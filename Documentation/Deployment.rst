.. _deployment:

==========
Deployment
==========

Checklist for production
========================

#. Set ``apiUrl``, ``apiUser``/``apiPassword`` or ``authToken`` in
   ``config/system/settings.php``, read from the environment
   (:ref:`configuration-settings-php`).
#. Leave ``verifySsl`` at ``1``.
#. Create the mailing list in Mailman (or Postorius) and note its posting
   address.
#. Create a **Newsman Subscribe** element with the list and the site wording.
#. Decide on double opt-in (:ref:`opt-in`) — especially relevant before a launch
   or a newsletter test run.
#. Flush the caches and submit the form once with a throwaway address.

Security notes
==============

* The submitted address is forwarded to Mailman and nowhere else; it is not
  written to the TYPO3 database or to a log.
* Credentials belong in the environment, not in a versioned file. The
  Extension Manager configuration is a convenient way to start, but a
  production installation should use ``settings.php``.
* Mailman is the only system that stores personal data here. Its own
  retention settings and its privacy policy apply to the newsletter.

.. _deployment-ddev:

Local development with DDEV
==========================

Optional: a local GNU Mailman 3 core and its admin interface run as DDEV
services, so the form can be developed and tested without a hosted instance.
The extension ships them as a DDEV add-on in ``ddev/``, which is installed from
the project:

.. code-block:: bash

   ddev add-on get vendor/ipf/newsman/ddev
   ddev start

The add-on writes ``.ddev/.env.web.newsman`` (the settings the web container
reads, with ``apiUrl`` = ``http://mailman:8001/3.0``) and ``.ddev/.env.newsman``
(the credentials of the two services), so nothing has to be configured by hand.
``ddev add-on remove newsman`` takes it away again.

``ddev describe`` then shows both services with their URLs and credentials:

.. list-table::
   :header-rows: 1
   :widths: 20 45 35

   * - Service
     - URL from the host
     - Credentials
   * - ``mailman``
     - ``https://<project>.ddev.site:8028/3.0``
     - ``restadmin`` / ``restpass``
   * - ``postorius``
     - ``https://<project>.ddev.site:8030``
     - ``admin`` / ``admin``

``<project>`` is the ``name`` from ``.ddev/config.yaml``; ddev-router routes by
hostname, so requests to ``localhost`` have to carry the ``Host`` header. Ports
and credentials are changed in ``.ddev/.env.newsman``. The steps that create the
mail domain, the list and the Postorius password are documented in
``ddev/README.md``, which ships with the extension.

Admin interface (Postorius)
===========================

Lists, members, moderation and settings are managed in Postorius, Mailman 3's
web interface, which runs as the second container:

* http://<project>.ddev.site:8029 (or ``https://<project>.ddev.site:8030``)
* user ``admin``, password ``admin`` (``POSTORIUS_ADMIN_USER`` /
  ``POSTORIUS_ADMIN_PASSWORD``)

HyperKitty, the web archive of sent messages, is not part of this image.

Two things about that container are worth knowing, because the image is built to
run behind a reverse proxy:

* It contains neither nginx nor WhiteNoise, so it cannot serve ``/static`` on
  its own. The compose file therefore runs Django's development server with the
  ``DEBUG`` from ``ddev/newsman/settings_local.py``. Use a real reverse proxy
  (that is what the ``maxking/mailman-web`` image adds) for anything but local
  use.
* Its ``settings.py`` calls ``gethostbyname("mailman-web")`` while building
  ``ALLOWED_HOSTS``, so the container needs that network alias or Django dies on
  import before any environment variable is read.

``createsuperuser --noinput`` additionally leaves the account without a
password, and allauth expects an ``EmailAddress`` record; without both, the
first login ends in a 500. ``ddev/README.md`` has the snippet that sets both.
