.. _reference:

=========
Reference
=========

Extension settings
==================

See :ref:`configuration` for the full list of the settings of
``$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['newsman']``.

TypoScript
==========

The extension ships one TypoScript object, which is all that is needed to render
the content element:

.. code-block:: typoscript

   tt_content.newsman_subscribe = EXTBASEPLUGIN
   tt_content.newsman_subscribe {
     extensionName = Newsman
     pluginName = Subscribe
     templateName =
   }

``templateName`` is intentionally empty, so that Extbase resolves the template
from controller and action
(``Resources/Private/Templates/Subscribe/Subscribe.fluid.html``). A hard-coded
name would break as soon as the file is renamed.

Content element
===============

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Property
     - Value
   * - CType
     - ``newsman_subscribe``
   * - Plugin
     - ``Subscribe`` (Extbase), ``extensionName = Newsman``
   * - FlexForm
     - ``EXT:newsman/Configuration/FlexForms/Subscribe.xml``
   * - Table
     - ``tt_content``
   * - Icon
     - ``EXT:newsman/Resources/Public/Icons/Extension.svg``

FlexForm
========

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Field
     - TCA type
     - Notes
   * - ``settings.list``
     - ``input``, ``eval = trim,required``
     - Posting address or list id, see
       :ref:`configuration`
   * - ``settings.successMessage``
     - ``text``
     - Free text or a label key; empty falls back to
       ``success.default``
   * - ``settings.emailLabel``
     - ``input``
     - Default ``E-Mail-Adresse *``
   * - ``settings.emailPlaceholder``
     - ``input``
     - Default ``E-Mail-Adresse *``
   * - ``settings.buttonLabel``
     - ``input``
     - Default ``Newsletter abonnieren``

.. _service-reference:

Services
========

``Ipf\NewsMan\Service\MailmanService``
   Reads the extension configuration in its constructor and turns a submitted
   address plus a list id into a Mailman subscription. Public API:

   .. code-block:: php

      public function subscribe(string $email, string $listId): array

   The returned array carries a message key (``newsmanMessageKey``), an
   optional editor text (``newsmanMessage``) and a status
   (``success``, ``error`` or ``pending``).

``Ipf\NewsMan\Controller\SubscribeController``
   Renders the form and handles the ``subscribe`` action. It validates the
   address, hands the work to the service and passes the result to the
   template.

Classes are autowired and autoconfigured through
``Configuration/Services.yaml``; nothing is public and no
``GeneralUtility::makeInstance()`` is used.

REST request
============

The REST subscription is a ``POST`` to the members collection of the list:

.. code-block:: none

   POST <apiUrl>/lists/<listId>/members

The body is JSON. Mailman's REST API expects the ``pre_*`` flags as
**strings**; real JSON booleans make its validator throw a 500 (``lazr.config``
calls ``->lower()`` on them). The service therefore sends them quoted:

.. code-block:: json

   {
       "email": "visitor@example.com",
       "address": "Visitor",
       "pre_verified": "true",
       "pre_confirmed": "true",
       "pre_approved": "true"
   }

Messages
========

All messages live in ``Resources/Private/Language/locallang.xlf``:

.. list-table::
   :header-rows: 1
   :widths: 35 65

   * - Label key
     - Shown when
   * - ``success.default``
     - The address was added
   * - ``success.confirmationPending``
     - A confirmation mail was sent, the member is not added yet
   * - ``error.invalidEmail``
     - The address is not a valid mail address
   * - ``error.notConfigured``
     - ``apiUrl`` is empty
   * - ``error.missingList``
     - The content element has no list configured
   * - ``error.listNotFound``
     - Mailman does not know the list
   * - ``error.alreadySubscribed``
     - The address is already a member
   * - ``error.notAuthorized``
     - Mailman rejected the credentials
   * - ``error.connection``
     - Mailman is unreachable or timed out
   * - ``error.rejected``
     - Mailman did not accept the address
   * - ``error.mailFailed``
     - The confirmation mail could not be sent
   * - ``error.unknown``
     - Anything else

Templates and files
===================

.. list-table::
   :header-rows: 1
   :widths: 60 40

   * - File
     - Purpose
   * - ``Resources/Private/Templates/Subscribe/Subscribe.fluid.html``
     - Form and messages
   * - ``Resources/Public/Css/newsman.css``
     - Form styling
   * - ``Configuration/TypoScript/setup.typoscript``
     - ``EXTBASEPLUGIN`` registration
   * - ``Configuration/Services.yaml``
     - Autowiring of the classes
   * - ``Configuration/FlexForms/Subscribe.xml``
     - Element settings
   * - ``ext_conf_template.txt``
     - Declaration of the extension settings
   * - ``ext_localconf.php``
     - Registration of plugin and controller actions

The template loads the stylesheet itself with ``f:asset.css``, because TYPO3
v14 has no ``configureExtension()`` for frontend stylesheets any more and the
form is only ever rendered inside a page. The message is rendered in the
template rather than with ``f:flashMessages``, because the controller hands over
a label key and ``LanguageService`` is no longer available as a DI service since
TYPO3 v13.

Security
========

* Form input is only used to call the Mailman API, nothing is persisted.
* The plugin action is registered as non-cacheable, so a submission is never
  answered from a cache.
* TLS is verified by default; ``verifySsl`` should stay enabled in production.
* Credentials come from the environment, not from versioned files.

Deprecation-free notes
======================

* No ``ext_emconf.php``. For Composer packages it is deprecated (TYPO3
  deprecation 108345); metadata and constraints live in ``composer.json``,
  which declares ``extra.typo3/cms.version`` and ``providesPackages`` so the
  core treats the package as Composer-only.
* ``ext_conf_template.txt`` is the supported way to declare settings, so
  ``ExtensionConfiguration::get()`` has values to work with.
* ``ExtensionUtility::registerPlugin()`` is public API. The controller actions
  are registered through Extbase's documented configuration array rather than
  ``ExtensionUtility::registerControllerActions()``, which is marked
  ``@internal``.
* ``Configuration/TypoScript/setup.typoscript`` replaces the TypoScript that
  ``ExtensionUtility::configurePlugin()`` would add.
* ``ExtensionConfiguration`` is constructor injected (it is a registered
  service), and no ``$GLOBALS`` is touched.
* Templates use the ``*.fluid.html`` extension, so ``typo3 fluid:analyze``
  covers them.
