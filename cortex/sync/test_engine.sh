#!/usr/bin/env bash
# End-to-end proof for the cortex MERGE engine (Phase B2a).
#
# Exercises sync.py against a DISPOSABLE target in a temp dir: dry-run, T0
# orphan-seed, OVERWRITE-to-worktree, the vendor branch, a baseline merge,
# idempotency, conflict surfacing, and deletion propagation. Reads the real
# pendulum repo read-only; never touches any real project.
#
# Run:  make sync-test-engine   (or: bash cortex/sync/test_engine.sh)
#
# NB: no `pipefail` — `git … | grep -q` SIGPIPEs git on early match, which
# would false-fail the pipe.
set -u

PEND="$(cd "$(dirname "$0")/../.." && pwd)"
SYNC="$PEND/cortex/sync/sync.py"
T="$(mktemp -d "${TMPDIR:-/tmp}/sync-engine-test.XXXXXX")"
WT="$(mktemp -d "${TMPDIR:-/tmp}/sync-engine-wt.XXXXXX")"; rm -rf "$WT"
fail=0
chk(){ if eval "$2"; then echo "  ok  $1"; else echo "  XX  $1"; fail=1; fi; }
cleanup(){ rm -rf "$T" "$WT"; }
trap cleanup EXIT

cd "$T"
git init -q
git config user.email "test@sync.local"
git config user.name "Sync Test"
printf 'PROJECT=ziontest\nAPP=app.ziontest\nAPI=api.ziontest\n' > .cortex-sync.conf
mkdir -p vitrum   # this target opted INTO vitrum but NOT codelumen/liquen (subproject scoping)
git add .cortex-sync.conf && git commit -qm "init target"

echo "== dry-run =="
# --force short-circuits the B2b source-green gate so this proof stays hermetic
# (no gh/network). The gate's own decision logic is covered by test_sync.py.
python3 "$SYNC" --to "$T" --dry-run --force > "$T/.dry.txt" 2>&1
chk "dry-run plans orphan vendor branch" "grep -q 'create (orphan)' '$T/.dry.txt'"
chk "dry-run shows a name-mapped path"   "grep -q 'ziontest/' '$T/.dry.txt'"
chk "dry-run wrote no branch"            "! git show-ref --verify --quiet refs/heads/pendulum-upstream"
chk "dry-run wrote no .claude"           "! test -e '$T/.claude'"

echo "== real T0 sync =="
python3 "$SYNC" --to "$T" --yes --force > "$T/.t0.txt" 2>&1
chk "pendulum-upstream branch created"        "git show-ref --verify --quiet refs/heads/pendulum-upstream"
chk "OVERWRITE applied to worktree (.claude)" "test -d '$T/.claude'"
chk "OVERWRITE applied to worktree (cortex)"  "test -d '$T/cortex'"
chk "OVERWRITE engine landed VERBATIM (not specialized)" \
  "diff -q '$PEND/cortex/sync/sync.py' '$T/cortex/sync/sync.py' >/dev/null"
chk "OVERWRITE engine rewrite table intact in target" \
  "grep -q '(\"celer\", app)' '$T/cortex/sync/sync.py'"
chk "OVERWRITE manifest.tsv IS specialized (path tokens for local --audit)" \
  "grep -q 'app.ziontest' '$T/cortex/sync/manifest.tsv'"
# An OVERWRITE path carries the project word too — `battoni-explain-pendulum.md` is the one that
# does. Its NAME follows its content, like everywhere else, and the pendulum-named copy does not
# survive beside it: two commands, the stale one holding the old text, is the failure this guards.
chk "OVERWRITE path name-mapped (battoni-explain-<project>)" \
  "test -f '$T/.claude/commands/battoni-explain-ziontest.md'"
chk "its .cursor mirror name-mapped too" \
  "test -f '$T/.cursor/rules/battoni-explain-ziontest.mdc'"
chk "no pendulum-named copy left beside it" \
  "! test -f '$T/.claude/commands/battoni-explain-pendulum.md' && ! test -f '$T/.cursor/rules/battoni-explain-pendulum.mdc'"
chk "the renamed command reads about the project, not about pendulum" \
  "grep -qi 'ziontest' '$T/.claude/commands/battoni-explain-ziontest.md'"
# MERGE code is not on main yet — only scaffolded LOCAL stubs may exist under app.ziontest;
# the merged content (README and friends) arrives via the vendor-branch merge below.
chk "MERGE code not on main yet"              "! test -e '$T/app.ziontest/README.md'"
chk "vendor branch holds mapped app.ziontest" "git ls-tree -r --name-only pendulum-upstream | grep -q '^app.ziontest/'"
chk "vendor branch holds mapped api.ziontest" "git ls-tree -r --name-only pendulum-upstream | grep -q '^api.ziontest/'"
chk "vendor branch has NO celer/ path"        "! ( git ls-tree -r --name-only pendulum-upstream | grep -q '^celer/' )"
chk "vendor branch excludes OVERWRITE"        "! ( git ls-tree -r --name-only pendulum-upstream | grep -q '^.claude/' )"

# Every job id in the TRANSFORMED workflow must still be a legal GitHub job id.
# `celer:`/`arcus:` as ids became `app.ziontest:`/`api.ziontest:` here, and a dot is illegal:
# GitHub refuses the whole workflow, so the run reports failure with zero jobs and no logs.
# Asserted on the vendor branch rather than on specialize()'s output, so it covers the real
# pipeline — and phrased as the GRAMMAR, not as the word `celer`, so it catches the next one.
git show pendulum-upstream:.github/workflows/ci.yml > "$T/.ci-transformed.yml" 2>/dev/null || true
chk "transformed ci.yml exists on the vendor branch" "test -s '$T/.ci-transformed.yml'"
chk "every transformed job id is a legal job id" \
  "python3 -c \"
import re,sys
text=open('$T/.ci-transformed.yml').read()
ids=re.findall(r'^  ([^\s:#]+):', text.split(chr(10)+'jobs:'+chr(10),1)[1], re.M)
sys.exit(0 if ids and all(re.match(r'^[A-Za-z_][A-Za-z0-9_-]*$', i) for i in ids) else 1)
\""
chk "opted-IN sub-project synced (vitrum)"    "git ls-tree -r --name-only pendulum-upstream | grep -q '^vitrum/'"
chk "opted-OUT sub-project skipped (codelumen)" "! ( git ls-tree -r --name-only pendulum-upstream | grep -q '^codelumen/' )"
chk "opted-OUT sub-project skipped (liquen)"  "! ( git ls-tree -r --name-only pendulum-upstream | grep -q '^liquen/' )"
# Core/local stubs: LOCAL extension points are scaffolded into the worktree if absent, so the
# synced core's static imports (routes.ts -> ./routes.local, etc.) resolve and the build holds.
chk "LOCAL stub scaffolded (routes.local.ts)" "test -f '$T/app.ziontest/src/libraries/router/routes.local.ts'"
chk "scaffolded stub has expected content"    "grep -q 'localRoutes' '$T/app.ziontest/src/libraries/router/routes.local.ts'"
chk "scaffolded stub name-mapped (no celer)"  "! grep -q 'celer' '$T/app.ziontest/src/libraries/router/routes.local.ts'"

echo "== T0 baseline merge =="
git add -A && git commit -qm "T0 overwrite layer"
git merge --allow-unrelated-histories -q -m "T0 baseline" pendulum-upstream
chk "merge brought app.ziontest into main" "test -d '$T/app.ziontest'"
chk "merge brought api.ziontest into main" "test -d '$T/api.ziontest'"
chk "content transformed in README"        "grep -q 'app.ziontest' '$T/app.ziontest/README.md'"
chk "no literal celer/ dir on main"        "! test -d '$T/celer'"
chk "lock files stayed LOCAL"              "! ( git ls-tree -r --name-only pendulum-upstream | grep -q 'package-lock.json' )"

echo "== post-merge test gate (--advance) =="
# Stub `make` via PATH so the suite is deterministic and offline; the gate must key off its exit.
MKSTUB="$(mktemp -d)"
rm -f "$T/.cortex-version"
printf '#!/bin/sh\nexit 1\n' > "$MKSTUB/make"; chmod +x "$MKSTUB/make"   # RED suite
PATH="$MKSTUB:$PATH" python3 "$SYNC" --advance --to "$T" > "$T/.adv-red.txt" 2>&1
chk "advance refuses on red tests"          "grep -qi 'TEST GATE RED' '$T/.adv-red.txt'"
chk "advance did NOT write .cortex-version"  "! test -f '$T/.cortex-version'"
printf '#!/bin/sh\nexit 0\n' > "$MKSTUB/make"   # GREEN suite
PATH="$MKSTUB:$PATH" python3 "$SYNC" --advance --to "$T" > "$T/.adv-green.txt" 2>&1
VSHA="$(git -C "$T" log -1 --format=%B pendulum-upstream | grep -oE 'pendulum@[0-9a-f]+' | cut -d@ -f2)"
chk "advance writes .cortex-version on green" "test -f '$T/.cortex-version'"
chk "advanced version == merged vendor sha"   "grep -q \"$VSHA\" '$T/.cortex-version'"
rm -rf "$MKSTUB"; rm -f "$T/.cortex-version"

echo "== triage (B2c) =="
# Fresh sync: every unit's based-on == pendulum version == project version -> all unchanged.
python3 "$SYNC" --triage --to "$T" > "$T/.triage0.txt" 2>&1
chk "fresh sync: 0 diverged"     "grep -qE 'diverged +0' '$T/.triage0.txt'"
chk "fresh sync: 0 clean-update" "grep -qE 'clean-update +0' '$T/.triage0.txt'"
# Drift two units: one pristine-but-old (clean-update), one locally edited (diverged).
setver(){ python3 - "$1" "$2" "$3" <<'PY'
import sys, re
path, based, ver = sys.argv[1:4]
t = open(path).read()
t = re.sub(r'(?m)^version: .*$', f'version: {ver}', t, count=1)
t = re.sub(r'(?m)^based-on: .*$', f'based-on: {based}', t, count=1)
open(path, 'w').write(t)
PY
}
setver "$T/app.ziontest/src/modules/User/views/Users/RULES.md" 0.0.1 0.0.1   # clean-update
setver "$T/app.ziontest/src/components/atoms/AFormError/RULES.md" 0.0.1 9.9.9 # diverged (ALogo is LOCAL now)
python3 "$SYNC" --triage --to "$T" > "$T/.triage1.txt" 2>&1
chk "drift: UsersView -> clean-update" "grep -q 'clean-update.*Users' '$T/.triage1.txt'"
chk "drift: AFormError -> diverged"    "grep -q 'diverged.*AFormError' '$T/.triage1.txt'"
chk "drift: clean-update shows a CHANGELOG excerpt" "grep -q '##' '$T/.triage1.txt'"

echo "== idempotent re-run =="
python3 "$SYNC" --to "$T" --yes --force > "$T/.idem.txt" 2>&1
chk "re-run makes no new vendor commit" "grep -q 'already at pendulum' '$T/.idem.txt'"

echo "== B2b source-green gate blocks when CI can't be verified (no --force) =="
# Deterministic + offline: a gh stub that fails makes the gate resolve to UNKNOWN,
# so a no-force sync must refuse before writing anything.
GHSTUB="$(mktemp -d)"; printf '#!/bin/sh\nexit 1\n' > "$GHSTUB/gh"; chmod +x "$GHSTUB/gh"
PATH="$GHSTUB:$PATH" python3 "$SYNC" --to "$T" --yes > "$T/.gate.txt" 2>&1
gate_rc=$?
rm -rf "$GHSTUB"
chk "gate blocks (nonzero exit) when source CI is unverifiable" "[ $gate_rc -ne 0 ]"
chk "gate prints a refusal message"                            "grep -q 'Refusing to sync' '$T/.gate.txt'"

echo "== conflict surfacing (simulated upstream advance vs local edit) =="
printf '\nLOCAL PROJECT EDIT\n' >> "$T/app.ziontest/README.md"
printf '\nlocal-target:\n\t@echo project\n' >> "$T/Makefile"   # project extends a JUNCTION file
git commit -q -am "local: customise README + Makefile"
git worktree add -q "$WT" pendulum-upstream
printf '# app.ziontest UPSTREAM CHANGE\n' > "$WT/app.ziontest/README.md"
printf '\nupstream-target:\n\t@echo upstream\n' >> "$WT/Makefile"
git -C "$WT" commit -q -am "upstream: change README + Makefile"
git worktree remove --force "$WT"
git merge pendulum-upstream > "$T/.conflict.txt" 2>&1
chk "git merge reports a conflict"        "grep -qi 'conflict' '$T/.conflict.txt'"
chk "conflict markers present in file"    "grep -q '<<<<<<<' '$T/app.ziontest/README.md'"

echo "== T0 auto-resolve by mode (Phase C) =="
python3 "$SYNC" --resolve-t0 --to "$T" > "$T/.resolve.txt" 2>&1
git diff --name-only --diff-filter=U > "$T/.unresolved.txt"
chk "resolve-t0 took upstream for MERGE file"   "grep -q 'UPSTREAM CHANGE' '$T/app.ziontest/README.md'"
chk "resolve-t0 cleared MERGE conflict markers" "! grep -q '<<<<<<<' '$T/app.ziontest/README.md'"
# Junction guardrail: a route/nav/locale/seeder/config file is NEVER auto-reverted to upstream.
chk "resolve-t0 held junction (Makefile) for manual merge" "grep -qx 'Makefile' '$T/.unresolved.txt'"
chk "resolve-t0 warns about junction files"     "grep -qi 'junction' '$T/.resolve.txt'"
chk "junction keeps both sides (markers intact)" "grep -q '<<<<<<<' '$T/Makefile'"
git merge --abort 2>/dev/null || git reset --hard -q HEAD

echo "== deletion propagation =="
python3 - "$T" "$PEND" <<'PY' > "$T/.del.txt" 2>&1
import sys
from pathlib import Path
sys.path.insert(0, str(Path(sys.argv[2]) / "cortex" / "sync"))
from sync import load_conf, load_manifest, files_by_mode, build_merge_plan, generate_upstream, run
target, pend = Path(sys.argv[1]), Path(sys.argv[2])
conf, rules = load_conf(target), load_manifest(pend)
plan = build_merge_plan(pend, files_by_mode(pend, rules)["MERGE"], conf)
reduced = [t for t in plan if t[0] != "app.ziontest/README.md"]
generate_upstream(target, reduced, run(["git", "rev-parse", "HEAD"], pend), dry_run=False)
PY
chk "removed file drops off the vendor branch" "! ( git ls-tree -r --name-only pendulum-upstream | grep -q '^app.ziontest/README.md$' )"
chk "other files remain on the vendor branch"  "git ls-tree -r --name-only pendulum-upstream | grep -q '^api.ziontest/'"

echo
if [ $fail -eq 0 ]; then echo "ENGINE PROOF PASS"; else echo "ENGINE PROOF FAIL"; fi
exit $fail
