.. _upgrade:

=======
Upgrade
=======

Version 1.0.0
=============

First public release. There is no predecessor, so no migration is needed. If you
are coming from a newsman that was installed without Composer, remove the old
plugin registration (``tt_content.newsman_subscribe`` from your site TypoScript)
and the stale ``ext_emconf.php`` before activating the package.

Checklist for an update
=======================

.. code-block:: bash

   composer require ipf/newsman:^1.0
   vendor/bin/typo3 extension:setup
   vendor/bin/typo3 cache:flush

No database schema changes are shipped, nothing has to be migrated, and no
TypoScript constant has to be copied. Existing content elements keep working,
because the CType ``newsman_subscribe`` and the FlexForm fields are stable.

After the update, check that:

* a **Newsman Subscribe** element still renders its form (TypoScript and
  registration are intact),
* a test subscription still reaches Mailman (settings were not dropped by the
  install tool),
* the messages of :ref:`reference` still appear as expected.
