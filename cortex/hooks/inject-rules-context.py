#!/usr/bin/env python3
"""Collect RULES.md chain for a app.rnc Vue/TS file and format hook output for Claude or Cursor."""

from __future__ import annotations

import json
import os
import sys
from pathlib import Path

MAX_CONTEXT_CHARS = 9000
SUPPORTED_SUFFIXES = {".vue", ".ts", ".tsx"}
CELER_SRC_MARKER = "app.rnc/src"


def read_hook_input() -> dict:
    raw = sys.stdin.read().strip()
    if raw:
        try:
            return json.loads(raw)
        except json.JSONDecodeError:
            pass

    env_input = os.environ.get("CLAUDE_TOOL_INPUT", "").strip()
    if env_input:
        try:
            return json.loads(env_input)
        except json.JSONDecodeError:
            pass

    return {}


def extract_file_path(payload: dict) -> str | None:
    candidates: list[object] = [
        payload.get("file_path"),
        payload.get("path"),
    ]

    tool_input = payload.get("tool_input")
    if isinstance(tool_input, dict):
        candidates.extend(
            [
                tool_input.get("file_path"),
                tool_input.get("path"),
            ]
        )

    arguments = payload.get("arguments")
    if isinstance(arguments, dict):
        candidates.extend(
            [
                arguments.get("file_path"),
                arguments.get("path"),
            ]
        )

    for candidate in candidates:
        if isinstance(candidate, str) and candidate.strip():
            return candidate.strip()

    return None


def is_supported_src_file(file_path: str) -> bool:
    normalized = file_path.replace("\\", "/")
    if CELER_SRC_MARKER not in normalized:
        return False

    return Path(normalized).suffix.lower() in SUPPORTED_SUFFIXES


def collect_rules_files(file_path: str) -> list[Path]:
    target = Path(file_path)
    current = target.parent if target.suffix else target

    if not current.exists():
        current = target.parent

    collected: list[Path] = []
    seen: set[Path] = set()

    while True:
        rules_file = (current / "RULES.md").resolve()
        if rules_file.exists() and rules_file not in seen:
            collected.append(rules_file)
            seen.add(rules_file)

        if current.name == "src":
            break

        parent = current.parent
        if parent == current:
            break

        current = parent

    return collected


def build_context(rules_files: list[Path]) -> str:
    if not rules_files:
        return ""

    sections: list[str] = [
        "Folder-specific RULES.md chain (closest folder first — higher priority):",
    ]

    for rules_file in rules_files:
        try:
            content = rules_file.read_text(encoding="utf-8").strip()
        except OSError:
            continue

        if not content:
            continue

        sections.append(f"\n---\n## {rules_file}\n\n{content}")

    context = "\n".join(sections).strip()
    if len(context) <= MAX_CONTEXT_CHARS:
        return context

    truncated = context[:MAX_CONTEXT_CHARS].rstrip()
    omitted = [str(path) for path in rules_files]
    return (
        f"{truncated}\n\n"
        "[RULES context truncated at 9000 characters. "
        f"Read these files for full rules: {', '.join(omitted)}]"
    )


def format_claude_output(context: str) -> str:
    if not context:
        return "{}"

    return json.dumps(
        {
            "hookSpecificOutput": {
                "hookEventName": "PreToolUse",
                "additionalContext": context,
            }
        }
    )


def format_cursor_output(context: str) -> str:
    if not context:
        return json.dumps({"permission": "allow"})

    return json.dumps(
        {
            "permission": "allow",
            "agent_message": context,
        }
    )


def main() -> int:
    hook_target = os.environ.get("RULES_HOOK_TARGET", "claude").lower()
    payload = read_hook_input()
    file_path = extract_file_path(payload)

    if not file_path or not is_supported_src_file(file_path):
        print("{}" if hook_target == "claude" else json.dumps({"permission": "allow"}))
        return 0

    rules_files = collect_rules_files(file_path)
    context = build_context(rules_files)

    if hook_target == "cursor":
        print(format_cursor_output(context))
        return 0

    print(format_claude_output(context))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
