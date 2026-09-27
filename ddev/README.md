# Local Mailman with DDEV

A DDEV add-on that runs a local GNU Mailman 3 core and its web interface
(Postorius), so the subscription form can be developed and tested without a
hosted instance. Nothing in here is needed in production.

| File | Installed as | Purpose |
| --- | --- | --- |
| `install.yaml` | — | The add-on itself: files, version constraint, credential setup |
| `docker-compose.newsman.yaml` | `.ddev/docker-compose.newsman.yaml` | The `mailman` (REST API) and `postorius` (admin UI) services |
| `newsman/healthcheck.py` | `.ddev/newsman/healthcheck.py` | Health probe of the REST API |
| `newsman/postorius-healthcheck.py` | `.ddev/newsman/postorius-healthcheck.py` | Health probe of the Postorius web interface |
| `newsman/settings_local.py` | `.ddev/newsman/settings_local.py` | `DEBUG = True` for Postorius, so its dev server serves the static files |

DDEV installs add-ons into the `.ddev` directory of the **project**, not from a
composer package, so the add-on is installed from its path inside `vendor/`.

## Install

From the project root, with the extension installed through composer:

```bash
ddev add-on get vendor/ipf/newsman/ddev
ddev start
```

The post-install action writes two files, both committed on purpose because
they only contain development credentials:

* `.ddev/.env.web.newsman` — `MAILMAN_MODE`, `MAILMAN_API_URL`,
  `MAILMAN_API_USER` and `MAILMAN_API_PASSWORD` for the web container, so
  `config/system/settings.php` finds them in the environment. `apiUrl` uses the
  service name, because the web container talks to Mailman in the network where
  `localhost` would be the web container itself.
* `.ddev/.env.newsman` — the credentials of both services.

To update after a new extension version, run `ddev add-on get` again; to
remove it, `ddev add-on remove newsman`. Removing also deletes the two `.env`
files, unless one of them has been extended with keys the add-on did not write —
that file is kept and named in the output, so nothing of yours is lost.

## Credentials in `ddev describe`

Both services carry an `x-ddev` block, so `ddev describe` lists their URLs and
credentials — no file has to be read:

```
│ mailman   │ OK │ https://my-project.ddev.site:8028/3.0 │ REST user: restadmin      │
│           │    │ InDocker:                           │ REST password: restpass  │
│           │    │  - mailman:8001                     │ In the web container:    │
│           │    │                                     │ http://mailman:8001/3.0  │
│ postorius │ OK │ https://my-project.ddev.site:8030   │ Admin user: admin        │
│           │    │ InDocker:                           │ Admin password: admin    │
```

The values come from the defaults in the compose file, so overriding them in
`.ddev/.env.newsman` changes both the containers and the `ddev describe` output.
The keys of that extension block need DDEV v1.24.10, the
`.ddev/.env.web.newsman` file needs v1.25.4, which is what `install.yaml`
declares as its `ddev_version_constraint`.

Change a port or a password in `.ddev/.env.newsman` and run `ddev restart`; the
labels are written when the containers are created.

| Variable | Default | Serves |
| --- | --- | --- |
| `MAILMAN_HTTPD` | `8027` | REST API over HTTP |
| `MAILMAN_HTTPS_PORT` | `8028` | REST API over HTTPS |
| `POSTORIUS_HTTPD` | `8029` | Postorius over HTTP |
| `POSTORIUS_HTTPS_PORT` | `8030` | Postorius over HTTPS |
| `MAILMAN_REST_USER` / `MAILMAN_REST_PASSWORD` | `restadmin` / `restpass` | REST account of both containers |
| `POSTORIUS_ADMIN_USER` / `POSTORIUS_ADMIN_PASSWORD` | `admin` / `admin` | Postorius superuser |

## Where the services are

`ddev describe` lists both services with their URLs and credentials, and
`ddev logs -s mailman` (or `-s postorius`) shows what they log.

ddev-router routes by hostname, so every request from the host has to carry the
project hostname — `<project>` is the `name` from `.ddev/config.yaml`:

```bash
curl -u restadmin:restpass -H "Host: <project>.ddev.site" \
    http://localhost:${MAILMAN_HTTPD:-8027}/3.0/system/versions
```

Mailman answers `200` with the version list, which means the core is up.

## Create a mail domain and a list

Mailman refuses to create a list on an unknown domain and the REST API has no
endpoint for domains, so the domain is created through the CLI:

```bash
printf '%s\n' \
    'from mailman.interfaces.domain import IDomainManager' \
    'from zope.component import getUtility' \
    'manager = getUtility(IDomainManager)' \
    'if manager.get("example.test") is None:' \
    '    manager.add("example.test")' \
    '' | ddev exec -s mailman -- mailman shell
```

The blank line is required: without it the shell swallows the following
statement as part of the indented block.

Then the list itself, through the REST API:

```bash
LIST=newsletter@example.test
AUTH=restadmin:restpass
HOST="<project>.ddev.site:8027"

curl -u "$AUTH" -H "Host: $HOST" -H 'Content-Type: application/json' \
    -d "{\"fqdn_listname\":\"$LIST\"}" http://localhost:8027/3.0/lists
```

`POST` is not idempotent, so this answers `400` when the list already exists.
Check with `GET /3.0/lists/$LIST` and open the list for posting, so test
messages arrive in Mailpit:

```bash
curl -u "$AUTH" -H "Host: $HOST" -H 'Content-Type: application/json' \
    -X PATCH -d '{"display_name":"Newsletter","subscription_policy":"open","unsubscription_policy":"open"}' \
    http://localhost:8027/3.0/lists/$LIST/config
```

Put the list address in the FlexForm of the content element, and the form is
live: `apiUrl`, `apiUser` and `apiPassword` are already in the environment of
the web container through `.ddev/.env.web.newsman`.

## Try a subscription

```bash
curl -u "$AUTH" -H "Host: $HOST" -H 'Content-Type: application/json' \
    -d '{"list_id":"newsletter.example.test","subscriber":"throwaway@example.test","pre_verified":"true","pre_confirmed":"true","pre_approved":"true"}' \
    http://localhost:8027/3.0/members
```

`201` means subscribed, `409` means the address is already on the list. The
`pre_*` flags are strings on purpose: real JSON booleans make Mailman's
validator throw a 500.

Remove the throwaway addresses again by reading the roster
(`GET /3.0/lists/$LIST/roster/member`) and deleting each `member_id` with
`DELETE /3.0/members/<member_id>`. Only touch addresses on your own test
domain, so real subscribers are never deleted.

## Postorius login

Lists, members, moderation and settings are managed in Postorius at
`http://<project>.ddev.site:8029/`.

The container's entrypoint creates the superuser with `createsuperuser
--noinput`, which leaves it without a password, and allauth additionally
expects an `EmailAddress` record. Without both, the first login ends in a 500.
Set the password once, and again after changing `POSTORIUS_ADMIN_PASSWORD`. The
values come from the container environment, which the add-on fills from
`.ddev/.env.newsman` (`POSTORIUS_ADMIN_USER`, `POSTORIUS_ADMIN_PASSWORD`):

```bash
ddev exec -s postorius -- python3 - <<'PY'
import os

os.environ.setdefault("DJANGO_SETTINGS_MODULE", "settings")

import django

django.setup()

from django.contrib.auth import get_user_model

username = os.environ.get("POSTORIUS_ADMIN_USER", "admin")
password = os.environ.get("POSTORIUS_ADMIN_PASSWORD", "admin")
email = os.environ.get("MAILMAN_ADMIN_EMAIL", "admin@example.test")

User = get_user_model()
user, created = User.objects.get_or_create(
    username=username, defaults={"email": email, "is_staff": True, "is_superuser": True}
)
user.is_staff = user.is_superuser = True
user.email = email
user.set_password(password)
user.save()

# allauth sends a confirmation mail for an unverified address right after the
# first login; marking it verified skips that.
try:
    from allauth.account.models import EmailAddress
except ImportError:
    pass
else:
    address, _ = EmailAddress.objects.get_or_create(
        user=user, email=email, defaults={"verified": True, "primary": True}
    )
    address.verified = address.primary = True
    address.save()

print("created" if created else "updated", username)
PY
```

HyperKitty, the web archive of sent messages, is not part of this image; the
`maxking/mailman-web` image adds it.

## Notes on the Postorius container

* It is built to run behind a reverse proxy. The image contains neither nginx
  nor WhiteNoise, so it cannot serve `/static` on its own; the compose file
  therefore runs Django's development server, which serves static files with
  the `DEBUG` from `newsman/settings_local.py`. Use a real reverse proxy for
  anything but local use.
* Its `settings.py` calls `gethostbyname("mailman-web")` while it builds
  `ALLOWED_HOSTS`, so the container needs that network alias (the compose file
  adds it) or Django dies on import before any environment variable is read.
