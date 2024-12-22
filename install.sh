#!/bin/bash
# Server-side install for the LAMP-ish stack movieventure runs on.
# Doesn't touch anything ingest-related; that's deployment-specific and lives
# in whatever fetcher/worker you wire up to /etc/movieventure/env.

set -e

apt-get install -y \
	apache2 \
	php \
	libapache2-mod-php \
	mysql-server mysql-client \
	certbot python3-certbot-apache \
	mediainfo \
	ffmpeg \
	bc jq

# Apache + Let's Encrypt are left for the operator to configure per-host.
# The expected layout once you're done:
#
#   /etc/movieventure/env             ingest -> INPUT_* env aliases (sourced by hook.sh)
#   /var/lib/movieventure/downloads   raw inputs
#   /var/lib/movieventure/encodes     encoder output (one dir per INPUT_HASH)
#   /var/log/movieventure/encode      encoder logs
#
# See config/config.example.php for everything else.

install -d -m 0755 \
	/var/lib/movieventure/downloads \
	/var/lib/movieventure/encodes \
	/var/log/movieventure/encode \
	/etc/movieventure
