#!/usr/bin/env python3
"""Deep diff: what reached pendulum against what left the project.

Step 10 of BACKPORT.md, and what catches whatever tests and screens do not. On the first
port the suite was green and the screens were working when this ran — and it found a front
enum out of step with the API's, an interface string carrying another product's name,
orphan labels, and a RULES.md pointing at files that do not exist.

It normalises the MECHANICAL transforms on both sides and then compares. Three questions:

  1. did any file from the source NOT arrive?
  2. did any arrive with different content?
  3. did the target gain something the source never had?

EVERY difference has to match a recorded decision. One that does not is an accident.

Usage:
  backport_diff.py <project> <source:target> [<source:target> ...]

  backport_diff.py lifeshifter \\
      api.lifeshifter/app/Modules/Idea:arcus/app/Modules/Idea \\
      app.lifeshifter/src/modules/Idea:celer/src/modules/Idea

<project> is the child repository's path from battoni.dev — almost always the folder name,
but not always: impressao lives at `impressao/new.impressao`. The API and APP names do not
follow the folder either, so they are read from the child's `.cortex-sync.conf`, which is
where the sync already declares them. Guessing from the directory name compared the module
against the wrong normaliser and showed every file as different.

Paths are relative to each repository's root. File or directory, either works.
"""
import os
import re
import sys
from pathlib import Path

ROOT = Path(os.environ.get('BACKPORT_RAIZ') or Path(__file__).resolve().parents[2].parent)
PENDULUM = Path(__file__).resolve().parents[2]

BINARIES = {'.png', '.jpg', '.jpeg', '.gif', '.webp', '.ico', '.woff', '.woff2', '.svg'}


def read_conf(child: Path) -> dict:
    """The child's PROJECT/APP/API, from the same source the sync reads."""
    source_file = child / '.cortex-sync.conf'
    if not source_file.exists():
        return {}

    conf = {}
    for line in source_file.read_text(encoding='utf-8').splitlines():
        if '=' in line and not line.lstrip().startswith('#'):
            key, value = line.split('=', 1)
            conf[key.strip()] = value.strip()
    return conf


def normaliser(conf: dict):
    """Makes both sides speak the same language, undoing specialize()."""
    project, api, app = conf['PROJECT'], conf['API'], conf['APP']
    swaps = [
        (f'{api}.test', 'API.test'), (f'{app}.test', 'APP.test'),
        ('arcus.pendulum.test', 'API.test'), ('celer.pendulum.test', 'APP.test'),
        (api, 'API'), (app, 'APP'),
        ('arcus', 'API'), ('celer', 'APP'),
        (f'origin: {project}', 'origin: ORIGIN'), ('origin: pendulum', 'origin: ORIGIN'),
        (f"config('{project}.", "config('PROJ."), ("config('pendulum.", "config('PROJ."),
    ]

    def normalise(text: str) -> str:
        for before, after in swaps:
            text = text.replace(before, after)
        return text

    return normalise


def compare(source: Path, target: Path, normalise):
    missing, different, extras = [], [], []

    if source.is_file():
        pairs = [(source, target, source.name)]
    else:
        pairs = [(a, target / a.relative_to(source), str(a.relative_to(source)))
                 for a in sorted(source.rglob('*')) if a.is_file()]

        seen = {unit_name for _, _, unit_name in pairs}
        if target.exists():
            extras = [str(b.relative_to(target)) for b in sorted(target.rglob('*'))
                      if b.is_file() and str(b.relative_to(target)) not in seen]

    for before, after, unit_name in pairs:
        if not after.exists():
            missing.append(unit_name)
            continue
        if before.suffix.lower() in BINARIES:
            if before.read_bytes() != after.read_bytes():
                different.append(unit_name)
            continue
        try:
            a = normalise(before.read_text(encoding='utf-8'))
            b = normalise(after.read_text(encoding='utf-8'))
        except (UnicodeDecodeError, OSError):
            continue
        if a != b:
            different.append(unit_name)

    return missing, different, extras


def main() -> int:
    if len(sys.argv) < 3:
        print(__doc__)
        return 2

    project, pairs = sys.argv[1], sys.argv[2:]
    child = ROOT / project
    if not child.exists():
        print(f'project not found: {child}')
        return 2

    conf = read_conf(child)
    lacking = [key for key in ('PROJECT', 'API', 'APP') if key not in conf]
    if lacking:
        print(f"{child}/.cortex-sync.conf does not declare: {', '.join(lacking)}")
        return 2

    normalise = normaliser(conf)
    total = {'missing': 0, 'different': 0, 'extras': 0}

    for pair in pairs:
        if ':' not in pair:
            print(f'invalid pair (missing the ":"): {pair}')
            return 2

        rel_source, rel_target = pair.split(':', 1)
        source, target = child / rel_source, PENDULUM / rel_target

        if not source.exists():
            print(f'!! source does not exist: {rel_source}')
            continue

        missing, different, extras = compare(source, target, normalise)
        total['missing'] += len(missing)
        total['different'] += len(different)
        total['extras'] += len(extras)

        if missing or different or extras:
            print(f'\n═══ {rel_target} ═══')
            for unit_name in missing:
                print(f'  MISSING       {unit_name}')
            for unit_name in different:
                print(f'  DIFFERS       {unit_name}')
            for unit_name in extras:
                print(f'  PARENT ONLY   {unit_name}')

    print(f"\n{total['missing']} missing · {total['different']} different · "
          f"{total['extras']} parent only")

    # A lost file or an intruder is an error; a difference is for a human to read.
    return 1 if total['missing'] or total['extras'] else 0


if __name__ == '__main__':
    sys.exit(main())
