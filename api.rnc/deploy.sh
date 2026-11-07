#!/usr/bin/env bash
#
# Publish api.rnc on the droplet. Run as root, from the API directory.
#
# Adapted from api.lifeshifter/deploy.sh, which earned every step here the hard way on
# 2026-09-02, each omission looking like a different bug:
#
#   - no `composer install` → a class-not-found on the two endpoints that use a new package,
#     while every other route kept working, so the deploy looked fine.
#   - no cache rebuild → routes frozen from before the pull: a new route answers 405 naming
#     the methods of the `{id}` route that swallowed it.
#   - `artisan` or `composer` as root → files the web user cannot read, which took an API
#     fully down: `storage/framework/views` unwritable meant 500 on EVERY route, reported as
#     a `tempnam()` warning naming neither the directory nor the cause.
#
# Ordering is not cosmetic: dependencies before migrations (a migration may use a new
# package), ownership before the caches are written, caches before the reload that reads them.
#
# ─────────────────────────────────────────────────────────────────────────────────────────
# THIS SCRIPT DROPS THE DATABASE ON EVERY DEPLOY, AND THAT IS THE POINT HERE.
#
# `arcus-api-architecture` §3 forbids ALTER migrations: a table's `create_*` is its only
# description and it is edited until it is right. That rule only holds if the server actually
# re-reads the creates. A plain `migrate --force` runs PENDING migrations only, so editing a
# create that already ran leaves the column missing in production — the code reads `null` and
# the screen lies, with no error anywhere. Which is the exact failure the rule exists to stop.
#
# So the bootstrap rebuilds. It keeps no data: everything here is seeded, and the seeders are
# the source of the reference rows.
#
# **A project derived from rnc MUST NOT copy this line.** The moment a product has real
# users, `migrate:fresh` is a data-loss incident with a green check mark next to it. That is
# why this file is LOCAL in the sync manifest and never travels — see the note there.
# ─────────────────────────────────────────────────────────────────────────────────────────
set -euo pipefail

# Every path below is relative — `public/storage`, `vendor storage bootstrap/cache`,
# `composer install`, `php artisan`. So the script anchors itself to its own directory
# rather than trusting the caller's, which is what makes it safe to invoke as
# `bash api.rnc/deploy.sh` from the repository root, or by hand from anywhere.
cd "$(dirname "${BASH_SOURCE[0]}")"

BRANCH="${DEPLOY_BRANCH:-main}"
WEB_USER="${DEPLOY_WEB_USER:-www-data}"
FPM_SERVICE="${DEPLOY_FPM_SERVICE:-php8.4-fpm}"
# The exact commit to publish. The workflow passes the one its Pest job approved: the tip of
# the branch at deploy time can be a LATER commit whose own run is still queued behind this
# one, and publishing it would put untested code on the server. Unset (a deploy by hand),
# the script publishes the branch tip, as it always did.
TARGET_SHA="${DEPLOY_SHA:-}"

echo "==> Fetching ${BRANCH}"
git fetch --quiet origin "${BRANCH}"

if [ -z "${TARGET_SHA}" ]; then
    git reset --hard "origin/${BRANCH}"
else
    # Only a commit that is on the branch: a SHA from anywhere else is refused, not published.
    if ! git merge-base --is-ancestor "${TARGET_SHA}" "origin/${BRANCH}"; then
        echo "✗ ${TARGET_SHA} is not on origin/${BRANCH} — refusing to publish it." >&2
        exit 1
    fi

    git reset --hard "${TARGET_SHA}"
fi

echo "==> Installing PHP dependencies"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> Discarding the cached config"
# Before the seeders run, and it is not housekeeping. The seeders read
# `config('seeding.*')` (`config/seeding.php`), and from the SECOND deploy onwards a cached
# config file always exists, because the run before this one ended with `config:cache`.
# That file is the PREVIOUS release's config built from the PREVIOUS `.env`: a
# `SUPERADMIN_PASSWORD` rotated since then would not reach the superadmin, and a config file
# this pull added would be missing from it altogether — on a deploy whose every line printed
# success. Clearing makes the seeders read this tree's config and the current `.env`.
# Rebuilt at the bottom, after the tree is in place.
sudo -u "${WEB_USER}" php artisan config:clear

echo "==> Rebuilding the database from the create migrations"
# `--force` is what lets this run outside an interactive terminal; without it the command
# refuses in production and the deploy stops. See the header for why this is `fresh`.
sudo -u "${WEB_USER}" php artisan migrate:fresh --seed --force

echo "==> Linking public storage"
# Asked only when missing: `storage:link` treats an existing link as an error, and an error
# printed on every healthy deploy teaches you to ignore errors.
#
# Created as root, NOT as ${WEB_USER}: the link is born inside public/, which belongs to root,
# so the web user cannot write there. On a fresh droplet the failure aborts the whole deploy
# AFTER `composer install` and BEFORE the chown and the cache rebuild, which leaves the server
# half-published — that is how it failed on api.impressao on 2026-09-04.
if [ ! -e public/storage ]; then
    php artisan storage:link
    chown -h "${WEB_USER}":"${WEB_USER}" public/storage
fi

echo "==> Handing the tree back to ${WEB_USER}"
chown -R "${WEB_USER}":"${WEB_USER}" vendor storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "==> Rebuilding caches"
sudo -u "${WEB_USER}" php artisan config:cache
sudo -u "${WEB_USER}" php artisan route:cache
sudo -u "${WEB_USER}" php artisan view:cache

echo "==> Reloading ${FPM_SERVICE}"
systemctl reload "${FPM_SERVICE}"

echo "==> Health check"
# Through the public hostname, NOT 127.0.0.1: this droplet serves several projects, so an
# address with no Host header lands on the default vhost and answers 404 about somebody
# else's site.
#
# Laravel's health route renders a Blade view, so a 200 here also proves the view cache is
# writable — the exact thing whose absence took an API fully down.
HEALTH_URL="${DEPLOY_HEALTH_URL:-https://api.rnc.battoni.dev/up}"
curl --fail --silent --show-error --location --max-time 20 -o /dev/null "${HEALTH_URL}"
echo "==> Deployed $(git rev-parse --short HEAD)"
