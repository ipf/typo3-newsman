#ddev-generated
# Postorius settings for local development.
#
# The container's entrypoint copies this file from the data volume into
# /opt/mailman-web/settings_local.py, where settings.py imports it at the end
# (`from settings_local import *`).
#
# Why it is needed: maxking/postorius is built to run behind a reverse proxy
# (the maxking/mailman-web image adds nginx). Neither nginx nor WhiteNoise is in
# this image, so with the default uwsgi command nothing serves /static and the
# interface comes up unstyled. The compose file therefore runs Django's
# development server, which only serves static files when DEBUG is True.
#
# This is a local-only container. Do not copy this into a deployment: there the
# real solution is a reverse proxy in front of uWSGI, which is what the
# mailman-web image provides.

DEBUG = True

# The development server must not try to reload on every file change inside the
# container; the compose file passes --noreload as well.
