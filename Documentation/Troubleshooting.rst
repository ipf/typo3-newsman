.. _troubleshooting:

===============
Troubleshooting
===============

The form is empty
=================

The content element renders nothing at all. That is what happens when the
plugin registration was overridden:

* a site package dispatches content elements through a ``CASE`` and does not
  repeat the key ``tt_content.newsman_subscribe``,
* or a site package set ``templateName`` to something that does not exist.

Compare your TypoScript with :ref:`usage` (*Site integration*). The extension
registers the object itself, so the site package only has to override it, never
to enable it.

.. tip::

   Use ``typo3contentelements`` in the "List module" of the page to see which
   TypoScript object is actually rendered.

The form says the server is not configured
==========================================

``apiUrl`` is empty. Check, in this order:

#. ``config/system/settings.php`` really returns the value — a missing
   ``return`` statement or a wrong array level silently does nothing.
#. The caches were flushed after the change
   (``vendor/bin/typo3 cache:flush``).
#. The Extension Manager shows the value under **Configure Extension**; if it
   does, the value reaches TYPO3 and the problem is the Mailman URL itself.

The mailing list does not exist
===============================

The list identifier in the FlexForm does not match Mailman. Both the posting
address (``newsletter@example.com``) and the list id
(``newsletter.example.com``, optionally ``list:newsletter.example.com``) are
accepted; the domain of the list must match the domain Mailman knows.

The server offers no Mailman 3 REST API
========================================

``error.noRestApi`` means the answer to ``apiUrl`` was not the Mailman REST API.
The service answers with that key when ``apiUrl`` returns a ``404`` whose body
is not JSON, because the Mailman API answers with JSON even for an unknown path.
The usual cause is a Mailman **2** host: its web interface is a different
application and it has no REST API at all, so ``/3.0/members`` does not exist.

* A hosted or shared list server (rather than a self-hosted Mailman 3) usually
  offers no REST API, and no REST account with the admin role either. Even with
  an API, subscribing with ``pre_verified``, ``pre_confirmed`` and
  ``pre_approved`` set bypasses confirmation and moderation, which such a server
  does not allow.
* Check the URL: ``apiUrl`` is the base of the API, ``https://host/3.0``, not
  the address of the list page.
* For a Mailman 2 host, switch to ``mode = email``. It sends a command mail to
  the list server instead, and the visitor confirms with the list server itself;
  see :ref:`configuration-email-mode`.

The command mail is not delivered
===============================

``mode = email`` hands one mail to the MTA and stops there; everything after that
belongs to the list server, so the form cannot tell a delivered mail from a
rejected one. The visitor is told to look out for a confirmation, and if none
arrives, check on the list server.

The usual cause is SPF/DMARC. With ``emailCommand = subscribe`` the mail claims
to come from the visitor while it comes from the web host, which fails those
checks at most hosted list servers. Switch to ``emailCommand = request`` and set
``emailSender`` to an address of the web host: the address then travels in the
body of the mail to ``<list>-request@<domain>`` and the sender is the site. See
:ref:`configuration-email-mode`.

Other things to check:

* ``mail()`` is PHP's own mailer, so the host needs a working MTA. A web host
  without one reports ``error.mailFailed`` right away, while a host with a
  silently broken one reports success and nothing arrives.
* ``emailDomain`` is empty and the list in the FlexForm has no ``@``, so the
  domain cannot be derived. That is ``error.notConfigured``.
* The list server answers the confirmation to the address in the command, so a
  list with *confirmation and approval* needs a moderator to accept the
  subscription afterwards. Nothing in the form shows or stores that state.

See :ref:`configuration-email-mode` for the two command shapes.

Mailman is not reachable
========================

* ``timeout`` (10 seconds by default) is too low for a slow or remote instance.
* ``verifySsl = 0`` was set to ``1`` again, and the certificate of the Mailman
  host is not trusted. For a self-signed dev instance, ``verifySsl = 0`` is
  acceptable; in production, install the certificate.
* A reverse proxy or firewall blocks the requests from the web container.

``error.connection`` is the message for all of these; the real reason is in the
TYPO3 log (Administration > Log), where the HTTP status of the request is
recorded.

The REST user is rejected
=========================

The Mailman response was ``401``/``403``. The user needs the admin role:

.. code-block:: none

   mailman shell
   >>> from mailman.rest.auth import add_member
   >>> add_member('example.com', 'restadmin', 'secret')

If ``authToken`` is set, it wins over user and password — an outdated token
therefore looks like a wrong password.

Mailman returns a 500 on a valid request
========================================

Mailman's REST API expects the ``pre_*`` flags as **strings**. Real JSON
booleans make its validator throw a 500, because ``lazr.config`` calls
``->lower()`` on them. The service sends them quoted, so this only appears after
a manual change of the payload. See :ref:`service-reference`.

A visitor is subscribed without confirming
===========================================

This is the default: ``pre_verified``, ``pre_confirmed`` and ``pre_approved``
are sent as ``"true"``. See :ref:`opt-in` for the double opt-in flow.

Spam subscriptions appear in the list
======================================

A visitor of the form can put any third-party address into the mailing list, so
an unprotected form attracts bot subscriptions. Install ``subugoe/typo3-cap`` and
protect the path of the page the form is on; see :ref:`usage-bot-protection`.
Bots that got through before it was installed are ordinary members and are
removed in Mailman (or Postorius), or with ``DELETE /3.0/members/<member_id>``.

The page cache serves a stale message
=====================================

The ``subscribe`` action is registered as non-cacheable, so a submission is
never answered from the cache. A stale message in the page itself (not after a
submission) points to a site-specific ``USER_INT``/``COA`` wrapper that has
caches the content element — such a wrapper has to include the element as
``USER_INT`` or bypass the cache.
