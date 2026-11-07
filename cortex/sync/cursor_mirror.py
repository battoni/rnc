#!/usr/bin/env python3
"""Keep .cursor/rules mirrors identical to their canonical .claude sources.

`.claude` is canonical. For every rule (`.claude/rules/*.md`), command
(`.claude/commands/*.md`) and skill that has a Cursor counterpart
(`.claude/skills/<name>/SKILL.md` → `.cursor/rules/<name>.mdc`), the matching
`.cursor/rules/*.mdc` must have an IDENTICAL body — only the `.mdc` frontmatter differs (Cursor uses a string
`globs:` and an "Invoke with /name." description).

Usage:
  cursor_mirror.py            rewrite mirrors so their bodies match .claude
  cursor_mirror.py --check    exit 1 if any mirror is out of date (no writes)

Wired into `make sync-mirror` / `make sync-mirror-check`; the check runs as
part of `make pre-commit` and in CI so the two formats can never drift.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
FRONTMATTER = re.compile(r'^---\n.*?\n---\n', re.S)


def kebab(name: str) -> str:
    return re.sub(r'(?<!^)(?=[A-Z])', '-', name).lower()


def strip_frontmatter(text: str) -> str:
    return FRONTMATTER.sub('', text, count=1)


def frontmatter_of(text: str) -> str | None:
    match = FRONTMATTER.match(text)
    return match.group(0) if match else None


def mirror_path(root: Path, name: str) -> Path:
    exact = root / '.cursor' / 'rules' / f'{name}.mdc'
    kebabed = root / '.cursor' / 'rules' / f'{kebab(name)}.mdc'

    if exact.exists():
        return exact

    if kebabed.exists():
        return kebabed

    return exact


def generated_frontmatter(name: str, claude_text: str) -> str:
    summary = name
    for line in claude_text.splitlines():
        stripped = line.strip()
        if stripped and not stripped.startswith('#'):
            summary = stripped
            break

    return f'---\ndescription: {summary} Invoke with /{name}.\nalwaysApply: false\n---\n'


def desired_mirror(root: Path, claude_file: Path, name: str) -> tuple[Path, str]:
    claude_text = claude_file.read_text(encoding='utf-8')
    body = strip_frontmatter(claude_text)
    target = mirror_path(root, name)

    if target.exists():
        frontmatter = frontmatter_of(target.read_text(encoding='utf-8'))
    else:
        frontmatter = None

    if frontmatter is None:
        frontmatter = generated_frontmatter(name, claude_text)

    return target, frontmatter + body


def sources(root: Path) -> list[tuple[Path, str]]:
    rules = sorted((root / '.claude' / 'rules').glob('*.md'))
    commands = sorted((root / '.claude' / 'commands').glob('*.md'))

    # A skill is mirrored only where a `.cursor/rules/<skill>.mdc` already exists: not every skill
    # has a Cursor counterpart, and the check must not invent one. Where it exists, its body is
    # the SKILL.md body, like any other mirror.
    skills = [
        (path, path.parent.name)
        for path in sorted((root / '.claude' / 'skills').glob('*/SKILL.md'))
        if mirror_path(root, path.parent.name).exists()
    ]

    return [(path, path.stem) for path in rules + commands] + skills


def run(root: Path, check: bool) -> int:
    drift: list[Path] = []

    for claude_file, name in sources(root):
        target, want = desired_mirror(root, claude_file, name)
        have = target.read_text(encoding='utf-8') if target.exists() else None

        if have == want:
            continue

        drift.append(target.relative_to(root))

        if not check:
            target.write_text(want, encoding='utf-8')

    if check:
        if drift:
            print('Out-of-sync .cursor mirrors:')
            for path in drift:
                print(f'  - {path}')
            print('\nRun `make sync-mirror` to regenerate them from .claude.')
            return 1

        print('All .cursor mirrors are in sync with .claude.')
        return 0

    print(f'Synced {len(drift)} mirror(s).' if drift else 'Mirrors already in sync.')
    return 0


def main() -> None:
    sys.exit(run(ROOT, check='--check' in sys.argv))


if __name__ == '__main__':
    main()
