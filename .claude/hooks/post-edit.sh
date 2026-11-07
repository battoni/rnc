#!/bin/bash
# Runs the appropriate formatter after Claude edits a file — the ONE file it just edited.
# Claude Code sends the PostToolUse payload as JSON on stdin; tool_input.file_path is absolute,
# so it is made relative to the repo root before it is matched.
#   api.rnc/**/*.php          → Pint on that file
#   app.rnc/src/**/*.{vue,ts} → ESLint --fix on that file (never `npm run lint`, which is `eslint .`)
# Fail-open: a payload it cannot read, or a formatter that fails, never blocks the session.
# CLAUDE_HOOK_DRY_RUN=1 prints the command instead of running it. Tests: cortex/hooks/test_claude_hooks.py

ROOT="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/../.." && pwd)}"

FILE=$(HOOK_INPUT="$(cat)" python3 -c '
import json, os
try:
    path = json.loads(os.environ.get("HOOK_INPUT", ""))["tool_input"]["file_path"]
except (ValueError, KeyError, TypeError):
    path = ""
print(path if isinstance(path, str) else "")
' 2>/dev/null)

if [[ -z "$FILE" || ! -f "$FILE" ]]; then exit 0; fi

REL="${FILE#"$ROOT"/}"
if [[ "$REL" == "$FILE" && "$FILE" == /* ]]; then exit 0; fi

run() {
  local dir="$1"
  shift

  if [[ -n "$CLAUDE_HOOK_DRY_RUN" ]]; then
    echo "(cd $dir && $*)"
    return 0
  fi

  (cd "$ROOT/$dir" && "$@") >/dev/null 2>&1
  return 0
}

case "$REL" in
  api.rnc/*.php)
    run api.rnc vendor/bin/pint --format agent "${REL#api.rnc/}"
    ;;
  app.rnc/src/*.vue | app.rnc/src/*.ts)
    run app.rnc npx --no-install eslint --fix "${REL#app.rnc/}"
    ;;
esac

exit 0
