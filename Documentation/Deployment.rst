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

A Mailman 3 core runs as a DDEV add-on container (see
``.ddev/docker-compose.mailman.yaml``), so nothing has to be installed on the
host:

.. code-block:: bash

   ddev start
   ddev newsman setup newsletter@fowi.test

.. list-table::
   :header-rows: 1
   :widths: 45 55

   * - Command
     - Purpose
   * - ``ddev newsman setup [list]``
     - Create mail domain and list, print the configuration
   * - ``ddev newsman list``
     - List the mailing lists
   * - ``ddev newsman members [list]``
     - Show the subscribers
   * - ``ddev newsman test [list]``
     - Subscribe a throwaway address
   * - ``ddev newsman clean [list]``
     - Remove the throwaway subscribers again
   * - ``ddev newsman admin``
     - Enable the Postorius login and print its URL
   * - ``ddev newsman info``
     - Versions of core and Postorius
   * - ``ddev newsman logs [service]``
     - Container logs, ``mailman`` or ``postorius``

``ddev newsman clean`` only removes addresses on the test domain (``fowi.test``,
set ``NEWSMAN_TEST_DOMAIN`` to change it) and prints what it kept, so real
subscribers are never removed by accident.

Inside DDEV the web container reaches the API at ``http://mailman:8001/3.0``,
which is what ``MAILMAN_API_URL`` in ``.ddev/config.yaml`` sets. On the host the
API is published by ddev-router on ``https://fowi.ddev.site:8028``
(``MAILMAN_HTTPD`` for plain HTTP, default 8027); because the router routes by
hostname, requests to ``localhost`` have to carry the ``Host`` header, which the
``ddev newsman`` command does.

Admin interface (Postorius)
===========================

Lists, members, moderation and settings are managed in Postorius, Mailman 3's
web interface, which runs as a second container:

.. code-block:: bash

   ddev start
   ddev newsman admin          # sets the password, prints the URL

* http://fowi.ddev.site:8029 (or ``https://fowi.ddev.site:8030``)
* user ``admin``, password ``admin`` (``POSTORIUS_ADMIN_USER`` /
  ``POSTORIUS_ADMIN_PASSWORD``)

HyperKitty, the web archive of sent messages, is not part of this image.

Three things about that container are worth knowing, because the image is built
to run behind a reverse proxy:

* It contains neither nginx nor WhiteNoise, so it cannot serve ``/static`` on
  its own. The compose file therefore runs Django's development server with
  ``DEBUG`` from ``.ddev/mailman/settings_local.py``. Use a real reverse proxy
  (that is what the ``maxking/mailman-web`` image adds) for anything but local
  use.
* Its ``settings.py`` calls ``gethostbyname("mailman-web")`` while building
  ``ALLOWED_HOSTS``, so the container needs that network alias or Django dies on
  import before reading any environment variable.
* ``createsuperuser --noinput`` leaves the account without a password, and
  allauth additionally expects an ``EmailAddress`` record. Without both, the
  first login ends in a 500. ``ddev newsman admin`` sets them, and is safe to
  re-run.
