# Newsman — Mailman newsletter subscription for TYPO3

A subscribe button (email field) as a TYPO3 content element, subscribing
visitors to a remote GNU Mailman 3 mailing list.

- Extension key: `newsman`
- Namespace: `Ipf\NewsMan\`
- Package: `ipf/newsman`
- Supports **TYPO3 13.4 and 14.x**
- Full documentation: [`Documentation/`](Documentation/Index.rst) (reStructuredText, TYPO3 Sphinx tooling)

The extension is standalone: it ships its own content element, FlexForm,
template and CSS, and stores nothing in the TYPO3 database. Mailman remains the
single source of truth for the subscribers.

## Installation

```bash
composer require ipf/newsman
```

Then activate it:

```bash
vendor/bin/typo3 extension:setup
```

## Configuration

The settings are declared in `ext_conf_template.txt`. The install tool keeps
`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['newsman']` in sync with it, and a
site overrides individual values in `config/system/settings.php`. Because the
keys come from the template, the install tool does not drop them when it
rewrites the configuration.

The connection details are read in the constructor of `MailmanService` through
constructor injection; the extension does not use `GeneralUtility::makeInstance()`.

| Key | Default | Purpose |
| --- | --- | --- |
| `mode` | `rest` | `rest` = Mailman 3 REST API, `email` = Mailman 2 style confirmation mail |
| `apiUrl` | `''` | Base URL of the REST API, e.g. `https://mailman.example.com/3.0` |
| `apiUser` | `''` | REST user (needs the Mailman admin role) |
| `apiPassword` | `''` | Password for `apiUser` |
| `authToken` | `''` | Bearer token; takes precedence over user/password when set |
| `verifySsl` | `1` | TLS certificate validation |
| `timeout` | `10` | HTTP timeout in seconds |
| `emailDomain` | `''` | Only for `mode = email`: the list domain, e.g. `example.com` |

Read the values from the environment so nothing secret ends up in the
repository:

```php
// config/system/settings.php
'EXTENSIONS' => [
    'newsman' => [
        'mode' => getenv('MAILMAN_MODE') ?: 'rest',
        'apiUrl' => getenv('MAILMAN_API_URL') ?: '',
        'apiUser' => getenv('MAILMAN_API_USER') ?: '',
        'apiPassword' => getenv('MAILMAN_API_PASSWORD') ?: '',
        'authToken' => getenv('MAILMAN_AUTH_TOKEN') ?: '',
        'verifySsl' => getenv('MAILMAN_VERIFY_SSL') !== 'false',
        'timeout' => (int)(getenv('MAILMAN_TIMEOUT') ?: 10),
        'emailDomain' => getenv('MAILMAN_EMAIL_DOMAIN') ?: '',
    ],
],
```

The settings can also be edited in the Extension Manager under
**Configure Extension**.

With `apiUrl` empty the plugin shows a "not configured" message instead of
failing silently, so a forgotten configuration is visible rather than a dead
form.

## Content element

Create a content element → **Newsman Subscribe** (`CType: newsman_subscribe`).

FlexForm settings:

| Setting | Description |
| --- | --- |
| `list` | The mailing list, e.g. `newsletter@example.com` |
| `successMessage` | Optional text shown instead of the default success message |
| `emailLabel` | Text of the field label |
| `emailPlaceholder` | Placeholder of the input |
| `buttonLabel` | Text of the submit button |

The three form texts are plain texts rather than labels, because the site brings
its own wording. Both the posting address (`newsletter@example.com`) and
Mailman's list id (`newsletter.example.com`, optionally prefixed with `list:`)
are accepted.

### Styling

The template loads the stylesheet itself with `<f:asset.css>`, so no TypoScript
is needed; `EXT:newsman/Resources/Public/Css/newsman.css` is published with the
page. Classes: `newsman-subscribe`, `newsman-subscribe__form`,
`newsman-subscribe__field`, `newsman-subscribe__label`,
`newsman-subscribe__input`, `newsman-subscribe__submit`,
`newsman-subscribe__message--{success,error}`.

## Mailman setup (server side)

The REST user needs the admin role:

```bash
mailman shell
>>> from mailman.rest.auth import add_member
>>> add_member('example.com', 'restadmin', 'secret')   # domain, user, password
```

The extension subscribes with `pre_verified`, `pre_confirmed` and
`pre_approved` set, so the visitor becomes a member immediately. For a double
opt-in flow, send those as `"false"` in `MailmanService::subscribeViaRest()` and
let Mailman send the confirmation mail.

## Site integration notes

The plugin is registered as `EXTBASEPLUGIN` from the extension's own
`Configuration/TypoScript/setup.typoscript` — deliberately **not** via
`ExtensionUtility::configurePlugin()`. That helper emits
`tt_content.newsman_subscribe =< lib.contentElement` with
`templateName = Generic` at `defaultContentRendering`, i.e. after every site
configuration. A site package that redefines `lib.contentElement` as a
`FLUIDTEMPLATE` (a common pattern that derives the template name from the
CType) would then be overridden, the numbered `20 = EXTBASEPLUGIN` child would
be dropped by `FLUIDTEMPLATE`, and the element would render empty.

Because site configuration is applied after extension TypoScript, a site
package that dispatches content elements through a `CASE` has to repeat the
key itself. Such a site package can render the plugin as a numbered child of
its own template, so that the texts and the design of the site can be put
around the form:

```typoscript
tt_content = CASE
# ...
tt_content.newsman_subscribe =< lib.contentElement
tt_content.newsman_subscribe {
  templateName = NewsmanSubscribe
  20 = EXTBASEPLUGIN
  20 {
    extensionName = Newsman
    pluginName = Subscribe
  }
}
```

```html
<!-- Content/NewsmanSubscribe.fluid.html -->
<f:cObject typoscriptObjectPath="tt_content.{data.CType}.20" data="{data}" table="tt_content"/>
```

`FLUIDTEMPLATE` does not render numbered children by itself, which is why the
template calls the child the way the core does it in
`EXT:fluid_styled_content/Resources/Private/Templates/Generic.fluid.html`.

`templateName` stays empty on purpose so Extbase resolves the template from
controller and action (`Resources/Private/Templates/Subscribe/Subscribe.html`)
instead of a hard-coded name.

The texts of the field and of the button are plain texts of the FlexForm, not
labels: the site brings its own wording. The stylesheet of the form is
loaded by the template with `f:asset.css`, because TYPO3 v14 has no
`configureExtension()` for frontend stylesheets any more and the form is only
ever rendered inside a page.

## Behaviour

| Situation | Result |
| --- | --- |
| New address | Member added, success message |
| Already subscribed | "already subscribed" hint, no duplicate |
| Invalid address | Validation error, nothing sent |
| Mailman unreachable | Connection error, no silent failure |
| Unknown list | Points the editor at the list configuration |
| `apiUrl` empty | "not configured" hint |

Mailman's REST API expects the `pre_*` flags as **strings**; real JSON booleans
make its validator throw a 500 (`lazr.config` calls `->lower()` on them). The
service sends them quoted for that reason.

## Local development with DDEV

Optional: a local GNU Mailman 3 core and its admin interface run as DDEV
services, so the form can be developed and tested without a hosted instance.
The extension ships them as a DDEV add-on in [`ddev/`](ddev/README.md), which is
installed from the project:

```bash
ddev add-on get vendor/ipf/newsman/ddev
ddev start
```

The add-on writes `.ddev/.env.web.newsman` (the settings the web container
reads, `apiUrl` = `http://mailman:8001/3.0`) and `.ddev/.env.newsman` (the
credentials of the two services), so nothing has to be configured by hand.
`ddev add-on remove newsman` takes it away again.

`ddev describe` then shows both services with their URLs and credentials:

| Service | URL from the host | Credentials |
| --- | --- | --- |
| `mailman` | `https://<project>.ddev.site:8028/3.0` | `restadmin` / `restpass` |
| `postorius` | `https://<project>.ddev.site:8030` | `admin` / `admin` |

`<project>` is the `name` from `.ddev/config.yaml`; ddev-router routes by
hostname, so requests to `localhost` have to carry the `Host` header. Ports and
credentials are changed in `.ddev/.env.newsman`, and
[`ddev/README.md`](ddev/README.md) has the steps that create the mail domain,
the list and the Postorius password.

### Admin interface (Postorius)

Lists, members, moderation and settings are managed in Postorius, Mailman 3's
web interface, which runs as the second container:

* <http://<project>.ddev.site:8029> (or `https://<project>.ddev.site:8030`)
* user `admin`, password `admin` (`POSTORIUS_ADMIN_USER` / `POSTORIUS_ADMIN_PASSWORD`)

HyperKitty, the web archive of sent messages, is not part of this image.

Two things about that container are worth knowing, because the image is built
to run behind a reverse proxy:

- It contains neither nginx nor WhiteNoise, so it cannot serve `/static` on its
  own. The compose file therefore runs Django's development server with the
  `DEBUG` from `ddev/newsman/settings_local.py`. Use a real reverse proxy (that
  is what the `maxking/mailman-web` image adds) for anything but local use.
- Its `settings.py` calls `gethostbyname("mailman-web")` while building
  `ALLOWED_HOSTS`, so the container needs that network alias or Django dies on
  import before reading any environment variable.

## Security

- Form input is only used to call the Mailman API, nothing is persisted.
- TLS is verified by default; `verifySsl` should stay enabled in production.
- Credentials come from the environment, not from versioned files.

## Deprecation-free notes

- No `ext_emconf.php`. For composer packages it is deprecated (TYPO3
  deprecation 108345); metadata and constraints live in `composer.json`, which
  declares `extra.typo3/cms.version` and `providesPackages` so core treats the
  package as composer-only.
- `ext_conf_template.txt` is the supported way to declare settings, so
  `ExtensionConfiguration::get()` has values to work with.
- `ExtensionUtility::registerPlugin()` is public API. The controller actions are
  registered through Extbase's documented configuration array rather than
  `ExtensionUtility::registerControllerActions()`, which is marked `@internal`.
- `Configuration/TypoScript/setup.typoscript` replaces the TypoScript that
  `ExtensionUtility::configurePlugin()` would add.
- `ExtensionConfiguration` is constructor injected (it is a registered service),
  and no `$GLOBALS` is touched.
- Templates use the `*.fluid.html` extension so `typo3 fluid:analyze` covers
  them.
