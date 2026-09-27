.. _installation:

============
Installation
============

Requirements
============

============= ========================
Requirement   Version
============= ========================
TYPO3         13.4 or 14.x
PHP           8.1 or higher
Mailman       3 (Mailman 2 style mail optionally)
============= ========================

Composer
========

The extension is installed as a Composer package, which is also what tells the
TYPO3 core to treat it as a Composer-only extension:

.. code-block:: bash

   composer require ipf/newsman

Activate the extension afterwards:

.. code-block:: bash

   vendor/bin/typo3 extension:setup

In the Extension Manager, check that **newsman** is listed as *activated*.

Extension Manager
=================

Instead of Composer, the extension can be installed through the Extension
Manager as a local extension from the repository root. Because the package is
Composer-only, the metadata and version constraints come from
``composer.json`` — there is no ``ext_emconf.php``.

.. _installation-mailman:

Prepare Mailman
===============

The form calls Mailman as a REST user that needs the Mailman *admin* role. In
the Mailman container or on the host:

.. code-block:: none

   mailman shell
   >>> from mailman.rest.auth import add_member
   >>> add_member('example.com', 'restadmin', 'secret')   # domain, user, password

The newsletter list itself has to exist as well. Lists are created either in
Mailman directly, in Postorius, or with the DDEV helper of this repository
(see :ref:`deployment`).

The address of the REST API is the base URL of the Mailman core, including the
``/3.0`` path, for example ``https://mailman.example.com/3.0``.

Check the installation
======================

#. Set ``apiUrl`` and the credentials as described in :ref:`configuration`.
#. Create a content element of type **Newsman Subscribe** and enter a list.
#. Publish the page and subscribe a throwaway address.

If the form reports that the server is not configured, the settings have not
reached TYPO3 — see :ref:`troubleshooting`.
