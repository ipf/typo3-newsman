#!/usr/bin/env python3
#ddev-generated
"""Health probe for the Postorius container of the local Mailman setup.

Postorius is a Django application: while it boots it answers with a redirect to
the login page, and only a 5xx means it is actually broken. The probe therefore
exits 0 for anything below 500.
"""

import sys
import urllib.error
import urllib.request

URL = "http://localhost:8000/"

try:
    response = urllib.request.urlopen(URL, timeout=5)
except urllib.error.HTTPError as error:
    # A redirect that urllib does not follow lands here; that is a healthy state.
    sys.exit(0 if error.code < 500 else 1)
except Exception:
    sys.exit(1)

sys.exit(0 if response.status < 500 else 1)
