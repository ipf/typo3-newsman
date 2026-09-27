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
   ``ddev start`` and ``ddev newsman test <list>``.
#. Open a pull request that describes the behaviour change and references the
   issue.

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

Documentation
=============

This documentation is written in reStructuredText and built with the TYPO3
Sphinx tooling. Keep it in sync with the code: the settings table in
:ref:`configuration` and the messages in :ref:`reference` are the two places
that go stale most often.
