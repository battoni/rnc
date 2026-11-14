# Deploy runbook — Hub da Reforma (rnc-4, F7)

Readiness only: nothing here has been deployed. This is what a server needs so the platform runs
every firm from one deploy — `<root>` (the platform firm, slug `hub`, the product site and the
operator's console) and `<slug>.<root>` (one accounting firm each: its public site and its back
office). `<root>` stands for the production domain (`PLATFORM_ROOT_DOMAIN`); `*.<root>` for every
firm. `tests/Feature/EnvInventoryTest.php` fails when an env key the API config reads, or a `VITE_*`
key of `app.rnc/.env.example`, is missing from this file.

## Shape

- One API (`api.rnc`, Laravel, PHP 8.4, MySQL 8) and one SPA build (`app.rnc`, `npm run build` →
  `dist/`), served by **one vhost for `<root>` and `*.<root>`**. Each host serves the SPA, and the
  same host proxies `/api` and `/storage` to the API: same-origin (spec D4), so every firm host keeps
  its own session cookie and no request crosses an origin.
- The SPA reads its firm from its own host (`<slug>.<root>` → `X-Firm: <slug>`; the root and `www`
  send none and get the platform firm). The API never trusts a header over a signed-in person's firm.
- A firm is a row, not a deploy: `php artisan firm:create <slug> --name="…" --manager=<username>`
  (or the operator's console at `https://<root>/plataforma/escritorios`). Its host works as soon as
  the row exists — the wildcard DNS and certificate already cover it.

## DNS and TLS

- DNS: `A`/`AAAA` for `<root>` and for `*.<root>` (or a `CNAME *.<root> → <root>`), both to the
  server or its load balancer. `www.<root>` is covered by the wildcard and is the platform firm too.
- TLS: one certificate for `<root>` **and** `*.<root>`. A wildcard needs the ACME **DNS-01**
  challenge (HTTP-01 cannot issue it): e.g. `certbot certonly --dns-<provider> -d <root> -d '*.<root>'`,
  renewed by the provider plugin's timer. A wildcard covers one level only: `a.b.<root>` is not a firm.
- The reserved slugs (`config/platform.php` → `reserved_slugs`: www, api, app, admin, hub, mail, …)
  are never a firm's address; a DNS record for one of them that points elsewhere does not collide.

## Web server (one vhost, every host)

Nginx, with the SPA's `dist/` as the root; `/api/` goes to PHP-FPM with the API's front controller,
and `/storage/` is the API's public disk (the firm logos, plan AM10) — on every host, because the SPA
asks `/storage/…` on its own host (`toStorageAssetPath`, F4d):

```nginx
server {
    listen 443 ssl http2;
    server_name <root> *.<root>;

    ssl_certificate     /etc/letsencrypt/live/<root>/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/<root>/privkey.pem;

    root  /srv/rnc/app.rnc/dist;
    index index.html;
    client_max_body_size 2m;            # the logo is ≤ 512 KB; the NF-e import sends XML files

    location ^~ /api/ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /srv/rnc/api.rnc/public/index.php;
        fastcgi_param DOCUMENT_ROOT   /srv/rnc/api.rnc/public;
        fastcgi_pass  unix:/run/php/php8.4-fpm.sock;
    }

    location ^~ /storage/ {
        alias /srv/rnc/api.rnc/storage/app/public/;
        access_log off;
        expires 7d;
    }

    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files $uri $uri/ /index.html;   # the SPA's history routes (/painel, /plataforma/…)
    }
}

server {
    listen 80;
    server_name <root> *.<root>;
    return 301 https://$host$request_uri;
}
```

- `php artisan storage:link` is not needed with the `alias` above; run it anyway so `public/storage`
  exists for anything that reads it through the API's own public folder.
- The API keeps answering `logo_url` as `APP_URL/storage/…`; the SPA rewrites it to a path on its own
  host, so the vhost's `/storage/` is what serves it.
- `/api/health` is the probe (below). Laravel's own `/up` is not proxied (it lives outside `/api`).

## Sessions, Sanctum and CORS per host

The session is a host-only cookie: signing in on `alvorada.<root>` never signs anyone in on
`franco.<root>` or the root.

| Key | Production value | Why |
| --- | --- | --- |
| `SESSION_DOMAIN` | `null` (unset) | host-only cookie; a `.<root>` value would share one session across every firm |
| `SESSION_DRIVER` | `database` | the `sessions` table; survives a deploy and serves every PHP worker |
| `SESSION_SECURE_COOKIE` | `true` | HTTPS only |
| `SESSION_SAME_SITE` | `lax` | same-origin calls only |
| `SESSION_LIFETIME` | `120` | minutes; a view-as left open is closed (`expirou`) at the operator's next sign-in |
| `SESSION_COOKIE`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_CONNECTION`, `SESSION_TABLE`, `SESSION_STORE`, `SESSION_EXPIRE_ON_CLOSE`, `SESSION_HTTP_ONLY`, `SESSION_PARTITIONED_COOKIE` | defaults | |
| `SANCTUM_STATEFUL_DOMAINS` | `<root>,*.<root>` | the SPA's hosts (no port in production) |
| `SANCTUM_TOKEN_PREFIX` | empty | no API tokens are issued |
| `CORS_ALLOWED_ORIGINS` | `https://<root>` | same-origin needs no CORS; a stray cross-origin caller gets none |
| `CORS_ALLOWED_ORIGINS_PATTERNS` | `^https://[a-z0-9-]+\.<root escaped>$` | the firm hosts, should a tool ever call cross-origin |

## Behind a proxy or load balancer

`TRUSTED_PROXIES` (`config/trustedproxy.php`) lists the addresses or CIDR ranges of whatever sits in
front of PHP-FPM (the load balancer, a CDN's ranges). With it, `$request->ip()` is the client's,
read from `X-Forwarded-For` — the view-as audit row's `ip_address` (Platform, C18) and every throttle
key (the beta signup's 5/min, the sign-in). Unset, no proxy is trusted and the header is ignored:
right when Nginx and PHP share the box and Nginx is the only peer (then `127.0.0.1` is the peer and
`TRUSTED_PROXIES=127.0.0.1` makes Nginx's `X-Forwarded-For` count — Nginx must set it:
`fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;` only when a load balancer is in
front). Never `*` while anything else can reach PHP directly.

## Health

`GET https://<root>/api/health` → 200 `{"data":{"status":"ok","db":"ok"}}`; 503
`{"data":{"status":"down","db":"down"}}` when the database does not answer. No session, no firm, no
sign-in, on any host — point the load balancer and the uptime check at it (`HealthTest`).

## Queue worker and scheduler

- **Queue** — `QUEUE_CONNECTION=database` (the `jobs` table). Queued mail: `BookingConfirmed`,
  `MeetingReminder`, `ReferralSent`, `BetaSignupMail`, `DailyBriefingMail`. One worker under
  Supervisor or systemd, restarted on every deploy:

  ```ini
  [program:rnc-queue]
  command=php /srv/rnc/api.rnc/artisan queue:work --sleep=3 --tries=3 --max-time=3600
  autorestart=true
  user=www-data
  stopwaitsecs=60
  ```

  On deploy: `php artisan queue:restart`. Failed jobs land in `failed_jobs` (`QUEUE_FAILED_DRIVER`
  default `database-uuids`): `php artisan queue:failed`, `queue:retry all`.
- **Scheduler** — one cron line, every minute:

  ```cron
  * * * * * cd /srv/rnc/api.rnc && php artisan schedule:run >> /dev/null 2>&1
  ```

  It runs (`routes/console.php`):

  | Command | When | What |
  | --- | --- | --- |
  | `appointments:remind` | hourly | the 24-hour meeting reminder, once per meeting (`reminded_at`) |
  | `assistant:notify-expiring` | 07:00 America/Sao_Paulo | a reviewer hears at day 83 that a note's review ends at day 90, every firm in turn |
  | `briefing:send` | 07:00 America/Sao_Paulo | the manager's daily briefing e-mail, business days only, every firm |

  Each command walks every firm itself; a queued mail carries its firm's brand in its DTO (a worker
  has no current firm).

## Per-firm outgoing mail (D11)

- One sending domain for every firm: **From** = the platform's verified `MAIL_FROM_ADDRESS` under
  the **firm's name** (or the sub-brand's), **Reply-To** = the firm's mailbox (`firms.mailbox`, set
  by its manager in Configurações). The band, colours and logo are the firm's (`MailBrand`).
- `MAIL_MAILER=resend` + `RESEND_API_KEY`; `MAIL_FROM_ADDRESS` on a domain verified in Resend and
  listed in `MAIL_VERIFIED_DOMAINS` (`MailSenderTest` refuses an unverified pair — Resend accepts and
  never delivers). `MAIL_FROM_NAME` is the product's name for mail that concerns no firm.
- SPF, DKIM and DMARC for the sending domain as Resend lists them. Per-firm sending domains are out
  of scope (D11: DNS per firm, later).
- A firm without a mailbox sends with no Reply-To (`MailBrand::replyTo` answers none): a reply goes to
  the From address — set every firm's mailbox before its first mail.

## Backups

- MySQL: nightly `mysqldump --single-transaction --routines --triggers rnc | gzip` to off-box storage,
  kept 30 days; restore-tested once a month into a scratch database.
- Files: `api.rnc/storage/app/public/` (the firm logos, `firms/<id>/logo-*.ext`) and
  `storage/app/` (imports), nightly with the dump.
- `.env` of both apps in the secrets manager, not in the backup set.

## First deploy, then every deploy

First:

1. `composer install --no-dev --optimize-autoloader`; `.env` from the inventory below;
   `php artisan key:generate`.
2. `php artisan migrate --force` then the reference seeders only:
   `php artisan db:seed --force` — in production it creates the firms Franco and `hub`, the roles,
   the super-admin and the sector pages; every demo seeder (the demo firm, the team, `gestao.hub`, the
   specialists' invented hours, the invented leads, partners, clients, brands and channel rows) returns
   without writing anywhere but `local` and `testing` — `staging` included (`DemoEnvironment`). A re-seed
   never rewrites an existing firm row: the console's edits stay. Set `SUPERADMIN_PASSWORD` and `SEEDER_PWD` to strong values first.
3. `npm ci && npm run build` in `app.rnc` with the SPA's production `.env` (below).
4. Queue worker and cron as above; `php artisan config:cache route:cache view:cache event:cache`.
5. `curl -fsS https://<root>/api/health` and `https://franco.<root>/api/health` → `ok`.

Every later deploy: `git pull`, `composer install --no-dev -o`, `php artisan migrate --force`,
`npm ci && npm run build`, `php artisan optimize`, `php artisan queue:restart`.

**The folded migrations end at the first deploy.** Until then (spec D8, arcus §3) a column went into
its table's `create_*` migration and the database was rebuilt. From the first production `migrate`
on, a published table changes only through a new altering migration (`add_<column>_to_<table>_table`,
`php artisan make:migration`), `migrate:fresh` never runs outside a local database, and
`php artisan migrate:status` is read before publishing. Record that rule in the project's root
`CLAUDE.md` the day of the first deploy (arcus §3: a launched project writes its own rule there).

## Database

MySQL 8, database `rnc`, `utf8mb4` / `utf8mb4_unicode_ci`, a user with rights on `rnc` only.

**Proven on MySQL 8.0.41 (rnc-4 F7b, 14/11/2026)**, server in its default strict `sql_mode`
(`ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,…`): `migrate:fresh --seed` with the local seeders (Franco, the
platform firm, Alvorada, 2629 + 2287 demo NF-e) runs clean, and the full Pest suite is green on MySQL as on
SQLite — **2399 Pest, all green on both**. What MySQL needed and SQLite never showed:

- an index name over 64 characters (`client_contracts`' five-column unique, SQLSTATE 1059) — now named;
  `SchemaIdentifierLengthTest` fails on any table, column, index or foreign key name over 64;
- `RoleService` read the unique-violation message by column (`roles.name`); MySQL names the index
  (`roles.roles_name_unique`), so a name race was a 500 and a slug collision never retried;
- tests only: MySQL re-orders a JSON object's keys and does not roll an auto-increment back between
  tests, and quotes with backticks — the tests compare JSON ignoring key order and ask for Franco's id.

W95 ("MySQL 8 not run") is retired: it held only because the root password was unknown; the owner
gave it and the commands below ran. Repeat them before a deploy that touches a migration:

```bash
# 1. the databases (asks for the root password)
mysql -uroot -p -e "CREATE DATABASE IF NOT EXISTS rnc_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS rnc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. the full suite on MySQL — the shell's variables win over phpunit.xml's <env> (none is forced).
#    The password comes from the environment, never from a file in the repo.
read -s DB_PASSWORD && export DB_PASSWORD
make test-mysql                          # DB_DATABASE defaults to rnc_test; DB_HOST/PORT/USERNAME to 127.0.0.1/3306/root

# 3. schema and seed on MySQL, without touching api.rnc/.env (it stays on SQLite)
cd api.rnc
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=rnc DB_USERNAME=root php artisan migrate:fresh --seed --force
```

Expect the SQLite count, every test green.

## Env inventory — API (`api.rnc/.env`)

Every key `api.rnc/config/*.php` reads. **Set** = must be set in production; the rest keep their
default unless the row says otherwise.

| Key | Production |
| --- | --- |
| `APP_NAME` | `api.rnc` (logs, cache and queue prefixes — not shown to people) |
| `APP_ENV` | `production` (the local-only seeders and the QA login-as refuse it) |
| `APP_KEY` | **Set** — `php artisan key:generate`; `APP_PREVIOUS_KEYS` keeps the old one during a rotation |
| `APP_PREVIOUS_KEYS` | empty, or the prior key while rotating |
| `APP_DEBUG` | `false` |
| `APP_URL` | **Set** — `https://<root>` (the base of `logo_url`; the SPA rewrites it to its own host) |
| `APP_TIMEZONE` | `America/Sao_Paulo` |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | `en`, `en`, `en_US` (the SPA owns pt-BR) |
| `APP_MAINTENANCE_DRIVER`, `APP_MAINTENANCE_STORE` | `file`, unset |
| `FRONTEND_URL` | **Set** — `https://<root>`; a firm's site in mail is `<slug>.` + this host (`MailBrand`) |
| `PLATFORM_NAME` | `Hub da Reforma` |
| `PLATFORM_ROOT_DOMAIN` | **Set** — `<root>` |
| `DEFAULT_FIRM_SLUG` | `hub` (the root's firm) |
| `TRUSTED_PROXIES` | **Set** when a proxy or load balancer is in front (section above) |
| `ASSISTANT_DRIVER` | `curated` (any other value stops the boot) |
| `SUPPORT_NAMES` | the sign-in screen's support names, comma-separated |
| `LOGIN_CODE_SENDER` | `log` until a real phone sender is bound (`log` refuses in production — keep the phone sign-in off) |
| `LOGIN_CODE_EXPOSE` | `false` |
| `AUTH_GUARD`, `AUTH_MODEL`, `AUTH_PASSWORD_BROKER`, `AUTH_PASSWORD_RESET_TOKEN_TABLE`, `AUTH_PASSWORD_TIMEOUT` | defaults |
| `REGISTRATION_ENABLED` | `false` (self-registration closed, D21) |
| `REGISTRATION_VERIFY_EMAIL`, `REGISTRATION_CODE_TTL_MINUTES`, `REGISTRATION_NEW_USER_STATUS` | defaults (inert while closed) |
| `SUPERADMIN_PASSWORD`, `SEEDER_PWD` | **Set** — strong, before the first `db:seed` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | **Set** — the MySQL 8 server, `rnc` |
| `DB_URL`, `DB_SOCKET`, `DB_CHARSET`, `DB_COLLATION`, `DB_FOREIGN_KEYS`, `DB_SSLMODE`, `DB_ENCRYPT`, `DB_TRUST_SERVER_CERTIFICATE`, `MYSQL_ATTR_SSL_CA` | defaults; `MYSQL_ATTR_SSL_CA` when the database is remote over TLS |
| `CACHE_STORE` | `database` |
| `CACHE_PREFIX`, `DB_CACHE_CONNECTION`, `DB_CACHE_TABLE`, `DB_CACHE_LOCK_CONNECTION`, `DB_CACHE_LOCK_TABLE` | defaults |
| `QUEUE_CONNECTION` | `database` |
| `DB_QUEUE`, `DB_QUEUE_CONNECTION`, `DB_QUEUE_TABLE`, `DB_QUEUE_RETRY_AFTER`, `QUEUE_FAILED_DRIVER` | defaults |
| `SESSION_*`, `SANCTUM_*`, `CORS_*` | the table in "Sessions, Sanctum and CORS per host" |
| `FILESYSTEM_DISK` | `local` (the logos use the `public` disk by name) |
| `IDEA_ATTACHMENT_DISK` | `public` |
| `MAIL_MAILER` | `resend` |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | **Set** — a verified address; the product's name |
| `RESEND_API_KEY` | **Set** — one key for this project |
| `MAIL_SCHEME`, `MAIL_URL`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_EHLO_DOMAIN`, `MAIL_LOG_CHANNEL`, `MAIL_SENDMAIL_PATH` | unused with Resend |
| `POSTMARK_API_KEY`, `POSTMARK_MESSAGE_STREAM_ID` | unused |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL` | `stack`, `daily`, `warning` |
| `LOG_DAILY_DAYS` | `14` |
| `LOG_DEPRECATIONS_CHANNEL`, `LOG_DEPRECATIONS_TRACE` | `null`, `false` |
| `LOG_SLACK_WEBHOOK_URL`, `LOG_SLACK_USERNAME`, `LOG_SLACK_EMOJI`, `LOG_PAPERTRAIL_HANDLER`, `PAPERTRAIL_URL`, `PAPERTRAIL_PORT`, `LOG_STDERR_FORMATTER`, `LOG_SYSLOG_FACILITY` | unused unless a log sink is added |
| `SLACK_BOT_USER_OAUTH_TOKEN`, `SLACK_BOT_USER_DEFAULT_CHANNEL` | unused |
| `REDIS_CLIENT`, `REDIS_URL`, `REDIS_HOST`, `REDIS_USERNAME`, `REDIS_PASSWORD`, `REDIS_PORT`, `REDIS_DB`, `REDIS_CACHE_DB`, `REDIS_PREFIX`, `REDIS_CLUSTER`, `REDIS_PERSISTENT`, `REDIS_MAX_RETRIES`, `REDIS_BACKOFF_ALGORITHM`, `REDIS_BACKOFF_BASE`, `REDIS_BACKOFF_CAP`, `REDIS_CACHE_CONNECTION`, `REDIS_CACHE_LOCK_CONNECTION`, `REDIS_QUEUE`, `REDIS_QUEUE_CONNECTION`, `REDIS_QUEUE_RETRY_AFTER` | unused (cache, queue and session are on the database); set them only if Redis is introduced |
| `MEMCACHED_HOST`, `MEMCACHED_PORT`, `MEMCACHED_USERNAME`, `MEMCACHED_PASSWORD`, `MEMCACHED_PERSISTENT_ID` | unused |
| `DYNAMODB_CACHE_TABLE`, `DYNAMODB_ENDPOINT` | unused |
| `BEANSTALKD_QUEUE`, `BEANSTALKD_QUEUE_HOST`, `BEANSTALKD_QUEUE_RETRY_AFTER`, `SQS_PREFIX`, `SQS_QUEUE`, `SQS_SUFFIX` | unused |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | unused (files on local disk); set them to move the `public` disk to S3 — then the vhost's `/storage/` becomes a proxy to the bucket |

Read outside `config/` but in `.env.example`: `MAIL_VERIFIED_DOMAINS` (the sending domains Resend
verified — `MailSenderTest` reads it), `BCRYPT_ROUNDS` (default 12).

## Env inventory — SPA (`app.rnc/.env.production`, read at build time)

| Key | Production |
| --- | --- |
| `VITE_API_URL` | `/api` — same-origin on every host (D4); never an absolute URL |
| `VITE_ROOT_DOMAIN` | **Set** — `<root>`: `<slug>.<root>` is a firm, the root and `www` the platform. `vite build` refuses a production build without it (`app.rnc/buildEnv.ts`) |
| `VITE_APP_URL` | `https://<root>` |
| `VITE_DEMO_FIRM_SLUG` | the demo firm the product landing links (`alvorada` is local only — empty hides the link until a production demo firm exists) |
| `VITE_ACTIVE_THEME` | `hub` (the neutral ground; each firm's colours come from its row) |
| `VITE_DEFAULT_LANGUAGE` | `pt-BR` |
| `VITE_LOGIN_FLOW` | `username` |
| `VITE_DOCS_URL` | empty (no docs portal) |
| `VITE_SENTRY`, `VITE_SENTRY_PROPAGATION_TARGETS`, `VITE_SENTRY_PROJECT_NAME`, `VITE_SENTRY_AUTH_TOKEN` | the Sentry project, if used; targets `['<root>', /\.<root>$/]`; `SENTRY_AUTH_TOKEN` only in the build's environment, for source maps |
| `VITE_LOCAL_SSL_KEY`, `VITE_LOCAL_SSL_CERT` | empty (dev server only) |
| `VITE_API_PROXY_TARGET` | not used in production — the dev/test server's `/api` and `/storage` proxy target (default `http://localhost:8000`; a worktree on 8001 sets it) |

## Open before go-live

- The production `<root>`, the Resend domain and the server are the owner's to choose.
- A production demo firm (`firm:create`) if the landing should link one (`VITE_DEMO_FIRM_SLUG`).
