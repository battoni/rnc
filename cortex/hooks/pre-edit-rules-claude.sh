#!/bin/bash
# Injects folder RULES.md chain before Claude Edit/Write tool calls.
# Receives PreToolUse JSON on stdin.

ROOT="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/../.." && pwd)}"
export RULES_HOOK_TARGET=claude
exec python3 "$ROOT/cortex/hooks/inject-rules-context.py"
