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

The page cache serves a stale message
=====================================

The ``subscribe`` action is registered as non-cacheable, so a submission is
never answered from the cache. A stale message in the page itself (not after a
submission) points to a site-specific ``USER_INT``/``COA`` wrapper that has
caches the content element — such a wrapper has to include the element as
``USER_INT`` or bypass the cache.
