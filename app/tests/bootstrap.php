<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Rebuild the test database from the current mapping before the suite runs.
//
// Migrations are replayed in CI against a scratch database to prove they work,
// but the suite itself builds the schema straight from the entities: a test
// failing because a migration was forgotten tells you nothing about the code
// under test, and every test would have to wait for the full migration history.
// The database itself, on a machine that has never run the suite before.
passthru(\sprintf(
    'APP_ENV=test php "%s/bin/console" doctrine:database:create --if-not-exists --quiet 2>&1',
    dirname(__DIR__),
), $databaseStatus);

if (0 !== $databaseStatus) {
    fwrite(\STDERR, "Could not reach the database. Is the container running? Try: docker compose up -d\n");

    exit(1);
}

passthru(\sprintf(
    'APP_ENV=test php "%s/bin/console" doctrine:schema:drop --full-database --force --quiet 2>&1',
    dirname(__DIR__),
), $dropStatus);

passthru(\sprintf(
    'APP_ENV=test php "%s/bin/console" doctrine:schema:create --quiet 2>&1',
    dirname(__DIR__),
), $createStatus);

if (0 !== $createStatus) {
    fwrite(\STDERR, "Could not create the test database schema. Is the database container running (docker compose up -d)?\n");

    exit(1);
}
