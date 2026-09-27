#!/usr/bin/env python3
#ddev-generated
"""Health probe for the local Mailman 3 core container.

Answers 0 (healthy) as soon as the REST API serves a request. Mailman replies
401 when no credentials are attached, which still proves the API is up, so any
status below 500 counts as ready.
"""

import sys
import urllib.error
import urllib.request

URL = "http://mailman:8001/3.0/system/versions"

try:
    response = urllib.request.urlopen(URL, timeout=5)
except urllib.error.HTTPError as error:
    # 401/403 mean the API answered - it is the auth layer, not a broken server.
    sys.exit(0 if error.code < 500 else 1)
except Exception:
    sys.exit(1)

sys.exit(0 if response.status < 500 else 1)
