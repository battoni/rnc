#!/usr/bin/env python3
"""Where every piece of ongoing work stands (`/battoni-status`): the current project by default,
every flow project under ~/Work with `--all`.

Nothing to keep up to date: it reads what the feature flow already writes. For each repository
that runs the flow (it has `.claude/commands/battoni-intent.md`):

- every feature folder's `state.md` (`Stage:` and the first paragraph of `## Next`), read from the
  feature's own branch while that branch is not merged, and from `origin/main` (or `main`) after;
- every branch that carries a task ID and is not merged, with no folder = the small track;
- where each one is checked out (`git worktree list`), and its open PR (`gh`, skipped offline).

One line per feature, `id | state | what | how to work on it`, the owner's own format (10/11/2026): a
table cut every long cell in the terminal, and blocks with every field were too much to read.
Delivered features are hidden unless `--delivered`. Animals First is never read (owner's rule).

    python3 scripts/flow-status.py [--all] [--delivered] [--no-pr] [--root DIR ...]
"""
from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
from dataclasses import dataclass
from pathlib import Path

DEFAULT_ROOTS = [Path.home() / 'Work']
EXCLUDED_WORDS = ('animalsfirst',)
SKIPPED_FOLDERS = ('node_modules', 'vendor', 'dist')
MAX_DEPTH = 4
FLOW_MARKER = Path('.claude/commands/battoni-intent.md')
STATE_PATH = re.compile(r'^specs/([^/]+)/state\.md$')


@dataclass
class Item:
    id: str
    title: str
    stage: str
    next: str
    branch: str | None = None
    worktree: str | None = None
    in_main_folder: bool = False
    pr: str | None = None

    @property
    def number(self) -> int:
        return int(self.id.rsplit('-', 1)[1])

    @property
    def delivered(self) -> bool:
        return self.stage.lower().startswith('delivered')


def run(cwd: Path, *args: str, timeout: int = 20) -> str | None:
    try:
        result = subprocess.run(args, cwd=cwd, capture_output=True, text=True, timeout=timeout)
    except (OSError, subprocess.TimeoutExpired):
        return None

    if result.returncode != 0:
        return None

    return result.stdout


def git(repo: Path, *args: str) -> str | None:
    return run(repo, 'git', *args)


def discover(roots: list[Path]) -> list[Path]:
    """Main checkouts of flow repositories (a worktree has a `.git` file, not a folder)."""
    found = []

    for root in roots:
        root = Path(root).expanduser()

        for folder, children, _ in os.walk(root):
            path = Path(folder)

            if any(word in path.as_posix().lower() for word in EXCLUDED_WORDS):
                children[:] = []
                continue

            if (path / '.git').is_dir() and (path / FLOW_MARKER).is_file():
                found.append(path)

            if (path / '.git').exists() or len(path.relative_to(root).parts) >= MAX_DEPTH:
                children[:] = []
                continue

            children[:] = [child for child in children if not child.startswith('.') and child not in SKIPPED_FOLDERS]

    return sorted(found)


def task_prefix(repo: Path) -> str:
    conf = repo / '.cortex-sync.conf'
    values = {}

    if conf.is_file():
        for line in conf.read_text().splitlines():
            key, _, value = line.partition('=')
            values[key.strip()] = value.strip()

    return values.get('TASK_PREFIX') or values.get('PROJECT') or repo.name


def base_ref(repo: Path) -> str:
    if git(repo, 'rev-parse', '--verify', '-q', 'origin/main') is not None:
        return 'origin/main'

    return 'main'


def worktrees(repo: Path) -> tuple[str | None, dict[str, str]]:
    """(branch of the main folder, {branch: worktree path}) — the main folder is the first block."""
    output = git(repo, 'worktree', 'list', '--porcelain') or ''
    main_branch = None
    by_branch = {}

    for index, block in enumerate(output.strip().split('\n\n')):
        fields = dict(line.split(' ', 1) for line in block.splitlines() if ' ' in line)
        branch = fields.get('branch', '').removeprefix('refs/heads/') or None

        if index == 0:
            main_branch = branch
            continue

        if branch:
            by_branch[branch] = str(Path(fields['worktree']).resolve())

    return main_branch, by_branch


def states(repo: Path, ref: str) -> dict[str, str]:
    """{folder: state.md text} on `ref`."""
    listing = git(repo, 'ls-tree', '-r', '--name-only', ref, '--', 'specs/') or ''
    found = {}

    for path in listing.splitlines():
        match = STATE_PATH.match(path)

        if not match:
            continue

        text = git(repo, 'show', f'{ref}:{path}')

        if text is not None:
            found[match.group(1)] = text

    return found


def plain(text: str) -> str:
    return re.sub(r'\*\*|`', '', text).strip()


def parse_state(feature_id: str, folder: str, text: str) -> Item:
    lines = text.splitlines()
    stage = next((line[len('Stage:'):].strip() for line in lines if line.startswith('Stage:')), '?')
    title = folder.removeprefix(f'{feature_id}-').replace('-', ' ')
    heading = lines[0] if lines else ''

    if '—' in heading:
        title = heading.split('—', 1)[1].strip()

    next_line = ''

    if '## Next' in lines:
        for line in lines[lines.index('## Next') + 1:]:
            if line.startswith('## '):
                break

            if line.strip():
                next_line = plain(line)
                break

    return Item(id=feature_id, title=title, stage=stage, next=next_line)


def open_prs(repo: Path) -> dict[str, str]:
    output = run(repo, 'gh', 'pr', 'list', '--state', 'open', '--limit', '100', '--json', 'number,headRefName,isDraft')

    if not output:
        return {}

    return {pr['headRefName']: f"#{pr['number']}{' draft' if pr['isDraft'] else ''}" for pr in json.loads(output)}


def merged_history(repo: Path, base: str, id_pattern: re.Pattern) -> dict[str, Item]:
    """Small-track work already merged: no folder and no branch left, only `base`'s history.

    The PR from `Merge pull request #N from <owner>/<branch>`, the title from the oldest
    `<id>: <subject>` commit (a squash merge `<id>: <subject> (#N)` gives both).
    """
    subjects = (git(repo, 'log', base, '--format=%s') or '').splitlines()
    prs: dict[str, str] = {}
    titles: dict[str, str] = {}

    for subject in reversed(subjects):
        merge = re.match(r'Merge pull request #(\d+) from \S+?/(\S+)', subject)

        if merge and id_pattern.search(merge.group(2)):
            prs.setdefault(id_pattern.search(merge.group(2)).group(1), merge.group(1))
            continue

        own = id_pattern.match(subject)

        if not own or not subject[own.end():].startswith(': '):
            continue

        title = subject[own.end() + 2:].strip()
        squash = re.search(r' \(#(\d+)\)$', title)

        if squash:
            prs.setdefault(own.group(1), squash.group(1))
            title = title[:squash.start()]

        titles.setdefault(own.group(1), title)

    found = {}

    for feature_id in set(prs) | set(titles):
        stage = f'delivered — PR #{prs[feature_id]}' if feature_id in prs else 'delivered'
        found[feature_id] = Item(id=feature_id, title=titles.get(feature_id, ''), stage=stage, next='')

    return found


def collect(repo: Path, with_prs: bool = True, show_delivered: bool = False) -> list[Item]:
    prefix = task_prefix(repo)
    id_pattern = re.compile(rf'(?<![\w-])({re.escape(prefix)}-\d+)(?![\d])')
    base = base_ref(repo)
    main_branch, by_branch = worktrees(repo)
    items: dict[str, Item] = {}

    for folder, text in states(repo, base).items():
        match = id_pattern.match(folder)

        if match:
            items[match.group(1)] = parse_state(match.group(1), folder, text)

    branches = (git(repo, 'for-each-ref', '--format=%(refname:short)', 'refs/heads/') or '').split()
    prs = open_prs(repo) if with_prs else {}

    for branch in branches:
        match = id_pattern.search(branch)

        if not match or git(repo, 'merge-base', '--is-ancestor', branch, base) is not None:
            continue

        feature_id = match.group(1)
        own = {folder: text for folder, text in states(repo, branch).items() if id_pattern.match(folder) and id_pattern.match(folder).group(1) == feature_id}
        item = Item(id=feature_id, title=branch.split('/', 1)[-1].removeprefix(f'{feature_id}-').replace('-', ' '), stage='small (no spec folder)', next='')

        for folder, text in own.items():
            item = parse_state(feature_id, folder, text)

        item.branch = branch
        item.worktree = by_branch.get(branch)
        item.in_main_folder = branch == main_branch
        item.pr = prs.get(branch)
        items[feature_id] = item

    if show_delivered:
        for feature_id, item in merged_history(repo, base, id_pattern).items():
            items.setdefault(feature_id, item)

    shown = [item for item in items.values() if show_delivered or not item.delivered]

    return sorted(shown, key=lambda item: item.number)


def home(path: str) -> str:
    user = str(Path.home())

    if path.startswith(user + os.sep):
        return '~' + path[len(user):]

    return path


def current_repo(cwd: Path) -> Path | None:
    """The main checkout of the flow repository `cwd` is in (also from inside a worktree)."""
    common = git(Path(cwd), 'rev-parse', '--path-format=absolute', '--git-common-dir')

    if not common:
        return None

    repo = Path(common.strip()).parent

    if not (repo / FLOW_MARKER).is_file():
        return None

    return repo


def short_stage(item: Item) -> str:
    """`delivered — PR #24` → `merged`, `verified — PR not opened (…)` → `verified`; small adds its PR."""
    if item.delivered:
        pr = re.search(r'PR #(\d+)', item.stage)

        return f'merged · PR #{pr.group(1)}' if pr else 'merged'

    stage = re.split(r' — | \(', item.stage, maxsplit=1)[0].strip()

    if stage.startswith('small'):
        return f'small · PR {item.pr}' if item.pr else 'small'

    return stage


def how(item: Item, repo: Path) -> str:
    """The one line to run to work on it."""
    folder = home(str(repo))

    if item.in_main_folder:
        return f'git -C {folder} checkout main && git -C {folder} worktree add {folder}/.claude/worktrees/{item.id} {item.branch}'

    if item.worktree:
        return f'cd {home(item.worktree)} && cc {item.id}'

    if item.branch:
        return f'git -C {folder} worktree add {folder}/.claude/worktrees/{item.id} {item.branch}'

    return '—'


def render(results: list[tuple[Path, list[Item]]], show_delivered: bool = False) -> str:
    """One line per feature: `id | state | what | how to work on it`, columns aligned; warnings after."""
    rows = []
    warnings = []

    for repo, items in results:
        main_branch, _ = worktrees(repo)

        if main_branch and main_branch != 'main':
            warnings.append(f'⚠ {repo.name}: the main folder is on {main_branch}, not main. Every session there moves with it.')

        for item in items:
            rows.append((item.id, short_stage(item), item.title, how(item, repo)))

            if item.in_main_folder:
                warnings.append(f'⚠ {item.id} is checked out in the main folder: its line above moves it to a worktree (needs a clean tree).')

    if not rows:
        rows.append(('—', 'no features' if show_delivered else 'nothing ongoing', '', '—'))

    widths = [max(len(row[column]) for row in rows) for column in range(3)]
    lines = [' | '.join([*(row[column].ljust(widths[column]) for column in range(3)), row[3]]) for row in rows]

    return '\n'.join(lines + warnings) + '\n'


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument('--all', action='store_true', help='every flow project under the roots, not only the current one')
    parser.add_argument('--delivered', action='store_true', help='also list delivered features')
    parser.add_argument('--no-pr', action='store_true', help='skip the GitHub lookup')
    parser.add_argument('--root', action='append', type=Path, help='folder to search with --all (default ~/Work); repeatable')
    args = parser.parse_args()

    repos = discover(args.root or DEFAULT_ROOTS) if args.all else [current_repo(Path.cwd())]

    if repos == [None]:
        print('Not inside a flow project. Run with --all to see every project under ~/Work.')
        return

    results = [(repo, collect(repo, with_prs=not args.no_pr, show_delivered=args.delivered)) for repo in repos]
    print(render(results, show_delivered=args.delivered), end='')


if __name__ == '__main__':
    main()
