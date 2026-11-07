#!/usr/bin/env bash
# Proof for the first-run in-place transform (Phase B3, `sync.py --init`).
#
# Clones pendulum into a temp dir, copies in the WORKING-tree sync.py (so the test
# exercises uncommitted changes too), and transforms the clone into a fake project.
# Never touches the real pendulum repo.
#
# Run:  make sync-test-init   (or: bash cortex/sync/test_init.sh)
set -u

PEND="$(cd "$(dirname "$0")/../.." && pwd)"
C="$(mktemp -d "${TMPDIR:-/tmp}/sync-init-test.XXXXXX")"
fail=0
chk(){ if eval "$2"; then echo "  ok  $1"; else echo "  XX  $1"; fail=1; fi; }
trap 'rm -rf "$C"' EXIT

git clone -q "$PEND" "$C"
cp "$PEND/cortex/sync/sync.py" "$C/cortex/sync/sync.py"   # exercise the working engine
cd "$C"
git config user.email "test@sync.local"; git config user.name "Sync Test"
printf 'PROJECT=zion\nAPP=app.zion\nAPI=api.zion\n' > .cortex-sync.conf

echo "== guard: refuses on pendulum =="
printf 'PROJECT=pendulum\nAPP=app\nAPI=api\n' > .pendulum-conf
chk "refuses --init when PROJECT=pendulum" \
  "cp .pendulum-conf .cortex-sync.conf && ! python3 cortex/sync/sync.py --init >/dev/null 2>&1"
printf 'PROJECT=zion\nAPP=app.zion\nAPI=api.zion\n' > .cortex-sync.conf  # restore

echo "== dry-run =="
python3 cortex/sync/sync.py --init --dry-run > "$C/.dry.txt" 2>&1
chk "dry-run plans the renames" "grep -q 'celer/ -> app.zion/' '$C/.dry.txt'"
chk "dry-run wrote nothing (celer/ still there)" "test -d '$C/celer'"

echo "== apply =="
python3 cortex/sync/sync.py --init > "$C/.apply.txt" 2>&1
chk "celer -> app.zion"        "test -d '$C/app.zion'"
chk "arcus -> api.zion"        "test -d '$C/api.zion'"
chk "no celer/ left"           "! test -d '$C/celer'"
chk "no arcus/ left"           "! test -d '$C/arcus'"
chk "content transformed (README)"   "grep -q 'app.zion' '$C/app.zion/README.md'"
chk "no 'celer' left in vite.config" "! grep -q 'celer' '$C/app.zion/vite.config.ts'"
chk "rule filename tokens preserved" "test -f '$C/.cursor/rules/celer-01-vue-checklist.mdc'"

echo "== names follow the content (the zion first-run bug) =="
# A path is name-mapped by the same specialize() a sync runs on it. Before this, only the two
# folders moved: config/pendulum.php kept its name while its content said config('zion.…'), so
# every lookup answered null with no error, and the next sync wrote config/zion.php beside it.
chk "config/pendulum.php -> config/zion.php" \
  "test -f '$C/api.zion/config/zion.php' && ! test -f '$C/api.zion/config/pendulum.php'"
chk "the config name matches what the code asks for" \
  "test -f '$C/api.zion/config/zion.php' && grep -rq \"config('zion\" '$C/api.zion/app'"
chk "command renamed in .claude" \
  "test -f '$C/.claude/commands/battoni-explain-zion.md' && ! test -f '$C/.claude/commands/battoni-explain-pendulum.md'"
chk "its .cursor mirror renamed too" \
  "test -f '$C/.cursor/rules/battoni-explain-zion.mdc' && ! test -f '$C/.cursor/rules/battoni-explain-pendulum.mdc'"
chk "docs.pendulum/ moved too — a folder is a name as well" \
  "test ! -d '$C/docs.pendulum'"
chk "no tracked path still carries the source project word" \
  "! git -C '$C' ls-files | grep -v '^cortex/sync/' | grep -qi pendulum"
chk "dry-run listed a file rename, not only the folders" \
  "grep -q 'config/pendulum.php -> ' '$C/.dry.txt'"
chk "git recorded a rename, not a delete plus an add" \
  "git -C '$C' status --porcelain | grep -q '^R.*config/zion.php'"

echo "== engine self-exclusion (must not rewrite itself) =="
# NOTE: the engine legitimately MENTIONS the example project 'zion' in comments/docs, so
# 'grep zion' is not a transform signal. Pristine canaries: byte-identical source for the .py,
# and the surviving 'pendulum' source-name in the docs (a transform would rewrite it to 'zion').
chk "engine sync.py byte-identical to source (pristine)" \
  "diff -q '$PEND/cortex/sync/sync.py' '$C/cortex/sync/sync.py' >/dev/null"
chk "engine sync.py rewrite table intact (not self-mangled)" \
  "grep -q '(\"celer\", app)' '$C/cortex/sync/sync.py'"
chk "engine RUNBOOK keeps the 'pendulum' source name (not rewritten to project)" \
  "grep -q 'pendulum' '$C/cortex/sync/RUNBOOK.md'"
chk "manifest.tsv IS transformed (path tokens for local --audit)" \
  "grep -q 'app.zion' '$C/cortex/sync/manifest.tsv'"
chk "init reports the engine was left pristine" \
  "grep -q 'engine left pristine' '$C/.apply.txt'"

echo
if [ $fail -eq 0 ]; then echo "INIT PROOF PASS"; else echo "INIT PROOF FAIL"; fi
exit $fail
