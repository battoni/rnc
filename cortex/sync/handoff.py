#!/usr/bin/env python3
"""Produce a clean client-handoff copy of the repo.

Strips every Pendulum AI artifact — `.claude/`, `.cursor/`, `cortex/`,
`codelumen/`, `plans/`, all `CLAUDE.md` files, and the tooling targets in the
Makefile / CI — while KEEPING the component `RULES.md` docs (verified free of
AI-plumbing references, so they stand alone as plain documentation).

Safety:
  * Works on a COPY exported from the COMMITTED tree (`git archive HEAD`), so it
    never touches your working repo and never includes uncommitted or ignored
    files (no node_modules, no .env).
  * Gives the result a FRESH git history — the stripped tooling is not
    recoverable from `git log`.

Usage:
  handoff.py [--out DIR] [--projects "celer arcus"] [--force] [--verify]

  --out       output directory (default: ../pendulum-handoff)
  --projects  space/comma list of app sub-projects to KEEP
              (default: every app project — arcus celer vitrum liquen)
  --force     overwrite --out if it already exists
  --verify    run the kept projects' build to prove the copy stands alone
"""
from __future__ import annotations

import argparse
import re
import shutil
import subprocess
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]

APP_PROJECTS = ['arcus', 'celer', 'vitrum', 'liquen']
REMOVE_DIRS = ['.claude', '.cursor', 'cortex', 'codelumen', 'plans']
REMOVE_FILES = ['CLAUDE.md', 'CLAUDE.local.md', 'CLAUDE.local.md.example']
MAKE_DROP_TARGETS = {
    'docs-install', 'docs', 'docs-gen', 'docs-build', 'docs-preview',
    'sync-mirror', 'sync-mirror-check', 'handoff', 'sync-audit', 'sync-brand-audit',
    'sync-test', 'sync-test-backport', 'sync-test-engine', 'sync-test-init', 'check-cortex',
}
TARGET_RE = re.compile(r'^([A-Za-z0-9_-]+)\s*:(?!=)')


# HELPERS

def export_snapshot(out: Path) -> None:
    out.mkdir(parents=True, exist_ok=True)
    archive = subprocess.run(
        ['git', '-C', str(REPO), 'archive', 'HEAD'],
        check=True, capture_output=True,
    )
    subprocess.run(['tar', '-x', '-C', str(out)], input=archive.stdout, check=True)


def remove(path: Path) -> None:
    if path.is_symlink() or path.is_file():
        path.unlink(missing_ok=True)
        return

    if path.is_dir():
        shutil.rmtree(path, ignore_errors=True)


def drop_prerequisites(line: str) -> str:
    """Remove dropped targets from a kept rule's prerequisite list (the `## help` stays)."""
    head, separator, help_text = line.partition('##')
    target, _, prerequisites = head.partition(':')
    kept = [name for name in prerequisites.split() if name not in MAKE_DROP_TARGETS]
    rebuilt = f"{target}: {' '.join(kept)}".rstrip()
    return f'{rebuilt} {separator}{help_text}' if separator else rebuilt


def strip_make_targets(text: str) -> str:
    def clean_phony(match: re.Match) -> str:
        names = [name for name in match.group(1).split() if name not in MAKE_DROP_TARGETS]
        return '.PHONY: ' + ' '.join(names)

    text = re.sub(r'\.PHONY:\s*(.*)', clean_phony, text, count=1)

    lines = text.split('\n')
    result: list[str] = []
    comments: list[str] = []
    index = 0

    while index < len(lines):
        line = lines[index]

        if line.startswith('#'):
            comments.append(line)
            index += 1
            continue

        match = TARGET_RE.match(line)

        if match and match.group(1) in MAKE_DROP_TARGETS:
            index += 1
            while index < len(lines) and lines[index].startswith('\t'):
                index += 1
            if index < len(lines) and lines[index].strip() == '':
                index += 1
            comments = []
            continue

        result.extend(comments)
        comments = []
        result.append(drop_prerequisites(line) if match else line)
        index += 1

    result.extend(comments)
    return '\n'.join(result)


def strip_ci_jobs(text: str, names: list[str]) -> str:
    if not names:
        return text

    job_re = re.compile(r'^  (' + '|'.join(re.escape(name) for name in names) + r'):')
    lines = text.split('\n')
    result: list[str] = []
    index = 0

    while index < len(lines):
        if job_re.match(lines[index]):
            index += 1
            while index < len(lines) and not re.match(r'^  \S', lines[index]):
                index += 1
            continue

        result.append(lines[index])
        index += 1

    return '\n'.join(result)


def edit_in_place(path: Path, transform) -> None:
    if not path.exists():
        return

    path.write_text(transform(path.read_text(encoding='utf-8')), encoding='utf-8')


def run(cmd: list[str], cwd: Path) -> int:
    print(f'  $ {" ".join(cmd)}  (in {cwd.name})')
    return subprocess.run(cmd, cwd=cwd).returncode


# MAIN

def main() -> None:
    parser = argparse.ArgumentParser(description='Eject a clean client-handoff copy.')
    parser.add_argument('--out', default=str(REPO.parent / 'pendulum-handoff'))
    parser.add_argument('--projects', default='')
    parser.add_argument('--force', action='store_true')
    parser.add_argument('--verify', action='store_true')
    args = parser.parse_args()

    out = Path(args.out).resolve()
    keep = [name for name in re.split(r'[\s,]+', args.projects) if name] or APP_PROJECTS
    unknown = [name for name in keep if name not in APP_PROJECTS]

    if unknown:
        sys.exit(f'Unknown project(s): {", ".join(unknown)}. Choose from {", ".join(APP_PROJECTS)}.')

    if out == REPO or REPO in out.parents:
        sys.exit('Refusing to write inside the source repo — choose an --out outside it.')

    if out.exists():
        if not args.force:
            sys.exit(f'{out} already exists. Pass --force to overwrite.')
        shutil.rmtree(out)

    print(f'Exporting committed snapshot → {out}')
    export_snapshot(out)

    print('Stripping AI tooling...')
    for name in REMOVE_DIRS:
        remove(out / name)
    for name in REMOVE_FILES:
        remove(out / name)
    for claude in out.rglob('CLAUDE*.md'):
        remove(claude)
    for cursor in list(out.rglob('.cursor')) + list(out.rglob('.claude')):
        remove(cursor)

    dropped_projects = [name for name in APP_PROJECTS if name not in keep]
    for name in dropped_projects:
        remove(out / name)

    edit_in_place(out / 'Makefile', strip_make_targets)
    edit_in_place(
        out / '.github' / 'workflows' / 'ci.yml',
        lambda text: strip_ci_jobs(text, ['mirror-sync', *dropped_projects]),
    )

    print('Re-initialising git history...')
    run(['git', 'init', '-q'], out)
    run(['git', 'add', '-A'], out)
    run(['git', 'commit', '-q', '-m', 'Initial commit'], out)

    rules_kept = sum(1 for _ in out.rglob('RULES.md'))

    print('\n' + '=' * 60)
    print('Handoff copy ready:', out)
    print('  kept projects   :', ', '.join(keep))
    if dropped_projects:
        print('  dropped projects:', ', '.join(dropped_projects))
    print('  RULES.md kept   :', rules_kept)
    print('  git history     : fresh (1 commit, no tooling in history)')
    print('=' * 60)

    if args.verify and (out / 'celer').exists():
        print('\nVerifying celer builds stand-alone...')
        ok = run(['npm', 'ci'], out / 'celer') == 0 and run(['npm', 'run', 'build'], out / 'celer') == 0
        print('  celer build:', 'OK' if ok else 'FAILED — review the copy')

    print('\nManual review before delivery:')
    print('  - README.md still references cortex/codelumen — trim if present.')
    print('  - Confirm no secrets/.env crept in (snapshot excludes ignored files).')
    print('  - Legal: the contract should retain framework IP and license the app.')


if __name__ == '__main__':
    main()
