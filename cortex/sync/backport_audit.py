#!/usr/bin/env python3
"""Auditing the pre-flight: what the port had to do that it never warned about.

Step 12 of BACKPORT.md, and the only honest measure of the tool. It does not ask the
pre-flight what it thinks it covers; it asks the WORK what it demanded.

How it runs: it creates a worktree at the commit BEFORE the port, runs TODAY's pre-flight
there (not the one from back then — the point is to measure the tool we are about to use),
and compares the warnings with the files the port's commit touched OUTSIDE the module.
Everything in that gap is a blind spot, with a name and an address.

The Tip port gave six warnings against forty files. Most of the gap was bookkeeping —
RULES.md, CHANGELOG.md, the generated catalogue — and four were real seams. Run it after
EVERY port: that is how the seam list grows.

Usage:
  backport_audit.py <port-sha> <project> <source:target> [<source:target> ...]

  backport_audit.py e97bba3 impressao/new.impressao \\
      api.impressao/app/Modules/Tip:arcus/app/Modules/Tip \\
      app.impressao/src/modules/Tip:celer/src/modules/Tip

Exits 1 if any candidate blind spot is left; 0 if every file touched outside the module is
either named in the report or bookkeeping.
"""
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

PENDULUM = Path(__file__).resolve().parents[2]
ROOT = PENDULUM.parent

# Files every port touches and no pre-flight has any business predicting: a unit's written
# contract, its version record, and the catalogue a build regenerates.
BOOKKEEPING = re.compile(r'(^|/)(RULES\.md|CHANGELOG\.md|components\.d\.ts|[\w.-]*\.generated\.json)$')


def git(*args, cwd=PENDULUM) -> str:
    return subprocess.run(['git', *args], cwd=cwd, capture_output=True, text=True, check=True).stdout


def _was_warned_about(rel_path: str, mentioned: str) -> bool:
    """Has the report already mentioned this file? Not always by its name.

    The pre-flight names the THING, not the file: a migration shows up as `Tip -> tips`, a
    translation key as `tips.title` and never as `pt-BR.json`, and a component as
    `APageHeaderAction` while the port touched the `index.ts` and the `.spec.ts` in its
    folder. Matching on the file name alone fills the list with false blind spots — and a
    list with twelve false entries out of twenty-two teaches you to ignore the list.
    """
    parts = Path(rel_path)
    name_root = parts.name.split('.')[0].lower()

    if name_root and name_root in mentioned:
        return True

    # A sibling of a warned unit: `.../APageHeaderAction/index.ts` when the report spoke
    # of `APageHeaderAction`.
    if parts.parent.name.lower() in mentioned and parts.parent.name:
        return True

    # Migration: the report names the TABLE, which is the middle of the file name.
    table = re.search(r'create_(\w+)_table', parts.name)
    if table and table.group(1).lower() in mentioned:
        return True

    # A translation file: covered if the report reported any key at all.
    if 'locales' in parts.parts and 'translation key' in mentioned:
        return True

    return False


def main() -> int:
    if len(sys.argv) < 4:
        print(__doc__)
        return 2

    sha, project, pairs = sys.argv[1], sys.argv[2], sys.argv[3:]
    if any(':' not in pair for pair in pairs):
        print('every pair needs the ":" — <source:target>')
        return 2

    sources = [pair.split(':', 1)[0] for pair in pairs]
    targets = [pair.split(':', 1)[1] for pair in pairs]

    touched = [line for line in git('show', '--name-only', '--format=', sha).splitlines() if line]
    if not touched:
        print(f'commit with no files: {sha}')
        return 2

    outside = [rel_path for rel_path in touched
            if not any(rel_path.startswith(target.rstrip('/') + '/') for target in targets)]

    with tempfile.TemporaryDirectory() as tmp:
        before_state = Path(tmp) / 'antes'
        git('worktree', 'add', '-q', '--detach', str(before_state), f'{sha}^')
        try:
            # TODAY's pre-flight against YESTERDAY's target. Measuring the old tool says
            # nothing about the one we will use on the next port.
            shutil.copy(Path(__file__).parent / 'backport_preflight.py', before_state / 'cortex/sync')

            environment = {**os.environ, 'BACKPORT_RAIZ': str(ROOT)}
            output = subprocess.run(
                [sys.executable, 'cortex/sync/backport_preflight.py', project, *sources],
                cwd=before_state, capture_output=True, text=True, env=environment,
            ).stdout
        finally:
            git('worktree', 'remove', '--force', str(before_state))

    if not output.strip():
        print('the pre-flight produced no report — check the source paths')
        return 2

    mentioned = output.lower()
    bookkeeping, covered, blind = [], [], []
    for rel_path in sorted(outside):
        if BOOKKEEPING.search(rel_path):
            bookkeeping.append(rel_path)
        elif _was_warned_about(rel_path, mentioned):
            covered.append(rel_path)
        else:
            blind.append(rel_path)

    warnings = [line.strip() for line in output.splitlines() if line.strip().startswith(('✗', '?'))]

    print('\n═══ what the pre-flight said, at the commit before the port ═══')
    for line in warnings:
        print(f'  {line}')
    if not warnings:
        print('  (no warnings)')

    print('\n═══ files the port touched outside the module ═══')
    print(f'  {len(outside)} in total · {len(covered)} named in the report · '
          f'{len(bookkeeping)} bookkeeping · {len(blind)} unexplained')

    if blind:
        print('\n═══ BLIND SPOTS — the port needed it and the pre-flight never said ═══')
        for rel_path in blind:
            print(f'  ?? {rel_path}')
        print('\nEach of these is a CANDIDATE seam, not a seam yet. Some will be the feature '
              'itself: another module\'s view that gained the component call is the reason for '
              'the port, not a forgotten stitch. Whatever repeats in a second port stops being '
              'a candidate — then it is a seam, and it deserves a check.')

    return 1 if blind else 0


if __name__ == '__main__':
    sys.exit(main())
