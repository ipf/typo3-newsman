<?php

declare(strict_types=1);

namespace Ipf\NewsMan\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Talks to a remote (self-hosted or hosted) GNU Mailman 3 instance.
 *
 * The connection settings come from the extension configuration, which the
 * install tool maintains from ext_conf_template.txt and which a site overrides
 * in config/system/settings.php.
 */
class MailmanService
{
    public const MODE_REST = 'rest';
    public const MODE_EMAIL = 'email';

    /**
     * Mail to <list>-subscribe@<domain> and let Mailman read the address from the
     * From: header. The default, because it is what the mode has always done.
     */
    public const EMAIL_COMMAND_SUBSCRIBE = 'subscribe';

    /**
     * Mail "subscribe <address>" to <list>-request@<domain> instead. The list
     * server then learns the address from the body rather than from the header,
     * which is the point: in the other mode the mail claims to come from the
     * visitor while actually coming from the web host, and that fails SPF/DMARC
     * at most hosted list servers.
     */
    public const EMAIL_COMMAND_REQUEST = 'request';

    protected string $mode;
    protected string $apiUrl;
    protected string $apiUser;
    protected string $apiPassword;
    protected string $authToken;
    protected bool $verifySsl;
    protected int $timeout;
    protected string $emailDomain;
    protected string $emailCommand;
    protected string $emailSender;

    /**
     * ExtensionConfiguration is registered as a service and carries an alias, so
     * the connection details are constructor injected instead of being fetched
     * with GeneralUtility::makeInstance(). The service is shared, so the settings
     * are read once here rather than on every call.
     */
    public function __construct(ExtensionConfiguration $extensionConfiguration)
    {
        $configuration = $extensionConfiguration->get('newsman') ?: [];

        $this->mode = (string)($configuration['mode'] ?? self::MODE_REST);
        $this->apiUrl = rtrim((string)($configuration['apiUrl'] ?? ''), '/');
        $this->apiUser = (string)($configuration['apiUser'] ?? '');
        $this->apiPassword = (string)($configuration['apiPassword'] ?? '');
        $this->authToken = (string)($configuration['authToken'] ?? '');
        // Values come from ext_conf_template.txt, so a boolean can arrive as
        // "1"/"0" rather than as a real bool.
        $this->verifySsl = !in_array((string)($configuration['verifySsl'] ?? '1'), ['0', '', 'false'], true);
        $this->timeout = (int)($configuration['timeout'] ?? 10);
        $this->emailDomain = trim((string)($configuration['emailDomain'] ?? ''), '@');

        $this->emailCommand = $this->normalizeEmailCommand(
            (string)($configuration['emailCommand'] ?? self::EMAIL_COMMAND_SUBSCRIBE)
        );
        $this->emailSender = trim((string)($configuration['emailSender'] ?? ''));
    }

    /**
     * Subscribe an address to a list. Returns ['success' => bool, 'message' => string].
     *
     * The two email arguments override the global setting for this call only, so
     * a content element can send its command differently from the rest of the
     * site. null means "use the configured value", which is what an untouched
     * FlexForm field sends.
     *
     * @param string|null $emailCommand One of the EMAIL_COMMAND_* constants
     */
    public function subscribe(
        string $email,
        string $listId,
        ?string $emailCommand = null,
        ?string $emailSender = null
    ): array {
        $email = trim($email);
        $listId = trim($listId);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'messageKey' => 'error.invalidEmail'];
        }
        if ($listId === '') {
            return ['success' => false, 'messageKey' => 'error.missingList'];
        }

        if ($this->mode !== self::MODE_EMAIL) {
            return $this->subscribeViaRest($email, $listId);
        }

        return $this->subscribeViaEmail(
            $email,
            $listId,
            $emailCommand === null ? $this->emailCommand : $this->normalizeEmailCommand($emailCommand),
            $emailSender === null ? $this->emailSender : trim($emailSender)
        );
    }

    /**
     * Mailman 3 REST API: POST /3.0/members
     *
     * The pre_* flags must be sent as strings: Mailman's validator converts
     * them with lazr.config's as_boolean, which calls .lower() and therefore
     * throws a 500 on real JSON booleans.
     */
    protected function subscribeViaRest(string $email, string $listId): array
    {
        if ($this->apiUrl === '') {
            return ['success' => false, 'messageKey' => 'error.notConfigured'];
        }

        $payload = json_encode([
            'list_id' => $this->normalizeListId($listId),
            'subscriber' => $email,
            'pre_verified' => 'true',
            'pre_confirmed' => 'true',
            'pre_approved' => 'true',
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init();
        $options = [
            CURLOPT_URL => $this->apiUrl . '/members',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_FOLLOWLOCATION => false,
        ];

        if ($this->authToken !== '') {
            $options[CURLOPT_HTTPHEADER][] = 'Authorization: Bearer ' . $this->authToken;
        } elseif ($this->apiUser !== '') {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = $this->apiUser . ':' . $this->apiPassword;
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            return [
                'success' => false,
                'messageKey' => 'error.connection',
                'message' => $curlError,
            ];
        }

        // curl_exec() is typed string|false, and the guard above has already
        // returned for false, so the cast is a no-op at runtime. It is here
        // because that is the only thing the static analysis accepts: it does not
        // narrow the type across the early return, and neither a reassignment nor
        // a ternary helps. Without the cast every use below carries string|false.
        $body = (string) $response;

        if ($httpCode === 400 || $httpCode === 422) {
            $detail = $this->extractDetail($body);
            // Mailman reports an unknown list as 400 "No such list" rather than
            // 404, so the message is inspected to keep the hint useful.
            return [
                'success' => false,
                'messageKey' => str_contains($detail, 'No such list')
                    ? 'error.listNotFound'
                    : 'error.rejected',
                'message' => $detail,
            ];
        }

        return match (true) {
            // A 404 whose body is not JSON does not come from Mailman: its REST
            // API always answers with JSON, even for an unknown path. A web
            // server that does not know /3.0 (a Mailman 2 host, a wrong port, a
            // proxy) returns its own HTML error page instead, and reporting
            // "list does not exist" would send the editor looking at the list
            // configuration while the apiUrl is the actual problem.
            $httpCode === 404 && !$this->isJson($body) => [
                'success' => false,
                'messageKey' => 'error.noRestApi',
            ],
            $httpCode === 200, $httpCode === 201 => ['success' => true],
            $httpCode === 409 => ['success' => false, 'messageKey' => 'error.alreadySubscribed'],
            $httpCode === 401, $httpCode === 403 => ['success' => false, 'messageKey' => 'error.notAuthorized'],
            $httpCode === 404 => ['success' => false, 'messageKey' => 'error.listNotFound'],
            default => [
                'success' => false,
                'messageKey' => 'error.unknown',
                'message' => 'HTTP ' . $httpCode . ' ' . $this->extractDetail($body),
            ],
        };
    }

    /**
     * Mailman identifies a list by its dotted list_id (newsletter.example.com),
     * not by the posting address (newsletter@example.com). Editors naturally
     * type the address, so both are accepted, as is the "list:" prefix that
     * Mailman uses when it renders list ids in emails and the admin UI.
     */
    protected function normalizeListId(string $listId): string
    {
        $listId = trim($listId);
        if (str_starts_with($listId, 'list:')) {
            $listId = substr($listId, 5);
        }

        return str_replace('@', '.', $listId);
    }

    /**
     * Mailman 2 style confirmation, sent as a command mail.
     *
     * Two shapes, both supported by Mailman 2, both of which end in the list
     * server mailing the visitor a confirmation:
     *
     * - EMAIL_COMMAND_SUBSCRIBE mails <list>-subscribe@<domain> and the address
     *   travels in the From: header.
     * - EMAIL_COMMAND_REQUEST mails "subscribe <address>" to
     *   <list>-request@<domain>, with emailSender as the sender. The address then
     *   travels in the body, so the mail no longer claims to come from the
     *   visitor while it comes from the web host - which is what makes it pass
     *   SPF/DMARC at a hosted list server.
     *
     * Either way the answer is only "the mail was handed to the MTA". Whether
     * Mailman accepted it is decided by the confirmation mail, which the
     * extension never sees.
     */
    protected function subscribeViaEmail(
        string $email,
        string $listId,
        string $command,
        string $sender
    ): array {
        $domain = $this->emailDomain;
        if ($domain === '' && str_contains($listId, '@')) {
            [$listId, $domain] = explode('@', $listId, 2);
        }
        if ($domain === '') {
            return ['success' => false, 'messageKey' => 'error.notConfigured'];
        }

        if ($command === self::EMAIL_COMMAND_REQUEST) {
            // The request address is a general command inbox, so a valid sender
            // is required: the mail is sent on behalf of the site, and the list
            // server answers the site, not the visitor.
            if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'messageKey' => 'error.missingSender'];
            }

            $recipient = $listId . '-request@' . $domain;
            $body = 'subscribe ' . $email . "\n";
            $from = $sender;
        } else {
            $recipient = $listId . '-subscribe@' . $domain;
            $body = "subscribe\n";
            $from = $email;
        }

        $headers = implode("\r\n", [
            'From: ' . $from,
            'Reply-To: ' . $from,
            'Content-Type: text/plain; charset=utf-8',
        ]);

        $sent = @mail($recipient, '', $body, $headers, "-f{$from}");

        return $sent
            ? ['success' => true, 'pending' => true]
            : ['success' => false, 'messageKey' => 'error.mailFailed'];
    }

    /**
     * Anything that is not one of the two known commands is treated as the
     * default rather than sent on: a typo in a FlexForm field must not turn into
     * a command the list server does not know.
     */
    protected function normalizeEmailCommand(string $command): string
    {
        $command = strtolower(trim($command));

        return in_array($command, [self::EMAIL_COMMAND_SUBSCRIBE, self::EMAIL_COMMAND_REQUEST], true)
            ? $command
            : self::EMAIL_COMMAND_SUBSCRIBE;
    }

    protected function extractDetail(string $response): string
    {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            foreach (['detail', 'message', 'error'] as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key])) {
                    return $decoded[$key];
                }
            }
        }
        return substr($response, 0, 200);
    }

    /**
     * Whether the body is a JSON object or array, i.e. whether it came from the
     * Mailman REST API. An empty body counts as JSON: that is Mailman's answer
     * to a successful POST, where only the status code carries the meaning.
     */
    protected function isJson(string $response): bool
    {
        if (trim($response) === '') {
            return true;
        }

        return is_array(json_decode($response, true));
    }
}
