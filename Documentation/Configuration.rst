.. _configuration:

=============
Configuration
=============

Where the settings live
=======================

The settings are declared in ``ext_conf_template.txt``. The install tool keeps
``$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['newsman']`` in sync with it, and a
site overrides individual values in ``config/system/settings.php``. Because the
keys come from the template, the install tool does not drop them when it
rewrites the configuration.

The values are read in the constructor of
:class:`Ipf\\NewsMan\\Service\\MailmanService` through constructor injection.
The extension never uses ``GeneralUtility::makeInstance()`` for its own
services and never touches ``$GLOBALS``.

Settings
========

.. list-table::
   :header-rows: 1
   :widths: 25 15 60

   * - Key
     - Default
     - Purpose
   * - ``mode``
     - ``rest``
     - ``rest`` = Mailman 3 REST API, ``email`` = Mailman 2 style command mail
       (see :ref:`configuration-email-mode`)
   * - ``apiUrl``
     - ``''``
     - Base URL of the REST API, e.g. ``https://mailman.example.com/3.0``
   * - ``apiUser``
     - ``''``
     - REST user, needs the Mailman admin role
   * - ``apiPassword``
     - ``''``
     - Password for ``apiUser``
   * - ``authToken``
     - ``''``
     - Bearer token. Takes precedence over user/password when set
   * - ``verifySsl``
     - ``1``
     - TLS certificate validation
   * - ``timeout``
     - ``10``
     - HTTP timeout in seconds
   * - ``emailDomain``
     - ``''``
     - Only for ``mode = email``: the list domain, e.g. ``example.com``
   * - ``emailCommand``
     - ``subscribe``
     - Only for ``mode = email``: how the command mail is addressed,
       ``subscribe`` or ``request``
   * - ``emailSender``
     - ``''``
     - Only for ``mode = email`` with ``emailCommand = request``: the address the
       command mail is sent from

.. _configuration-email-mode:

The email mode
==============

``mode = email`` is how a Mailman 2 list is subscribed, because Mailman 2 has no
REST API. The extension sends one command mail and the list server takes it from
there: it answers the visitor with a confirmation mail that the extension never
sees. Both shapes below are part of Mailman 2 and end in that same confirmation.

.. list-table::
   :header-rows: 1
   :widths: 15 45 40

   * - ``emailCommand``
     - Mail
     - Note
   * - ``subscribe``
     - To ``<list>-subscribe@<domain>``, the address in the ``From:`` header
     - The default, and the one that has always been sent. The mail claims to
       come from the visitor while it actually comes from the web host, so it
       fails SPF/DMARC at most hosted list servers
   * - ``request``
     - To ``<list>-request@<domain>``, the body is ``subscribe <address>`` and
       the sender is ``emailSender``
     - The address travels in the body instead of the header, which is what makes
       the mail pass those checks. Use it for a list server you do not run
       yourself

``request`` requires a valid ``emailSender``, because that mail is sent on behalf
of the site: the list server answers the site operator, not the visitor. Without
it the form reports ``error.missingSender`` instead of sending a mail that would
only be rejected. The sender should be an address of the web host, for the same
reason.

Both settings can be overridden per content element in the FlexForm
(``emailCommand``, ``emailSender``), so one site can mix them; see
:ref:`usage-flexform`. A value that is not one of the two commands falls back to
``subscribe`` rather than being sent on: a typo must not become a command the
list server does not know.

.. _configuration-settings-php:

From ``settings.php``
=====================

Read the values from the environment, so that no secret ends up in the
repository:

.. code-block:: php

   // config/system/settings.php
   return [
       'EXTENSIONS' => [
           'newsman' => [
               'mode' => getenv('MAILMAN_MODE') ?: 'rest',
               'apiUrl' => getenv('MAILMAN_API_URL') ?: '',
               'apiUser' => getenv('MAILMAN_API_USER') ?: '',
               'apiPassword' => getenv('MAILMAN_API_PASSWORD') ?: '',
               'authToken' => getenv('MAILMAN_AUTH_TOKEN') ?: '',
               'verifySsl' => getenv('MAILMAN_VERIFY_SSL') !== 'false',
               'timeout' => (int) (getenv('MAILMAN_TIMEOUT') ?: 10),
               'emailDomain' => getenv('MAILMAN_EMAIL_DOMAIN') ?: '',
               'emailCommand' => getenv('MAILMAN_EMAIL_COMMAND') ?: 'subscribe',
               'emailSender' => getenv('MAILMAN_EMAIL_SENDER') ?: '',
           ],
       ],
   ];

Flush the TYPO3 caches afterwards, otherwise the old values are still served:

.. code-block:: bash

   vendor/bin/typo3 cache:flush

With an empty ``apiUrl`` the plugin shows a *not configured* message instead of
failing silently, so a forgotten configuration is visible rather than a dead
form.

.. _configuration-extension-manager:

Extension Manager
=================

The same values can be edited under **Extensions > Newsman > Configure
Extension**. This writes to the install tool's own configuration and is the
quick way to get a running instance; for production, prefer
:ref:`configuration-settings-php`.

Authentication
==============

Two authentication methods are supported:

``authToken``
   A bearer token, sent as ``Authorization: Bearer <token>``. Takes precedence
   when it is set. Convenient for a Mailman instance that issues tokens, because
   no password has to be stored.

``apiUser`` / ``apiPassword``
   HTTP basic authentication. The user needs the Mailman admin role.

TLS
===

``verifySsl`` is enabled by default and should stay enabled in production. Turn
it off only for a local instance with a self-signed certificate; the form then
talks to a connection anybody can intercept.

Mailing list identifier
=======================

The FlexForm field accepts both forms of a list identifier:

* the posting address, e.g. ``newsletter@example.com``
* Mailman's list id, e.g. ``newsletter.example.com``, optionally prefixed with
  ``list:``

Anything else is reported as *mailing list does not exist* and points the editor
at the list configuration.

Mailman setup (server side)
===========================

The extension subscribes with ``pre_verified``, ``pre_confirmed`` and
``pre_approved`` set, so a visitor becomes a member immediately. For a double
opt-in flow, see :ref:`opt-in`.
