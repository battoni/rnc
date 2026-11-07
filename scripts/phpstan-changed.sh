#!/usr/bin/env bash
# Run PHPStan (larastan) on api.rnc PHP files changed on this branch (+ uncommitted) — touched code only.
# Usage: scripts/phpstan-changed.sh [base-branch]   (default: main)
#
# PHPStan comes with `composer install`: larastan/larastan is in api.rnc's require-dev.
# api.rnc/phpstan.neon.dist ships with this repo (CI runs it over the whole of app/); copy it to
# api.rnc/phpstan.neon to tune it locally.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE="${1:-main}"
ARCUS="$ROOT/api.rnc"
PHPSTAN="$ARCUS/vendor/bin/phpstan"

if [ ! -x "$PHPSTAN" ]; then
  echo "✗ PHPStan not installed — larastan is in require-dev, so install api.rnc's dependencies:" >&2
  echo "    (cd api.rnc && composer install)" >&2
  exit 1
fi

# Prefer a real config; fall back to the shipped starter.
CONFIG=""
for candidate in "$ARCUS/phpstan.neon" "$ARCUS/phpstan.neon.dist"; do
  [ -f "$candidate" ] && { CONFIG="$candidate"; break; }
done
if [ -z "$CONFIG" ]; then
  echo "✗ No phpstan.neon(.dist) in api.rnc/. Copy the starter or create one." >&2
  exit 1
fi

cd "$ROOT"

# Tests are left out. A Pest test is a closure whose `$this` is bound at runtime, so PHPStan reads every
# `$this->getJson()` and every global helper from `tests/Pest.php` as undefined — 133 findings on one
# branch, none of them a defect, burying the one that was. The official `pestphp/pest-plugin-phpstan`
# teaches PHPStan those bindings but needs Pest 5 (api.rnc is on Pest 4): add it with that upgrade and
# drop this filter. `phpstan.neon.dist` already scopes a full run to `app/`.
CHANGED="$( {
  git diff --name-only --diff-filter=d "$BASE"...HEAD -- api.rnc
  git diff --name-only --diff-filter=d -- api.rnc
  git diff --cached --name-only --diff-filter=d -- api.rnc
  git ls-files --others --exclude-standard -- api.rnc
} 2>/dev/null | grep -E '\.php$' | grep -v '^api.rnc/tests/' | sort -u || true)"

if [ -z "$CHANGED" ]; then
  echo "PHPStan: no changed api.rnc PHP files vs $BASE — nothing to analyse."
  exit 0
fi

# Run from the Laravel root so larastan can bootstrap the app; absolute file paths work regardless.
cd "$ARCUS"
echo "$CHANGED" | sed "s|^|$ROOT/|" \
  | xargs "$PHPSTAN" analyse -c "$CONFIG" --no-progress --memory-limit=1G
