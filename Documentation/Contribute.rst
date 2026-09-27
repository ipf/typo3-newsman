.. _contribute:

=========
Contribute
=========

Workflow
========

#. Fork the repository and create a branch.
#. Run the TYPO3 test suite:

   .. code-block:: bash

      composer install
      vendor/bin/phpunit -c Build/phpunit.xml

#. For an end-to-end check, use the DDEV setup of :ref:`deployment-ddev`:
   ``ddev start`` and then subscribe a throwaway address with the ``curl`` call
   from ``ddev/README.md``.
#. Open a pull request that describes the behaviour change and references the
   issue.

.. _contribute-language-server:

Language server
===============

Editing happens with `PHPantom <https://github.com/PHPantom-dev/phpantom_lsp>`_,
a standalone language server that needs neither PHP nor Node at runtime. It is
configured by ``.phpantom.toml`` in the package root, which pins the PHP version
to the ``>=8.1`` from ``composer.json`` and switches on the project-wide
diagnostics, so a problem is reported in the file that is not open as well.

.. code-block:: bash

   phpantom_lsp analyze
   phpantom_lsp analyze Classes/ --severity error

In an editor it is an ordinary language server; Neovim drives it with the
built-in client, without a plugin:

.. code-block:: lua

   vim.lsp.config['phpantom'] = {
     cmd = { 'phpantom_lsp' },
     filetypes = { 'php' },
     root_markers = { 'composer.json', '.git' },
   }
   vim.lsp.enable('phpantom')

``analyze`` doubles as a CI gate: it is a single binary, reports unresolved
symbols in a PHPStan-style table and needs neither a baseline nor a level.

What it does not do is reason about types. It reports whether a class, a member
or an argument count exists — a language server that cannot resolve a symbol
reports nothing, so its silence is about resolution, not correctness. Type
analysis stays with PHPStan or Psalm, which PHPantom proxies automatically once
one of them is in ``require-dev`` with its own configuration file.

Conventions
===========

* PHP 8.1 syntax, no deprecations of TYPO3 13.4 or 14.x.
* Constructor injection for services; no ``GeneralUtility::makeInstance()`` for
  the extension's own classes, no ``$GLOBALS``.
* Templates use the ``*.fluid.html`` extension and are checked with
  ``typo3 fluid:analyze``.
* Every new message gets a label key in
  ``Resources/Private/Language/locallang.xlf`` and an entry in
  :ref:`reference`.
* Changes of the REST payload are tested against a real Mailman 3, because the
  validator reacts to the types of the ``pre_*`` flags.
* No magic calls that PHPantom cannot resolve: constructor injection keeps the
  types visible, which is also what makes them checkable.

Documentation
=============

This documentation is written in reStructuredText and built with the TYPO3
Sphinx tooling. Keep it in sync with the code: the settings table in
:ref:`configuration` and the messages in :ref:`reference` are the two places
that go stale most often.
