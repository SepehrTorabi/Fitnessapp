#!/usr/bin/env bash
#
# Installs an unpacked Fitnessapp release.
#
# The package contains the application and the built frontend but no
# configuration and no database - those belong to the machine it runs on, not
# to the artifact. This script takes it from unpacked to running.
#
# Usage:
#   tar -xzf fitnessapp-<version>.tar.gz
#   cd fitnessapp-<version>
#   DATABASE_URL=... MAILER_DSN=... ./install.sh
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$HERE/app"

say() { printf '\033[1;34m==>\033[0m %s\n' "$1"; }
fail() { printf '\033[1;31mError:\033[0m %s\n' "$1" >&2; exit 1; }

[ -d "$APP_DIR" ] || fail "No app/ directory here. Run this from inside the unpacked release."

# --- Required configuration -------------------------------------------------
# Deliberately no defaults. A silent fallback to a local database is how a
# release ends up writing to the wrong place.
: "${DATABASE_URL:?Set DATABASE_URL, e.g. postgresql://user:pass@host:5432/fitnessapp?serverVersion=16}"
: "${APP_SECRET:=}"
: "${MAILER_DSN:=null://null}"
: "${FRONTEND_VERIFY_URL:=}"
: "${MAILER_SENDER_ADDRESS:=no-reply@localhost}"
: "${MAILER_SENDER_NAME:=Fitnessapp}"
: "${OPEN_FOOD_FACTS_USER_AGENT:=Fitnessapp (self-hosted)}"

command -v php >/dev/null || fail "php is not on PATH."

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
say "Using PHP $PHP_VERSION"

for ext in pdo_pgsql intl; do
  php -m | grep -qi "^$ext$" || fail "The PHP extension '$ext' is missing."
done

# A missing secret would otherwise be generated differently on every deploy,
# silently invalidating every existing session.
if [ -z "$APP_SECRET" ]; then
  say "APP_SECRET was not set - generating one. Save it; changing it later signs everyone out."
  APP_SECRET="$(php -r 'echo bin2hex(random_bytes(16));')"
fi

# Where the confirmation mail should send people. Defaults to this host.
if [ -z "$FRONTEND_VERIFY_URL" ]; then
  FRONTEND_VERIFY_URL="http://localhost/app/verify-email"
  say "FRONTEND_VERIFY_URL was not set - defaulting to $FRONTEND_VERIFY_URL"
fi

# --- Write the environment --------------------------------------------------
say "Writing $APP_DIR/.env.local"
cat > "$APP_DIR/.env.local" <<ENV
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$APP_SECRET
DATABASE_URL="$DATABASE_URL"
MAILER_DSN=$MAILER_DSN
MAILER_SENDER_ADDRESS=$MAILER_SENDER_ADDRESS
MAILER_SENDER_NAME="$MAILER_SENDER_NAME"
FRONTEND_VERIFY_URL=$FRONTEND_VERIFY_URL
OPEN_FOOD_FACTS_USER_AGENT="$OPEN_FOOD_FACTS_USER_AGENT"
ENV
chmod 600 "$APP_DIR/.env.local"

cd "$APP_DIR"

say "Warming the cache"
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod --no-debug

say "Applying database migrations"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod --no-debug

say "Creating the Messenger transport tables"
php bin/console messenger:setup-transports --env=prod --no-debug

# Only on a fresh database - re-running would duplicate the catalogue.
if [ "${LOAD_FIXTURES:-no}" = "yes" ]; then
  say "Loading the seed foods"
  php bin/console doctrine:fixtures:load --no-interaction --env=prod --no-debug
fi

cat <<'NEXT'

Installed.

Two things still have to be set up on this machine:

  1. A web server with its document root at app/public/. The SPA is served from
     app/public/app/ so it shares an origin with the API - no CORS needed.

  2. A worker for the confirmation mails, which are queued rather than sent
     during the request:

         php bin/console messenger:consume async --env=prod

     Run it under systemd or supervisor so it comes back after a restart.

NEXT
