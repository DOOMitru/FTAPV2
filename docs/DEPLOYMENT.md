# Deploying to DreamHost

GitHub Actions runs the suite on every push and pull request, and deploys to
DreamHost when `main` passes. The pipeline is one file:
`.github/workflows/ci.yml`.

Nothing below is automatic the first time. This is the one-time setup, and the
deploy will fail in a specific, readable way until each piece is in place.

## What the pipeline does

```
push to main
   └── test  (SQLite and MySQL, in parallel)
         └── deploy   ← only if BOTH legs pass
               ├── composer install --no-dev, npm run build   (on the runner)
               ├── artisan down
               ├── rsync the release
               ├── artisan migrate --force
               ├── config/route/view cache, storage:link
               ├── artisan mail:check      ← fails the deploy on a bad APP_URL
               └── artisan up              ← runs even if a step above failed
```

The build happens on the runner, not on the server. Shared hosting has no
reliable Composer and not much memory to resolve dependencies with, so `vendor/`
and `public/build` are compiled in Actions and shipped. **That is why the PHP
version in the workflow has to match the server's** -- those files are compiled
against it.

The version lives in one place, `PHP_VERSION` at the top of the workflow, and is
set to **8.5** -- the server offers `/usr/local/php85` and local development runs
8.5.4, so dev, CI and production are one interpreter rather than three.

Three things have to agree, and this is the whole requirement:

| Where | Value |
|---|---|
| `PHP_VERSION` in `.github/workflows/ci.yml` | `8.5` |
| DreamHost panel → Websites → the domain → PHP version | 8.5 |
| `DEPLOY_PHP` (or the workflow's default) | `/usr/local/php85/bin/php` |

Before the first deploy, confirm DreamHost's 8.5 build has what the app needs.
Laravel and its dependencies require these:

```
/usr/local/php85/bin/php -m | grep -ciE '^(ctype|dom|fileinfo|filter|hash|iconv|json|libxml|mbstring|openssl|pcre|session|tokenizer|xml|xmlwriter|phar)$'
```

That should print **16**. Add `pdo_mysql` to the list once the database is set
up -- without it the site cannot connect at all. If anything is missing, drop to
`/usr/local/php84` and change the three values above together; nothing in the
app requires 8.5 specifically.

## 1. GitHub: the `production` environment

Settings → Environments → New environment → name it `production`. Adding a
required reviewer there later turns this into a deploy-on-approval pipeline
without touching the workflow.

Add these as **environment secrets**:

| Secret | What it is | Example |
|---|---|---|
| `DEPLOY_SSH_KEY` | The **private** half of a key pair made for this. Whole file, including the BEGIN/END lines. | `-----BEGIN OPENSSH PRIVATE KEY-----…` |
| `DEPLOY_HOST` | The DreamHost server hostname | `iad1-shared-e1-01.dreamhost.com` |
| `DEPLOY_USER` | The shell user | `ftapdeploy` |
| `DEPLOY_PATH` | Absolute path to the app on the server | `/home/ftapdeploy/apps/ftap` |

And optionally an environment **variable** (not a secret):

| Variable | Default if unset |
|---|---|
| `DEPLOY_PHP` | `/usr/local/php85/bin/php` |

Check the real path first -- `ssh you@host 'ls /usr/local/php*/bin/php'` -- because
plain `php` on DreamHost's shell is often an older version than the one serving
your site, and a deploy that migrates with the wrong binary is a bad afternoon.

Generate the key pair with no passphrase (Actions cannot type one):

```
ssh-keygen -t ed25519 -C "github-actions-ftap" -f ftap_deploy -N ""
```

`ftap_deploy` goes in `DEPLOY_SSH_KEY`. `ftap_deploy.pub` goes on the server:

```
ssh you@host
mkdir -p ~/.ssh && chmod 700 ~/.ssh
cat >> ~/.ssh/authorized_keys    # paste the .pub contents, then Ctrl-D
chmod 600 ~/.ssh/authorized_keys
```

## 2. DreamHost: the site

**Enable shell access.** Panel → Manage Users → the user → "Shell user (SSH)".

**Set PHP 8.5.** Panel → Websites → the domain → PHP version. It must match the
workflow, for the reason above.

**Point the domain at `public/`.** Laravel serves from `public/`, and everything
above it must not be reachable. In the panel, set the domain's **Web Directory**
to `apps/ftap/public` while the app itself lives at `~/apps/ftap`. If the
document root ends up one level too high, `.env` becomes a downloadable file.
Check that `https://yourdomain/.env` returns 404 after the first deploy.

**Create the MySQL database.** Panel → MySQL Databases. Note the hostname
DreamHost gives you -- it is a `mysql.yourdomain.com` style name, not
`localhost`.

## 3. The server's `.env`

The deploy never touches `.env`; it is excluded from the sync precisely so a
release cannot overwrite production configuration. Create it once by hand.

The deploy creates the directory itself, so the order that works is: let the
first deploy run, then fill in `.env`, then re-run it. The first attempt stops
with a message telling you exactly that.

```
ssh you@host
cd /home/you/apps/ftap          # absolute path, the same one in DEPLOY_PATH
cp .env.example .env
/usr/local/php85/bin/php artisan key:generate
nano .env
```

**`DEPLOY_PATH` must be absolute.** A leading `~` does not expand inside the
quoted commands the deploy runs, and the resulting error is unhelpful -- so the
workflow checks it and fails early with a clear message instead.

The values that matter:

```
APP_ENV=production
APP_DEBUG=false            # true here leaks stack traces and config to visitors
APP_URL=https://yourdomain # NOT localhost -- mail:check fails the deploy on it

DB_CONNECTION=mysql
DB_HOST=mysql.yourdomain.com
DB_DATABASE=…
DB_USERNAME=…
DB_PASSWORD=…

MAIL_MAILER=smtp           # the rest as they are locally
MAIL_HOST=smtp.dreamhost.com
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=info@firsttoactpoker.com
LEAGUE_CONTACT_EMAIL=…
```

`APP_KEY` differs from the local one, and that is correct -- it is the key
encrypting production sessions and cookies. Losing it logs everyone out; leaking
it is worse.

Make the two writable directories writable:

```
chmod -R 775 storage bootstrap/cache
```

## 4. The data

Local development is SQLite; production is MySQL. The two are not file
compatible, so the 235 accounts do not travel by copying anything.

**If the database is being rebuilt from scratch** (which was the plan as of
2026-09-05), the first deploy's `migrate --force` builds the schema and you
re-import:

```
/usr/local/php85/bin/php artisan users:import users.csv \
    --approved --verified --admin=dumitru.campan@gmail.com
```

Then re-enter venues, seasons, sponsors and the points structure through the
dashboard. Everything else -- tournaments, results, venue points -- accumulates
from play.

**If the local data must come across instead**, dump it as inserts and load it
into MySQL; SQLite's dump syntax needs a pass to be MySQL-compatible, so budget
an hour and check `venue_points.season_id` survived, since that column is
recent.

Either way: **run `php artisan mail:check` on the server before inviting
anybody.** It is the gate that catches a production `APP_URL` still pointing
somewhere local, and the deploy runs it too.

## 5. The first deploy

Push to `main`. The first run will likely fail somewhere in section 2 or 3 --
that is expected, and the failure names the missing piece. The site is left
running: the `artisan up` step is marked `if: always()`, so a failed deploy
gives you a red build rather than a 503.

To watch it: Actions → the run → the `deploy` job.

## What is NOT automated, on purpose

- **Rollback.** Shared hosting has no atomic release swap, so this deploys in
  place. To roll back, revert the commit and push -- the pipeline redeploys the
  previous state. Migrations do not roll back with it; a bad migration needs a
  new migration.
- **Queue workers.** Nothing queues today (`QUEUE_CONNECTION=sync`). If that
  changes, shared hosting cannot hold a worker open and it becomes a cron
  entry running `queue:work --stop-when-empty`.
- **Scheduled tasks.** Nothing is scheduled today. If that changes, add one
  DreamHost cron entry running `artisan schedule:run` every minute.
