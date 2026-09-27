.. _usage:

=====
Usage
=====

Content element
===============

Create a content element in the page and choose **Newsman Subscribe**
(``CType: newsman_subscribe``). The element is registered by the extension
itself, so no TypoScript or TypoScript object needs to be copied into the site
package.

.. _usage-flexform:

FlexForm settings
=================

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Field
     - Description
   * - ``list``
     - The mailing list, e.g. ``newsletter@example.com``. Required
   * - ``successMessage``
     - Text shown instead of the default success message. A label key
       (``LLL:EXT:newsman/...:success.default``) is also accepted and translated
   * - ``emailCommand``
     - Overrides the ``emailCommand`` setting for this element, ``subscribe`` or
       ``request``. Only used by ``mode = email``; the first option means "from
       the extension settings"
   * - ``emailSender``
     - Overrides the ``emailSender`` setting for this element. Only used by
       ``mode = email`` together with ``request``
   * - ``emailLabel``
     - Text of the field label
   * - ``emailPlaceholder``
     - Placeholder of the input
   * - ``buttonLabel``
     - Text of the submit button

The three form texts are plain texts rather than labels, because the site
brings its own wording. The two command settings are overrides rather than
requirements: an empty field falls back to the value from
:ref:`configuration-email-mode`, so a site configures them once and only the
elements that need something else touch them.

Behaviour of the form
=====================

The field is posted into the plugin namespace
(``tx_newsman_subscribe[email]``), which is why the form carries
``name="tx_newsman_subscribe"``. The address is validated before anything is
sent to Mailman, so an invalid address produces a validation error and no
request leaves TYPO3.

The action is registered as a non-cacheable action, so the response to a
submission is never served from a page cache.

Styling
=======

The template loads the stylesheet itself with ``<f:asset.css>``, so no
TypoScript is needed; ``EXT:newsman/Resources/Public/Css/newsman.css`` is
published with the page.

.. list-table::
   :header-rows: 1
   :widths: 40 60

   * - Class
     - Element
   * - ``newsman-subscribe``
     - Wrapper of the whole form
   * - ``newsman-subscribe__form``
     - The ``<form>`` element
   * - ``newsman-subscribe__field``
     - Wrapper of field and label
   * - ``newsman-subscribe__label``
     - Label of the email field, visually hidden, read out by screen readers
   * - ``newsman-subscribe__input``
     - The email input
   * - ``newsman-subscribe__submit``
     - The submit button
   * - ``newsman-subscribe__message``
     - Base class of a message
   * - ``newsman-subscribe__message--success``
     - Success message
   * - ``newsman-subscribe__message--error``
     - Error message, additionally marked with ``role="alert"``

The bundled CSS only styles the form itself; the design of the page around it
comes from the site package, which can overwrite these classes.

Site integration
================

The plugin is registered as ``EXTBASEPLUGIN`` from the extension's own
``Configuration/TypoScript/setup.typoscript`` — deliberately **not** via
``ExtensionUtility::configurePlugin()``. That helper emits
``tt_content.newsman_subscribe =< lib.contentElement`` with
``templateName = Generic`` at ``defaultContentRendering``, i.e. after every site
configuration. A site package that redefines ``lib.contentElement`` as a
``FLUIDTEMPLATE`` (a common pattern that derives the template name from the
CType) would then be overridden, the numbered ``20 = EXTBASEPLUGIN`` child would
be dropped by ``FLUIDTEMPLATE``, and the element would render empty.

Because site configuration is applied after extension TypoScript, a site package
that dispatches content elements through a ``CASE`` has to repeat the key
itself. A site package that wants to put its own texts and design around the
form overrides the object with a ``FLUIDTEMPLATE`` again, keeps the plugin as
its child ``20`` and calls that child from its template:

.. code-block:: typoscript

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

.. code-block:: html

   <!-- Content/NewsmanSubscribe.fluid.html -->
   <f:cObject typoscriptObjectPath="tt_content.{data.CType}.20" data="{data}" table="tt_content"/>

``FLUIDTEMPLATE`` does not render numbered children by itself, which is why the
template calls the child the way the core does it in
``EXT:fluid_styled_content/Resources/Private/Templates/Generic.fluid.html``.

.. _usage-bot-protection:

Bot protection (optional)
=========================

The form subscribes whoever submits it, so an open form is also an invitation to
spam: a bot can put arbitrary third-party addresses into the list. The extension
has no captcha of its own, because a good one is a solved problem and both
candidates need configuration a site owns anyway.

`subugoe/typo3-cap <https://github.com/subugoe/typo3-cap>`_ is the recommended
addition. It is a PSR-15 middleware that asks for a
`proof of work <https://capjs.org/guide/>`_ before a request to a protected path
is answered, so the extension needs no field, no template change and no code —
installing it is enough.

.. code-block:: bash

   composer require subugoe/typo3-cap

Cap protects **paths**, and the form posts to the page it is rendered on, so
that path is the unit of protection. Put the signup element on a page of its
own and protect that page, otherwise the proof is required for the whole site:

.. code-block:: typoscript

   tx_typo3cap {
     enabled = 1
     siteKey = your-site-key
     serviceUrl = http://cap:3000
     protectedPaths = /newsletter/
   }

These are Page TSconfig settings of the site root (page properties → Resources
→ Page TSconfig); the same keys work as environment variables (``CAP_ENABLED``,
``CAP_SITE_KEY``, ``CAP_SECRET_KEY``, ``CAP_SERVICE_URL``,
``CAP_PROTECTED_PATHS``), which take effect when no Page TSconfig value is set.
``serviceUrl`` has to be reachable from PHP, while browsers talk to Cap through
the same-origin proxy of the extension, so the Cap server does not need a public
address.

``protectedPaths`` also takes ``#``-delimited PCRE expressions, which is the way
to protect a single page among several that share a path prefix:

.. code-block:: typoscript

   protectedPaths = #/newsletter-signup.*#

Two things to know before enabling it:

* The first visit to a protected page briefly shows a loading page while the
  proof is solved; every page rendered afterwards prepares the next proof in
  the background.
* Cap consumes a proof once and then continues the navigation for about ten
  seconds, during which one repeated ``GET`` and a few redirects are allowed.
  Duplicate ``POST`` submissions stay rejected, so a visitor who submits the
  form twice has to solve a new proof. The extension itself has no
  application-level idempotency for the same reason.

The package requires TYPO3 12.4 or 13.4. On TYPO3 14 it cannot be installed, so
it is listed under ``suggest`` in ``composer.json`` rather than required; the
form keeps working unprotected there.

.. _usage-multiple-lists:

Several lists on one site
=========================

Because the list is a FlexForm field, every content element can subscribe to a
different list. The forms are independent; each one uses the address and the
credentials from the global extension settings.

Translating the form
=====================

All backend labels and the fixed messages are labels in
``EXT:newsman/Resources/Private/Language/locallang.xlf``. The texts of field
label, placeholder and button are FlexForm fields and are therefore translated
per site language with the normal record translation of the content element.
