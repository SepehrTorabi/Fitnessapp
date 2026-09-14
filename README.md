# Fitnessapp

A calorie and macro diary: log what you eat, log what you burn, and see whether
the day landed inside the budget your body data implies.

- **Backend** — Symfony 8.1, JSON API, Doctrine ORM, PostgreSQL
- **Frontend** — Vue 3 (Composition API), TypeScript, Vite, Pinia
- **Tests** — PHPUnit, 128 tests
- **CI** — GitHub Actions: lint, static analysis, tests, and a packaged release

---

## Running it locally

You need PHP 8.4+ (with `pdo_pgsql` and `intl`), Composer, Node 22+ and Docker.

```bash
# 1. Database and a local mail catcher
cd app && docker compose up -d

# 2. Backend
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console messenger:setup-transports
php bin/console doctrine:fixtures:load --no-interaction   # 30 staple foods
symfony server:start -d --port=8000

# 3. The mail worker, in its own terminal.
#    Confirmation mails are queued, so without this nothing arrives.
php bin/console messenger:consume async -vv

# 4. Frontend, in another terminal
cd ../frontend && npm install && npm run dev
```

| What | Where |
|---|---|
| The app | http://localhost:5173 |
| API | http://localhost:8000/api |
| Mail inbox (Mailpit) | http://localhost:8025 |

Register an account, then open the confirmation mail in Mailpit and click the
link — outside production no mail actually leaves the machine.

## Tests

```bash
cd app
vendor/bin/phpunit                                  # 128 tests
vendor/bin/phpstan analyse --memory-limit=1G        # level 6, clean
```

The suite rebuilds the test schema from the entity mapping on the way in and
wraps every test in a transaction that is rolled back afterwards, so tests are
independent and the database needs no resetting between runs. The database
container has to be up.

## How it fits together

```
frontend/   Vue SPA. In development it proxies /api to :8000, so the browser
            sees one origin and the session cookie needs no CORS.
app/        Symfony. Serves JSON only; Twig is used for the e-mail templates.
deploy/     Install script and sample nginx / systemd units for a release.
```

### Some decisions worth knowing about

**Diary entries store a snapshot of their nutrition values.** They are not
recomputed from the food on read. If you correct a food's calories next month,
the days you already logged must not silently change underneath you.

**Body data is split in two.** `UserProfile` holds what rarely changes — date
of birth, height, sex, activity level, goal. `BodyMeasurement` is a series, one
row per weigh-in. That is what lets last Tuesday keep the calorie target that
actually applied on Tuesday.

**Calories use Katch-McArdle when body fat is known**, falling back to
Mifflin-St Jeor. Two people of the same height and weight burn different amounts
if one carries far more muscle, so a formula based on lean mass beats one based
on total weight whenever the figure is available.

**Amounts that cannot honestly be converted are refused.** "One slice" means
nothing in general, so it resolves only against a portion weight defined on that
specific food. The resolver throws rather than inventing a number, because a
guessed weight would quietly corrupt every total it feeds into.

**Unknown is not zero.** A food with no fibre figure stores `null`, not `0.0`.
Summing a day keeps the known part rather than collapsing to unknown.

**Food lookup goes through an interface.** `ExternalFoodProviderInterface` is
implemented against Open Food Facts, which also covers barcode scanning. Search
results are offered, not saved — a food row is written only when you pick one.
Swapping in a different provider is one line in `config/services.yaml`.

## Routes

Routes live in [`app/config/routes/api.yaml`](app/config/routes/api.yaml) rather
than in `#[Route]` attributes, so the whole surface of the API reads as one file.

```bash
php bin/console debug:router
```

## Configuration

Copy anything you need to override into `app/.env.local` — it is gitignored.

| Variable | What it does |
|---|---|
| `DATABASE_URL` | PostgreSQL connection |
| `MAILER_DSN` | `smtp://localhost:1025` locally (Mailpit) |
| `FRONTEND_VERIFY_URL` | Where the confirmation link sends people |
| `MAILER_SENDER_ADDRESS` / `MAILER_SENDER_NAME` | From-address of the mails |
| `OPEN_FOOD_FACTS_USER_AGENT` | Open Food Facts asks callers to identify themselves |

## CI and releases

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on every push:

1. **backend-lint** — `composer validate`, YAML/Twig/container lint, Doctrine
   mapping check, PHPStan level 6
2. **backend-tests** — PHPUnit against a real PostgreSQL service container, then
   replays the migrations on an empty database to prove they still build the
   schema on their own
3. **frontend** — `vue-tsc` type check and production build
4. **package** — only if all three pass: production dependencies, built SPA and
   an install script, tarred up with a SHA-256 checksum and uploaded as a
   build artifact

Tagging a commit `v1.2.3` publishes the same archive as a GitHub release.

### Installing a release

```bash
tar -xzf fitnessapp-v1.2.3.tar.gz
cd fitnessapp-v1.2.3
DATABASE_URL='postgresql://user:pass@host:5432/fitnessapp?serverVersion=16' \
FRONTEND_VERIFY_URL='https://example.com/app/verify-email' \
  ./install.sh
```

The script writes `.env.local`, warms the cache, runs the migrations and sets up
the Messenger tables. Two things remain machine-specific and are not in the
package: a web server (see `deploy/nginx.conf.example`) and the mail worker
(see `deploy/fitnessapp-worker.service.example`).

## Not built yet

- JWT authentication as a second firewall alongside the session login
- Editing an existing diary entry (today: delete and re-add)
- Password reset
- Weight-history chart on the profile page
- Frontend component tests
