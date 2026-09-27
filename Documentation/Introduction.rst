.. _introduction:

============
Introduction
============

What it does
============

Newsman renders a subscribe form (a single email field and a submit button) as a
TYPO3 content element. A visitor enters an address, and Newsman adds that
address as a member of a remote Mailman mailing list. The form texts and the
mailing list are per element, so different pages can subscribe visitors to
different lists.

Mailman is not part of TYPO3 and Newsman does not replace it. Newsman is only the
frontend that talks to Mailman 3 over its REST API (or, in the legacy
``email`` mode, sends a Mailman 2 style subscription mail).

What it does not do
===================

* No double opt-in out of the box. The extension subscribes visitors directly
  (see :ref:`opt-in`), so double opt-in has to be switched on in the service.
* No newsletter rendering. Sending and archiving the actual messages is the job
  of Mailman and HyperKitty.
* No subscriber management in the TYPO3 backend. Lists, members and moderation
  are managed in Mailman itself (or in Postorius, Mailman 3's web interface).
* No site storage. Nothing of the form input is written to the TYPO3 database.

Features
========

* Content element ``newsman_subscribe`` with a FlexForm for list and texts
* Mailman 3 REST API as well as Mailman 2 style subscription mail
* Basic authentication or bearer token
* Validation of the address before anything is sent
* Meaningful messages for every failure case instead of a silent dead form
* Non-cacheable plugin action, so the response is never served from a page cache
* No TypoScript setup required, no `ext_emconf.php`, no deprecations

.. _opt-in:

Double opt-in
=============

By default the address is subscribed immediately, because the service sends the
REST request with ``pre_verified``, ``pre_confirmed`` and ``pre_approved`` set
(see :ref:`service-reference`). For a confirmation mail, pass those flags as
``"false"`` in
``Ipf\NewsMan\Service\MailmanService::subscribeViaRest()``; Mailman then sends
the confirmation request and the member is only added after the visitor
confirms. The form reports this state with the message
``success.confirmationPending``.

.. tip::

   Restrict the form in a workspace or a staging environment if double opt-in is
   switched on, so a test run does not add real addresses to the production
   list.
