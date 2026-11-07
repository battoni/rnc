#!/bin/bash
# Injects folder RULES.md chain before Cursor Edit/Write tool calls.
# Receives preToolUse JSON on stdin.

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export RULES_HOOK_TARGET=cursor
exec python3 "$ROOT/cortex/hooks/inject-rules-context.py"
