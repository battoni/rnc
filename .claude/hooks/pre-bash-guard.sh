#!/bin/bash
# Blocks dangerous bash operations before they run.
# Claude Code sends the PreToolUse payload as JSON on stdin (the command is tool_input.command).
# Exit code 2 = block the command and show the stderr message to Claude.
# Anything this script cannot read lets the command through (fail-open): a guard that breaks
# every Bash call is worse than no guard. Tests: python3 cortex/hooks/test_claude_hooks.py

HOOK_INPUT="$(cat)" python3 - <<'PY'
import json
import os
import re
import sys

try:
    payload = json.loads(os.environ.get('HOOK_INPUT', ''))
    command = payload['tool_input']['command']
except (ValueError, KeyError, TypeError):
    sys.exit(0)

if not isinstance(command, str):
    sys.exit(0)

END = r'(?=\s|$|[;&|)"\'])'

# Each entry: (what the message names, pattern). `rm -rf /` must not match `rm -rf /tmp/x`,
# so the root and home forms only match when nothing but `/`, `/*` or `~/` follows.
BLOCKED = [
    ('rm -rf /', r'\brm\s+-[a-zA-Z]*[rR][a-zA-Z]*\s+(--no-preserve-root\s+)?/\*?' + END),
    ('rm -rf ~', r'\brm\s+-[a-zA-Z]*[rR][a-zA-Z]*\s+~/?\*?' + END),
    ('drop table', r'(?i)\bdrop\s+table\b'),
    ('git push --force', r'\bgit\b[^;&|]*\bpush\b[^;&|]*\s(--force\S*|-[a-zA-Z]*f[a-zA-Z]*)' + END),
    ('> /dev/sda', r'>\s*/dev/sd[a-z]'),
]

for label, pattern in BLOCKED:
    if re.search(pattern, command):
        print(f'BLOCKED: Dangerous command pattern detected: {label}', file=sys.stderr)
        sys.exit(2)

sys.exit(0)
PY
