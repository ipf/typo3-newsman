<?php

declare(strict_types=1);

namespace Ipf\NewsMan\Controller;

use Ipf\NewsMan\Service\MailmanService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class SubscribeController extends ActionController
{
    public function __construct(
        protected readonly MailmanService $mailmanService,
    ) {
    }

    public function subscribeAction(): ResponseInterface
    {
        // The form is rendered inside a page on a plain GET, so there is nothing
        // to subscribe then and no message is shown. getArgument() would throw
        // when the field is absent, which is why the whole argument array is
        // read; an address that is there but empty is the invalid one.
        $arguments = $this->request->getArguments();
        $email = trim((string)($arguments['email'] ?? ''));
        $listId = trim((string)($this->settings['list'] ?? ''));

        if ($email === '' && $this->request->getMethod() !== 'POST') {
            return $this->htmlResponse();
        }

        $result = $this->mailmanService->subscribe($email, $listId);

        if ($result['success']) {
            $this->view->assignMultiple([
                'newsmanSeverity' => 'success',
                'newsmanMessage' => $this->successMessage($result),
                'newsmanMessageKey' => null,
            ]);
        } else {
            $this->view->assignMultiple([
                'newsmanSeverity' => 'error',
                'newsmanMessage' => null,
                // Translated in the template: LanguageService is not a DI service
                // since TYPO3 v13, so the key is handed to Fluid instead.
                'newsmanMessageKey' => (string)($result['messageKey'] ?? 'error.unknown'),
            ]);
        }

        return $this->htmlResponse();
    }

    /**
     * @param array{success: bool, pending?: bool} $result
     */
    protected function successMessage(array $result): string
    {
        $custom = trim((string)($this->settings['successMessage'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        // The confirmation-mail mode tells the visitor to look out for a mail,
        // the REST mode subscribes directly.
        return !empty($result['pending'])
            ? 'LLL:EXT:newsman/Resources/Private/Language/locallang.xlf:success.confirmationPending'
            : 'LLL:EXT:newsman/Resources/Private/Language/locallang.xlf:success.default';
    }
}
