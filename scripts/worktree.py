#!/usr/bin/env python3
"""One worktree per session: its label, its ports and its environment's .env files.

A worktree lives under `.claude/worktrees/<id>` of the main checkout. Everything here is derived
from git and from the main checkout's folder name (the project), so the script carries no project
literal and a synced copy works as it is:

- label: `<p><n>` for an ID `<project>-<n>` (`p` = the project's first letter), otherwise the
  name lowercased to `[a-z0-9]` (spec R5).
- ports: each project claims a block of 200 from 6000 in a file per machine (`WT_PORT_FILE`,
  default `~/.config/battoni/port-blocks.json`), under an `fcntl` lock. A feature `<n>` (n < 80)
  gets Vite `base + n` and e2e `base + 100 + n`; any other worktree the first slot from 80 to 99
  that no other worktree's `.worktree.json` holds and `lsof` does not see listening (spec R6).
- env files: the worktree's api and app `.env` are the main checkout's, line for line, with only
  the environment's own origin keys changed; secrets are copied, never printed (spec R8).
- new (`make wt-new ID=<id> [SLUG=<slug>] [BRANCH=<branch>]`): `git fetch`, then the worktree on
  `feature/<id>[-<slug>]` from origin/main (untracked), or on BRANCH (local, from origin, or new
  from origin/main); refused when its label is another worktree's or `<project>_<label>` exists
  with no record. Installed `vendor/`/`node_modules/` are cloned (`cp -c`, plain copy where
  unsupported) and reconciled with `composer install`/`npm install`; `.worktree.json` is written
  (spec R2, R10). An install that leaves `git status` dirty (a rewritten lockfile) is warned about,
  since `down`/`discard`/`gc` refuse a dirty tree.
- Node: every `npm` (install, `run dev`) runs under the Node the unit's nearest `.nvmrc` names (the
  unit's own, else one in a folder above it up to the worktree root), through `fnm exec --using`
  (`WT_FNM` overrides where fnm is; empty means none). Without fnm, the shell's `node` must have the
  `.nvmrc` major, or the command is refused: npm 10 under Node 22 rewrote the app's npm 11 lockfile.
- up (`make wt-up`, inside a worktree; adopts one made by hand): every check first, then the
  work. The main api `.env` must be local (APP_ENV, DB_HOST, DB_DATABASE = the project, target
  `<project>_<label>`, R14), have `SESSION_DOMAIN=.<project>.test` (R9), and `sudo -n -l` must
  list a NOPASSWD rule for a valet path (else it prints the line to run once in a real terminal,
  `/opt/homebrew/bin/valet trust`, R15); only then is `valet` (always by
  that path) or `mysql` called. It claims the ports, writes the record, sets the main
  `SESSION_DRIVER` back to `database` when an older wt-up left it on `cookie` (R9, C6), links and secures `api.<label>.<project>` (isolated to the
  main API site's PHP) and `app.<label>.<project>`, copies the local DB into a new
  `<project>_<label>` (kept on a rerun), writes both `.env` files (sessions in the main DB's
  table, `SESSION_TABLE=<project>.sessions`, C6) and runs `migrate --force` (R4).
- SQLite projects (`DB_CONNECTION=sqlite` in the main api `.env`, served by `php artisan serve`, no
  Valet): `new`, `down`, `discard` and `gc` skip every MySQL step, and `up` refuses naming it.
- app (`make wt-app`): `npm run dev` in the app (under its `.nvmrc` Node) with `E2E_PORT` exported; refused before `up` or
  when its port listens, warns above 4 Vite servers across the blocks (R7).

- down (`make wt-down ID=<id>`): after `git fetch`, refused naming the check unless the branch's PR is
  merged (`gh`), `origin/main..<branch>` is empty, `git status` is clean, every ignored file is
  regenerable (deps, builds, `.env*`, caches), no transcript whose records' `cwd` is in it changed in
  30 min and its Vite port is quiet (R11, R14). Then it undoes what `up` left (Valet isolation,
  certificate and link, by the trusted path; a link pointing elsewhere is left alone), drops
  `<project>_<label>`, removes the worktree and deletes the branch.
- discard (`make wt-discard ID=<id> [CONFIRM=<id>]`): `down` for work that will not merge. The same
  checks, except the two that only mean "not merged" (a merged PR, nothing ahead of origin/main); an
  OPEN PR is refused (`close PR #<n> first`), and a dirty tree still is, since uncommitted work has no
  restore line. Without `CONFIRM`, or with one that is not the ID, it only lists what would go (Valet
  steps, database, folder, branch with its tip SHA) and the confirmed command; no prompt is read.
  Confirmed, it prints `git branch <branch> <sha>` to restore the branch, then does what `down` does.
  Local only: nothing is pushed, no remote branch is deleted, no PR is closed.
- gc (`make wt-gc`): one fetch, then `down` on every non-main worktree that passes, record or not;
  the rest is listed as kept (with the failed check) or left behind (PR closed unmerged, or no
  commit or session for 7 days), never torn down on its own (R12); a left-behind line names
  `make wt-discard ID=<id>`, the dry run.
- port (`make e2e` exports it as `E2E_PORT` unless one is set): this checkout's e2e port. The main
  checkout's is slot 0 of the project's block (`base + 100`, the block claimed on first use); a
  worktree's is its record's, or the one `up` would give it when it has none (nothing written).

Run `python3 scripts/worktree.py label <name>`, `… new <id>`, `… up`, `… app`, `… down <id>`,
`… discard <id> [--confirm <id>]`, `… gc` or `… port`; tests: `make wt-test`.
"""
from __future__ import annotations

import argparse
import fcntl
import json
import os
import re
import shutil
import subprocess
import sys
from datetime import datetime
from pathlib import Path

BLOCK_START = 6000
BLOCK_SIZE = 200
FEATURE_SLOTS = 80
OTHER_SLOTS = range(80, 100)
E2E_OFFSET = 100
RECORD = '.worktree.json'


class WtError(Exception):
    """A refusal with its reason; the CLI prints it and exits 1."""


# --- names -------------------------------------------------------------------------------------

def label(name: str, project: str) -> str:
    match = re.fullmatch(rf'{re.escape(project)}-(\d+)', name)
    if match:
        return f'{project[0].lower()}{int(match.group(1))}'
    cleaned = re.sub(r'[^a-z0-9]', '', name.lower())
    if not cleaned:
        raise WtError(f'"{name}" has no letter or digit to make a label from')
    return cleaned


def feature_number(wt_id: str, project: str) -> int | None:
    match = re.fullmatch(rf'{re.escape(project)}-(\d+)', wt_id)
    return int(match.group(1)) if match else None


def git_out(cwd: Path, *args: str, strip: bool = True) -> str:
    result = subprocess.run(['git', *args], cwd=cwd, capture_output=True, text=True)
    if result.returncode != 0:
        raise WtError(f'git {" ".join(args)} failed in {cwd}: {result.stderr.strip()}')
    return result.stdout.strip() if strip else result.stdout


def main_checkout(path: Path) -> Path:
    """The main checkout of the repo `path` sits in, from a worktree or a subfolder alike."""
    common = git_out(path, 'rev-parse', '--path-format=absolute', '--git-common-dir')
    return Path(common).resolve().parent


def worktree_paths(main: Path) -> list[Path]:
    porcelain = git_out(main, 'worktree', 'list', '--porcelain')
    return [Path(line[len('worktree '):]).resolve() for line in porcelain.splitlines() if line.startswith('worktree ')]


def read_record(path: Path) -> dict | None:
    try:
        return json.loads((path / RECORD).read_text())
    except (OSError, ValueError):
        return None


# --- ports -------------------------------------------------------------------------------------

def port_file() -> Path:
    return Path(os.environ.get('WT_PORT_FILE', Path.home() / '.config' / 'battoni' / 'port-blocks.json'))


def claim_block(project: str, path: Path) -> int:
    """The project's block base, claiming the lowest free block of 200 on its first call."""
    path.parent.mkdir(parents=True, exist_ok=True)
    with open(path, 'a+', encoding='utf-8') as handle:
        fcntl.flock(handle, fcntl.LOCK_EX)
        handle.seek(0)
        raw = handle.read()
        try:
            data = json.loads(raw) if raw.strip() else {}
            blocks = data.setdefault('blocks', {})
            if not isinstance(blocks, dict):
                raise ValueError
        except ValueError:
            raise WtError(f'{path} is not a port-block file I can read; fix or remove it by hand') from None
        if project in blocks:
            return int(blocks[project])
        used = {int(base) for base in blocks.values()}
        base = BLOCK_START
        while base in used:
            base += BLOCK_SIZE
        if base + BLOCK_SIZE > 65536:
            raise WtError(f'no free port block left in {path}')
        blocks[project] = base
        handle.seek(0)
        handle.truncate()
        handle.write(json.dumps(data, indent=2, sort_keys=True) + '\n')
        handle.flush()
        os.fsync(handle.fileno())
        return base


def is_listening(port: int) -> bool:
    try:
        result = subprocess.run(
            ['lsof', '-nP', f'-iTCP:{port}', '-sTCP:LISTEN', '-t'], capture_output=True, text=True,
        )
    except FileNotFoundError:
        return False
    return result.returncode == 0 and bool(result.stdout.strip())


def allocate_ports(wt: Path) -> dict:
    """Base, slot, Vite and e2e ports for the worktree at `wt` (its own record's slot is kept)."""
    wt = wt.resolve()
    main = main_checkout(wt)
    project = main.name
    wt_id = wt.name
    base = claim_block(project, port_file())

    own = read_record(wt) or {}
    own_slot = (own.get('ports') or {}).get('slot')
    if isinstance(own_slot, int):
        return ports_for(base, own_slot)

    taken: dict[int, str] = {}
    for path in worktree_paths(main):
        if path == wt:
            continue
        record = read_record(path) or {}
        slot = (record.get('ports') or {}).get('slot')
        if isinstance(slot, int):
            taken[slot] = record.get('id') or path.name

    n = feature_number(wt_id, project)
    if n is not None:
        if n >= FEATURE_SLOTS:
            raise WtError(
                f'{wt_id}: feature number {n} has no port slot (features use 0-{FEATURE_SLOTS - 1}, '
                f'other worktrees 80-99)'
            )
        if n in taken:
            raise WtError(f'{wt_id}: port slot {n} is held by the worktree {taken[n]}')
        for port in (base + n, base + E2E_OFFSET + n):
            if is_listening(port):
                raise WtError(f'{wt_id}: port {port} is already listening (lsof); stop what holds it first')
        return ports_for(base, n)

    for slot in OTHER_SLOTS:
        if slot not in taken and not is_listening(base + slot) and not is_listening(base + E2E_OFFSET + slot):
            return ports_for(base, slot)
    raise WtError(f'{wt_id}: every port slot from 80-99 is taken (a record holds it or it is listening)')


def ports_for(base: int, slot: int) -> dict:
    return {'base': base, 'slot': slot, 'vite': base + slot, 'e2e': base + E2E_OFFSET + slot}


def e2e_port(cwd: Path) -> int:
    """This checkout's e2e port: slot 0 of the block for the main checkout, the record's for a worktree."""
    main = main_checkout(cwd)
    here = Path(git_out(cwd, 'rev-parse', '--show-toplevel')).resolve()
    if here == main:
        return claim_block(main.name, port_file()) + E2E_OFFSET
    e2e = ((read_record(here) or {}).get('ports') or {}).get('e2e')
    if isinstance(e2e, int):
        return e2e
    return allocate_ports(here)['e2e']


# --- env files ---------------------------------------------------------------------------------

LINE_RE = re.compile(r'^(\s*(?:export\s+)?)([A-Za-z_][A-Za-z0-9_]*)(\s*=)(.*?)(\r?\n)?$', re.DOTALL)


def env_changes(project: str, lbl: str, port: int) -> tuple[dict[str, str], dict[str, str]]:
    domain = f'{project}.test'
    api_host = f'api.{lbl}.{domain}'
    app_host = f'app.{lbl}.{domain}'
    app_origin = f'https://{app_host}:{port}'
    api = {
        'APP_URL': f'https://{api_host}',
        'FRONTEND_URL': app_origin,
        'DB_DATABASE': f'{project}_{lbl}',
        'CORS_ALLOWED_ORIGINS': app_origin,
        'SANCTUM_STATEFUL_DOMAINS': f'{app_host}:{port}',
        'CORS_ALLOWED_ORIGINS_PATTERNS': '',
    }
    app = {
        'VITE_API_URL': f'https://{api_host}/api',
        'VITE_APP_URL': app_origin,
        'VITE_SENTRY_PROPAGATION_TARGETS': f"['localhost', '{app_origin}']",
        'VITE_LOCAL_SSL_KEY': f'.config/valet/Certificates/{app_host}.key',
        'VITE_LOCAL_SSL_CERT': f'.config/valet/Certificates/{app_host}.crt',
    }
    return api, app


def quote(value: str, original: str) -> str:
    if original.startswith('"') or re.search(r'[\s#\'"]', value):
        return '"' + value.replace('\\', '\\\\').replace('"', '\\"') + '"'
    return value


def rewrite_env(text: str, changes: dict[str, str]) -> str:
    """`text` with each key of `changes` set; every other line byte-identical, missing keys appended."""
    lines = text.splitlines(keepends=True)
    newline = '\r\n' if lines and lines[0].endswith('\r\n') else '\n'
    seen: set[str] = set()
    out = []
    for line in lines:
        match = LINE_RE.match(line)
        if match and match.group(2) in changes:
            prefix, key, equals, raw, ending = match.groups()
            out.append(f'{prefix}{key}{equals}{quote(changes[key], raw.strip())}{ending or ""}')
            seen.add(key)
        else:
            out.append(line)
    missing = [key for key in changes if key not in seen]
    if missing and out and not out[-1].endswith('\n'):
        out[-1] += newline
    for key in missing:
        out.append(f'{key}={quote(changes[key], "")}{newline}')
    return ''.join(out)


def session_changes(main: Path, project: str) -> dict[str, str]:
    """C6: every environment keeps its sessions in the main database's table, so one login holds.

    The API starts its session in the `api` group with no queued-cookie middleware, so the
    `cookie` driver never sends its data cookie (login 200, then 401); a shared `database`
    table needs no code change, and every DB copy lives on the same local MySQL.
    """
    path = main / f'api.{project}' / '.env'
    table = (read_env(path).get('SESSION_TABLE') if path.is_file() else '') or 'sessions'
    return {'SESSION_DRIVER': 'database', 'SESSION_TABLE': table if '.' in table else f'{project}.{table}'}


def write_env_files(main: Path, wt: Path, project: str, lbl: str, port: int) -> None:
    api_changes, app_changes = env_changes(project, lbl, port)
    api_changes.update(session_changes(main, project))
    for unit, changes in ((f'api.{project}', api_changes), (f'app.{project}', app_changes)):
        source = main / unit / '.env'
        if not source.is_file():
            raise WtError(f'{unit}/.env is missing in the main checkout ({main})')
        text = source.read_bytes().decode('utf-8')
        target = wt / unit / '.env'
        target.write_bytes(rewrite_env(text, changes).encode('utf-8'))
        target.chmod(source.stat().st_mode & 0o777)
        print(f'wrote {unit}/.env (set {", ".join(changes)}; every other line copied)')


# --- local database ----------------------------------------------------------------------------

LOCAL_HOSTS = ('127.0.0.1', 'localhost')


def read_env(path: Path) -> dict[str, str]:
    """KEY=value pairs of a .env file, surrounding quotes removed (values never printed)."""
    values = {}
    for line in path.read_text(encoding='utf-8').splitlines():
        match = LINE_RE.match(line)
        if match:
            raw = match.group(4).strip()
            if len(raw) >= 2 and raw[0] == raw[-1] and raw[0] in '"\'':
                raw = raw[1:-1]
            values[match.group(2)] = raw
    return values


def is_sqlite(env: dict[str, str]) -> bool:
    """A project on SQLite (`php artisan serve`): no MySQL database to check, copy or drop, and no Valet."""
    return env.get('DB_CONNECTION') == 'sqlite'


def local_db(main: Path, project: str, target: str) -> dict[str, str] | None:
    """The main checkout's DB settings once they name a local database (R14), else a refusal.

    None when the main checkout has no api `.env` yet (nothing to read or copy from).
    """
    path = main / f'api.{project}' / '.env'
    if not path.is_file():
        return None
    env = read_env(path)
    if env.get('APP_ENV') != 'local':
        raise WtError(f'api.{project}/.env in the main checkout is not APP_ENV=local; refusing to touch its database')
    if is_sqlite(env):
        return env
    if env.get('DB_HOST', '') not in LOCAL_HOSTS:
        raise WtError(f'api.{project}/.env in the main checkout has a DB_HOST that is not local; refusing')
    if env.get('DB_DATABASE') != project:
        raise WtError(f'api.{project}/.env in the main checkout has a DB_DATABASE that is not {project}; refusing')
    if not re.fullmatch(rf'{re.escape(project)}_[a-z0-9]+', target):
        raise WtError(f'"{target}" is not a worktree database name ({project}_<label>)')
    return env


def mysql_args(tool: str, env: dict[str, str]) -> list[str]:
    return [tool, '-h', env.get('DB_HOST', '127.0.0.1'), '-P', env.get('DB_PORT') or '3306',
            '-u', env.get('DB_USERNAME', 'root')]


def mysql_env(env: dict[str, str]) -> dict[str, str]:
    """The child environment: the password rides in MYSQL_PWD, never in argv."""
    return {**os.environ, 'MYSQL_PWD': env.get('DB_PASSWORD', '')}


def database_exists(env: dict[str, str], name: str) -> bool:
    """Whether the local MySQL has `name`; the password goes through MYSQL_PWD, never argv."""
    argv = mysql_args('mysql', env) + [
        '-N', '-B', '-e', f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{name}'"]
    child_env = mysql_env(env)
    try:
        result = subprocess.run(argv, capture_output=True, text=True, env=child_env)
    except FileNotFoundError:
        raise WtError(f'mysql is not on PATH, so I cannot check that {name} does not exist yet') from None
    if result.returncode != 0:
        raise WtError(
            f'could not ask the local MySQL whether {name} exists (is it running?): {result.stderr.strip()}'
        )
    return name in result.stdout.split()


# --- new ---------------------------------------------------------------------------------------

ID_RE = re.compile(r'[A-Za-z0-9][A-Za-z0-9._-]*')
SLUG_RE = re.compile(r'[a-z0-9]+(?:-[a-z0-9]+)*')
DEPS = (('vendor', 'composer.json', ['composer', 'install', '--no-interaction', '--no-progress']),
        ('node_modules', 'package.json', ['npm', 'install', '--no-audit', '--no-fund']))


def ref_exists(repo: Path, ref: str) -> bool:
    return subprocess.run(
        ['git', 'show-ref', '--verify', '--quiet', ref], cwd=repo, capture_output=True,
    ).returncode == 0


def label_holder(main: Path, lbl: str, skip: Path | None = None) -> str | None:
    """The worktree whose record (or, without one, whose folder name) already has this label."""
    project = main.name
    for path in worktree_paths(main):
        if path == main or path == skip:
            continue
        record = read_record(path)
        if record is not None:
            if record.get('label') == lbl:
                return record.get('id') or path.name
            continue
        try:
            if label(path.name, project) == lbl:
                return path.name
        except WtError:
            continue
    return None


def exclude_worktrees(main: Path) -> None:
    """Keep `.claude/worktrees/` out of the main checkout's `git status` (local exclude, untracked)."""
    if subprocess.run(['git', 'check-ignore', '-q', '.claude/worktrees/x'], cwd=main).returncode == 0:
        return
    common = Path(git_out(main, 'rev-parse', '--path-format=absolute', '--git-common-dir'))
    exclude = common / 'info' / 'exclude'
    exclude.parent.mkdir(parents=True, exist_ok=True)
    text = exclude.read_text() if exclude.exists() else ''
    if text and not text.endswith('\n'):
        text += '\n'
    exclude.write_text(text + '/.claude/worktrees/\n')


def clone_dir(source: Path, target: Path) -> None:
    """An APFS clone (`cp -c`) of `source` at `target`; a plain copy where `-c` is unsupported."""
    if subprocess.run(['cp', '-c', '-R', str(source), str(target)], capture_output=True).returncode == 0:
        return
    if target.exists():
        subprocess.run(['rm', '-rf', str(target)], check=True)
    result = subprocess.run(['cp', '-R', str(source), str(target)], capture_output=True, text=True)
    if result.returncode != 0:
        raise WtError(f'could not copy {source} to {target}: {result.stderr.strip()}')


def clone_deps(main: Path, wt: Path) -> list[tuple[Path, list[str]]]:
    """Clone every installed `vendor/`/`node_modules/` of the main checkout; return what to reconcile."""
    units = [Path('.')] + sorted(p.relative_to(main) for p in main.iterdir() if p.is_dir() and not p.name.startswith('.'))
    installs = []
    for unit in units:
        for folder, manifest, command in DEPS:
            source = main / unit / folder
            if not source.is_dir() or source.is_symlink() or not (wt / unit / manifest).is_file():
                continue
            target = wt / unit / folder
            if not target.exists():
                clone_dir(source, target)
                print(f'cloned {unit / folder}')
            installs.append((wt / unit, command))
    return installs


def nvmrc_version(unit: Path, root: Path) -> str | None:
    """The version in the nearest `.nvmrc` from the unit up to the worktree root, or None."""
    folder = unit.resolve()
    root = root.resolve()
    while True:
        nvmrc = folder / '.nvmrc'
        if nvmrc.is_file():
            version = nvmrc.read_text().strip()
            return version or None
        if folder == root or folder.parent == folder:
            return None
        folder = folder.parent


def fnm_path() -> str | None:
    override = os.environ.get('WT_FNM')
    if override is not None:
        return override or None
    return shutil.which('fnm')


def under_node(command: list[str], unit: Path, root: Path) -> list[str]:
    """`command` as it must run: an npm one under the unit's `.nvmrc` Node; anything else as is."""
    if command[0] != 'npm':
        return command
    version = nvmrc_version(unit, root)
    if version is None:
        return command
    fnm = fnm_path()
    if fnm is not None:
        return [fnm, 'exec', f'--using={version}', '--', *command]
    try:
        current = subprocess.run(['node', '-v'], capture_output=True, text=True).stdout.strip()
    except FileNotFoundError:
        current = ''
    want = version.lstrip('v').split('.')[0]
    if current.lstrip('v').split('.')[0] != want:
        raise WtError(f'{unit.name} wants Node {version} (.nvmrc) but node is {current or "missing"} and fnm '
                      f'is not on PATH; switch Node, then run `{" ".join(command)}` in {unit}')
    return command


def new_worktree(cwd: Path, wt_id: str, branch: str | None = None, slug: str | None = None) -> Path:
    if not ID_RE.fullmatch(wt_id):
        raise WtError(f'"{wt_id}" is not a plain worktree name (letters, digits, ".", "_", "-")')
    if slug is not None and not SLUG_RE.fullmatch(slug):
        raise WtError(f'"{slug}" is not a kebab-case slug')
    main = main_checkout(cwd)
    project = main.name
    lbl = label(wt_id, project)
    wt = main / '.claude' / 'worktrees' / wt_id
    if wt.exists():
        raise WtError(f'{wt} already exists; work in it, or tear it down first')
    holder = label_holder(main, lbl)
    if holder is not None:
        raise WtError(f'the label {lbl} is already the worktree {holder}\'s; pick another ID')
    database = f'{project}_{lbl}'
    env = local_db(main, project, database)
    if env is None:
        print(f'api.{project}/.env is missing in the main checkout; skipped the {database} check')
    elif is_sqlite(env):
        print(f'api.{project} runs on SQLite; there is no {database} database to check')
    elif database_exists(env, database):
        raise WtError(f'the database {database} exists with no worktree recording it; drop it or pick another ID')

    branch = branch or (f'feature/{wt_id}-{slug}' if slug else f'feature/{wt_id}')
    if subprocess.run(['git', 'check-ref-format', '--branch', branch], cwd=main, capture_output=True).returncode != 0:
        raise WtError(f'"{branch}" is not a valid branch name')
    fetch = subprocess.run(['git', 'fetch', '--quiet', 'origin'], cwd=main, capture_output=True, text=True)
    if fetch.returncode != 0:
        raise WtError(f'git fetch origin failed, so origin/main may be stale: {fetch.stderr.strip()}')
    if ref_exists(main, f'refs/heads/{branch}'):
        add = ['worktree', 'add', str(wt), branch]
    elif ref_exists(main, f'refs/remotes/origin/{branch}'):
        add = ['worktree', 'add', '--track', '-b', branch, str(wt), f'origin/{branch}']
    else:
        add = ['worktree', 'add', '--no-track', '-b', branch, str(wt), 'origin/main']
    exclude_worktrees(main)
    git_out(main, *add)
    print(f'created {wt.relative_to(main)} on {branch}')

    domain = f'{project}.test'
    record = {
        'id': wt_id,
        'label': lbl,
        'branch': branch,
        'hosts': {'api': f'api.{lbl}.{domain}', 'app': f'app.{lbl}.{domain}'},
        'database': database,
        'created_at': datetime.now().astimezone().isoformat(timespec='seconds'),
    }
    (wt / RECORD).write_text(json.dumps(record, indent=2) + '\n')

    for unit, command in clone_deps(main, wt):
        argv = under_node(command, unit, wt)
        try:
            result = subprocess.run(argv, cwd=unit)
        except FileNotFoundError:
            raise WtError(f'{argv[0]} is not on PATH; run `{" ".join(command)}` in {unit} by hand') from None
        if result.returncode != 0:
            raise WtError(f'`{" ".join(argv)}` failed in {unit}; the worktree is made, rerun it there')
    dirty = [line[3:] for line in git_out(wt, 'status', '--porcelain', strip=False).splitlines() if line]
    if dirty:
        print(f'warning: the installs left the worktree dirty ({", ".join(dirty[:3])}); '
              f'`make wt-down`/`make wt-discard` refuse it until that is committed or restored')
    serve = ('use the project\'s own make targets to serve it (SQLite: no `wt-up`)' if env is not None and is_sqlite(env)
             else 'serve it with `make wt-up` from inside it')
    print(f'ready: {wt} (label {lbl}); {serve}')
    return wt


# --- up / app ----------------------------------------------------------------------------------

def valet_home() -> Path:
    return Path(os.environ.get('WT_VALET_HOME', Path.home() / '.config' / 'valet'))


def worktree_root(cwd: Path) -> tuple[Path, Path]:
    """(main checkout, worktree) for a cwd inside a worktree; refused in the main checkout."""
    main = main_checkout(cwd)
    wt = Path(git_out(cwd, 'rev-parse', '--show-toplevel')).resolve()
    if wt == main:
        raise WtError('run this inside a worktree (.claude/worktrees/<id>), not in the main checkout')
    return main, wt


def valet_trusted() -> str:
    """R15: the valet that sudo runs without a password (`valet trust`), by the path its rule names.

    Valet re-execs `sudo "$SOURCE"` with the path it was started by, so it must start by the path the
    NOPASSWD rule covers (Homebrew's `bin/valet`), never Composer's proxy that `which` finds first:
    the proxy sources the real script and sudo then asks for a password. `sudo -n -l <valet>` proves
    nothing (a cached timestamp, or `(ALL) ALL` under `listpw=any`, passes it), so the rules are read.
    """
    probe = subprocess.run(['sudo', '-n', '-l'], capture_output=True, text=True)
    if probe.returncode == 0:
        for line in probe.stdout.splitlines():
            if 'NOPASSWD:' not in line:
                continue
            for command in line.rsplit('NOPASSWD:', 1)[1].split(','):
                words = command.replace('SETENV:', '').split()
                if (len(words) in (1, 2) and words[1:] in ([], ['*']) and os.path.basename(words[0]) == 'valet'
                        and os.path.isabs(words[0]) and os.access(words[0], os.X_OK)):
                    return words[0]
    raise WtError(f'Valet is not trusted yet, so it would ask for a password. Run once, in a real terminal '
                  f'(an `!` command cannot read the sudo password): {trust_command()}')


def trust_command() -> str:
    """The one line that trusts Valet: Homebrew's binary by path, since the NOPASSWD rule it writes names
    that path, and a shell function or Composer's proxy named `valet` would start another one."""
    return f"{os.environ.get('HOMEBREW_PREFIX') or '/opt/homebrew'}/bin/valet trust"


def isolated_php(conf: Path) -> str | None:
    """The PHP a Valet site is isolated to (`php@8.4`), read from its Nginx file; None when it has none."""
    if not conf.is_file():
        return None
    match = re.match(r'# ISOLATED_PHP_VERSION=(\S+)', conf.read_text())
    if match is None:
        return None
    return re.sub(r'(?:php@?)?([0-9])\.?([0-9])', r'php@\1.\2', match.group(1), flags=re.I)


def isolate_like_main(valet: str, project: str, site: str, folder: Path) -> None:
    """The worktree's API runs on the PHP the main API site is isolated to (`valet isolate`)."""
    nginx = valet_home() / 'Nginx'
    php = isolated_php(nginx / f'api.{project}.test')
    if php is None or isolated_php(nginx / f'{site}.test') == php:
        return
    run([valet, 'isolate', php, f'--site={site}'], cwd=folder)


def run(argv: list[str], cwd: Path, env: dict[str, str] | None = None, what: str | None = None) -> None:
    try:
        result = subprocess.run(argv, cwd=cwd, env=env)
    except FileNotFoundError:
        raise WtError(f'{argv[0]} is not on PATH') from None
    if result.returncode != 0:
        raise WtError(f'`{what or " ".join(argv)}` failed in {cwd}')


SESSION_DRIVERS = ('database', 'cookie')


def share_session(main: Path, project: str) -> None:
    """R9, C6: the main api .env keeps its sessions in the database, the table every environment reads.

    `cookie` is what an older wt-up wrote there (it broke the login), so it goes back to `database`.
    """
    path = main / f'api.{project}' / '.env'
    if read_env(path).get('SESSION_DRIVER') != 'cookie':
        return
    text = path.read_bytes().decode('utf-8')
    path.write_bytes(rewrite_env(text, {'SESSION_DRIVER': 'database'}).encode('utf-8'))
    print(f'set SESSION_DRIVER=database in the main api.{project}/.env: log in once more, then every environment shares it')


def serve(valet: str, name: str, folder: Path) -> None:
    """Link `name` to `folder` and secure it, each only when Valet does not have it yet."""
    folder.mkdir(parents=True, exist_ok=True)
    link = valet_home() / 'Sites' / name
    if not (link.is_symlink() and link.resolve() == folder.resolve()):
        run([valet, 'link', name], cwd=folder)
    if not (valet_home() / 'Certificates' / f'{name}.test.crt').is_file():
        run([valet, 'secure', name], cwd=folder)


def copy_database(env: dict[str, str], source: str, target: str) -> bool:
    """`source` copied into a new `target` (utf8mb4); False when `target` exists and is kept."""
    if database_exists(env, target):
        return False
    child_env = mysql_env(env)
    run(mysql_args('mysql', env) + ['-e', f'CREATE DATABASE `{target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'],
        cwd=Path.cwd(), env=child_env, what=f'CREATE DATABASE {target}')
    dump = subprocess.Popen(
        mysql_args('mysqldump', env) + ['--single-transaction', '--no-tablespaces', '--routines', '--triggers', source],
        stdout=subprocess.PIPE, env=child_env,
    )
    load = subprocess.run(mysql_args('mysql', env) + [target], stdin=dump.stdout, env=child_env)
    dump.stdout.close()
    if dump.wait() != 0 or load.returncode != 0:
        subprocess.run(mysql_args('mysql', env) + ['-e', f'DROP DATABASE IF EXISTS `{target}`'], env=child_env)
        raise WtError(f'copying {source} into {target} failed; {target} was dropped again, rerun when MySQL is fine')
    return True


def up_worktree(cwd: Path) -> dict:
    main, wt = worktree_root(cwd)
    project = main.name
    record = read_record(wt) or {}
    lbl = record.get('label') or label(wt.name, project)
    database = record.get('database') or f'{project}_{lbl}'

    # Every check first (R14, R15, R9): nothing calls valet or mysql before they all pass.
    env_path = main / f'api.{project}' / '.env'
    if not env_path.is_file():
        raise WtError(f'api.{project}/.env is missing in the main checkout ({main})')
    env = local_db(main, project, database)
    if is_sqlite(env):
        raise WtError(f'api.{project} runs on SQLite (`php artisan serve`): `up` serves through Valet and copies a '
                      f'MySQL database, so it does not apply; serve the worktree with the project\'s own make targets')
    holder = label_holder(main, lbl, skip=wt)
    if holder is not None:
        raise WtError(f'the label {lbl} is already the worktree {holder}\'s')
    domain = f'{project}.test'
    if env.get('SESSION_DOMAIN') != f'.{domain}':
        raise WtError(f'the main api.{project}/.env needs SESSION_DOMAIN=.{domain} so one login reaches every environment')
    if (env.get('SESSION_DRIVER') or 'database') not in SESSION_DRIVERS:
        raise WtError(f'the main api.{project}/.env needs SESSION_DRIVER=database: every environment reads its sessions table')
    valet = valet_trusted()

    ports = allocate_ports(wt)
    record = {
        'id': record.get('id') or wt.name,
        'label': lbl,
        'branch': git_out(wt, 'branch', '--show-current'),
        'hosts': {'api': f'api.{lbl}.{domain}', 'app': f'app.{lbl}.{domain}'},
        'ports': ports,
        'database': database,
        'created_at': record.get('created_at') or datetime.now().astimezone().isoformat(timespec='seconds'),
    }
    (wt / RECORD).write_text(json.dumps(record, indent=2) + '\n')

    share_session(main, project)
    serve(valet, f'api.{lbl}.{project}', wt / f'api.{project}')
    isolate_like_main(valet, project, f'api.{lbl}.{project}', wt / f'api.{project}')
    serve(valet, f'app.{lbl}.{project}', wt / f'app.{project}' / 'dist')
    if copy_database(env, project, database):
        print(f'copied the local {project} database into {database}')
    else:
        print(f'kept the existing {database}')
    write_env_files(main, wt, project, lbl, ports['vite'])
    run(['php', 'artisan', 'migrate', '--force'], cwd=wt / f'api.{project}')
    print(f'up: https://{record["hosts"]["app"]}:{ports["vite"]} (API https://{record["hosts"]["api"]}); '
          f'start the app with `make wt-app`, then open it once as '
          f'https://{record["hosts"]["app"]}:{ports["vite"]}/?qa-session=1 to pick up the login (C6)')
    return record


MAX_VITE = 4


def listening_ports() -> set[int]:
    try:
        result = subprocess.run(['lsof', '-nP', '-iTCP', '-sTCP:LISTEN', '-Fn'], capture_output=True, text=True)
    except FileNotFoundError:
        return set()
    return {int(m.group(1)) for m in re.finditer(r'^n.*:(\d+)$', result.stdout, re.MULTILINE)}


def vite_servers() -> int:
    """Listening ports in the Vite half (slots 0-99) of every claimed block."""
    try:
        blocks = json.loads(port_file().read_text()).get('blocks', {})
    except (OSError, ValueError):
        return 0
    listening = listening_ports()
    return sum(1 for base in blocks.values() for port in listening if int(base) <= port < int(base) + E2E_OFFSET)


def run_app(cwd: Path) -> int:
    main, wt = worktree_root(cwd)
    project = main.name
    ports = (read_record(wt) or {}).get('ports') or {}
    app = wt / f'app.{project}'
    if not isinstance(ports.get('vite'), int) or not (app / '.env').is_file():
        raise WtError('this worktree has no environment yet; run `make wt-up` first')
    if is_listening(ports['vite']):
        raise WtError(f'port {ports["vite"]} is already listening; its Vite runs already, or stop what holds it')
    count = vite_servers()
    if count > MAX_VITE:
        print(f'warning: {count} Vite servers are running in the port blocks (more than {MAX_VITE}); stop the ones you are done with')
    sys.stdout.flush()
    try:
        argv = under_node(['npm', 'run', 'dev'], app, wt)
        return subprocess.run(argv, cwd=app, env={**os.environ, 'E2E_PORT': str(ports['e2e'])}).returncode
    except FileNotFoundError:
        raise WtError('npm is not on PATH') from None
    except KeyboardInterrupt:
        return 0


# --- down / gc ---------------------------------------------------------------------------------

QUIET_MINUTES = 30
IDLE_DAYS = 7
# Ignored files `down` may delete with the worktree: rebuilt by an install, a build or the app itself.
REGEN_DIRS = {'vendor', 'node_modules', 'dist', '__pycache__', '.phpunit.cache', '.pytest_cache', '.vite',
              'test-results', 'playwright-report', 'coverage'}
REGEN_FILES = {RECORD, '.phpunit.result.cache', '.eslintcache', '.DS_Store', 'components.d.ts', 'auto-imports.d.ts'}
REGEN_PATHS = (('storage', 'framework'), ('storage', 'logs'), ('bootstrap', 'cache'), ('.claude', 'scheduled_tasks.lock'))


def projects_dir() -> Path:
    return Path(os.environ.get('WT_PROJECTS_DIR', Path.home() / '.claude' / 'projects'))


def regenerable(rel: str) -> bool:
    parts = rel.rstrip('/').split('/')
    name = parts[-1]
    if any(part in REGEN_DIRS for part in parts) or name in REGEN_FILES:
        return True
    if name.startswith('.env') or name.endswith('.tsbuildinfo'):
        return True
    return any(tuple(parts[i:i + len(seq)]) == seq for seq in REGEN_PATHS for i in range(len(parts)))


def inside(cwd: str, wt: Path) -> bool:
    cwd = os.path.normpath(cwd)
    return cwd == str(wt) or cwd.startswith(str(wt) + os.sep)


def last_session(wt: Path, since: float) -> float | None:
    """The newest mtime after `since` of a transcript (subagents' included) with a record whose cwd is in `wt`.

    The records' `cwd` decides, not the project folder's slug, which a worktree shares with others.
    """
    root = projects_dir()
    if not root.is_dir():
        return None
    latest = None
    for path in root.rglob('*.jsonl'):
        try:
            mtime = path.stat().st_mtime
        except OSError:
            continue
        if mtime < since or (latest is not None and mtime <= latest):
            continue
        try:
            with open(path, encoding='utf-8', errors='replace') as handle:
                for line in handle:
                    if str(wt) not in line:
                        continue
                    try:
                        cwd = json.loads(line).get('cwd')
                    except (ValueError, AttributeError):
                        continue
                    if isinstance(cwd, str) and inside(cwd, wt):
                        latest = mtime
                        break
        except OSError:
            continue
    return latest


def pull_requests(main: Path, branch: str) -> list[dict]:
    """The branch's PRs on GitHub (`gh`, read-only): number, state (OPEN/CLOSED/MERGED), mergedAt."""
    argv = ['gh', 'pr', 'list', '--head', branch, '--state', 'all', '--json', 'number,state,mergedAt']
    try:
        result = subprocess.run(argv, cwd=main, capture_output=True, text=True)
    except FileNotFoundError:
        raise WtError('gh is not on PATH, so I cannot see whether its PR merged') from None
    if result.returncode != 0:
        raise WtError(f'gh pr list failed, so I cannot see whether its PR merged: {result.stderr.strip()}')
    try:
        prs = json.loads(result.stdout or '[]')
    except ValueError:
        raise WtError('gh pr list answered something that is not JSON') from None
    return prs if isinstance(prs, list) else []


def find_worktree(main: Path, wt_id: str) -> Path:
    """The worktree of this repo whose record id, or folder name, is `wt_id` (never the main checkout)."""
    if not ID_RE.fullmatch(wt_id):
        raise WtError(f'"{wt_id}" is not a plain worktree name')
    for path in worktree_paths(main):
        if path == main:
            continue
        if (read_record(path) or {}).get('id') == wt_id or path.name == wt_id:
            return path
    raise WtError(f'{wt_id}: no worktree of this repo has that ID or folder name')


class Teardown:
    """What `down` would undo for one worktree, worked out with no side effect but `git fetch`."""

    def __init__(self, main: Path, wt: Path) -> None:
        self.main, self.wt = main, wt
        self.project = main.name
        record = read_record(wt) or {}
        self.id = record.get('id') or wt.name
        self.label = record.get('label') or label(wt.name, self.project)
        self.database = record.get('database') or f'{self.project}_{self.label}'
        self.vite = (record.get('ports') or {}).get('vite')
        self.branch = ''
        self.env: dict[str, str] = {}
        self.valet: str | None = None
        self.valet_steps: list[list[str]] = []
        self.skipped: list[str] = []
        self.prs: list[dict] | None = None

    def blocker(self, discard: bool = False) -> str | None:
        """The first R11 check that fails, named, or None when the worktree may go.

        `discard` skips the two checks that only mean "not merged" (merged PR, nothing ahead of
        origin/main) and refuses an OPEN PR instead; every other check runs, in the same order.
        """
        holder = label_holder(self.main, self.label, skip=self.wt)
        if holder is not None:
            return f'its label {self.label} is also {holder}\'s, so {self.database} may be that worktree\'s database'
        try:
            env = local_db(self.main, self.project, self.database)
        except WtError as error:
            return str(error)
        if env is None:
            return f'api.{self.project}/.env is missing in the main checkout, so its database cannot be checked local'
        self.env = env
        self.branch = git_out(self.wt, 'branch', '--show-current')
        if not self.branch:
            return 'it is not on a branch (detached HEAD)'
        try:
            self.prs = pull_requests(self.main, self.branch)
        except WtError as error:
            return str(error)
        if discard:
            opened = [pr for pr in self.prs if pr.get('state') == 'OPEN']
            if opened:
                number = opened[0].get('number')
                return f'PR #{number} is open: close PR #{number} first'
        else:
            merged = [pr for pr in self.prs if pr.get('state') == 'MERGED']
            if not merged:
                return f'no merged PR for {self.branch} (gh pr list --head {self.branch} --state merged)'
            ahead = git_out(self.main, 'rev-list', '--count', f'origin/main..refs/heads/{self.branch}')
            if ahead != '0':
                return f'{self.branch} has {ahead} commit(s) not in origin/main (git rev-list origin/main..{self.branch})'
        status = git_out(self.wt, 'status', '--porcelain', '-z', '--ignored=matching', strip=False)
        entries = [entry for entry in status.split('\0') if entry]
        dirty = [entry[3:] for entry in entries if not entry.startswith('!! ')]
        if dirty:
            return f'git status --porcelain is not empty ({dirty[0]}{" …" if len(dirty) > 1 else ""})'
        kept = [entry[3:] for entry in entries if entry.startswith('!! ') and not regenerable(entry[3:])]
        if kept:
            return f'ignored file(s) outside the regenerable set would be lost: {", ".join(kept[:3])}'
        session = last_session(self.wt, datetime.now().timestamp() - QUIET_MINUTES * 60)
        if session is not None:
            minutes = int((datetime.now().timestamp() - session) // 60)
            return f'a session in it changed {minutes} min ago (under {QUIET_MINUTES} min)'
        if isinstance(self.vite, int) and is_listening(self.vite):
            return f'its Vite port {self.vite} is listening (stop `make wt-app` first)'
        self.plan_valet()
        if self.valet_steps:
            try:
                self.valet = valet_trusted()
            except WtError as error:
                return str(error)
        return None

    def plan_valet(self) -> None:
        """Undo for each site only what `wt-up` left: isolation, certificate, link (one pointing elsewhere is not ours)."""
        home = valet_home()
        for name in (f'api.{self.label}.{self.project}', f'app.{self.label}.{self.project}'):
            link = home / 'Sites' / name
            if link.is_symlink() and not inside(os.path.realpath(link), self.wt):
                self.skipped.append(name)
                continue
            if isolated_php(home / 'Nginx' / f'{name}.test') is not None:
                self.valet_steps.append(['unisolate', f'--site={name}'])
            if (home / 'Certificates' / f'{name}.test.crt').is_file():
                self.valet_steps.append(['unsecure', name])
            if link.is_symlink():
                self.valet_steps.append(['unlink', name])

    def what(self) -> str:
        """What `run` removed besides the worktree and its branch, for the closing line."""
        return '' if is_sqlite(self.env) else f'Valet sites, {self.database},'

    def run(self) -> None:
        for name in self.skipped:
            print(f'left the Valet site {name} alone: it links outside this worktree')
        for step in self.valet_steps:
            run([self.valet, *step], cwd=self.main)
        if not is_sqlite(self.env):
            run(mysql_args('mysql', self.env) + ['-e', f'DROP DATABASE IF EXISTS `{self.database}`'],
                cwd=self.main, env=mysql_env(self.env), what=f'DROP DATABASE {self.database}')
        git_out(self.main, 'worktree', 'remove', str(self.wt))
        # -D, not -d: `down` just showed the branch in origin/main (the local main may lag behind it);
        # `discard` printed the line that restores it.
        git_out(self.main, 'branch', '-D', self.branch)


def fetch(main: Path) -> None:
    result = subprocess.run(['git', 'fetch', '--quiet', '--prune', 'origin'], cwd=main, capture_output=True, text=True)
    if result.returncode != 0:
        raise WtError(f'git fetch origin failed, so origin/main may be stale: {result.stderr.strip()}')


def refuse_main(main: Path, wt_id: str) -> None:
    if wt_id == main.name and not any(p.name == wt_id for p in worktree_paths(main) if p != main):
        raise WtError(f'{wt_id} is the main checkout; it is never torn down')


def down_worktree(cwd: Path, wt_id: str) -> None:
    main = main_checkout(cwd)
    refuse_main(main, wt_id)
    wt = find_worktree(main, wt_id)
    fetch(main)
    teardown = Teardown(main, wt)
    reason = teardown.blocker()
    if reason is not None:
        raise WtError(f'{teardown.id} stays: {reason}')
    teardown.run()
    print(f'torn down: {teardown.id} ({teardown.what()} the worktree and {teardown.branch})')


def discard_worktree(cwd: Path, wt_id: str, confirm: str | None) -> None:
    """`down` for an unmerged worktree: a dry run unless `confirm` repeats the ID; local only, never the remote."""
    main = main_checkout(cwd)
    refuse_main(main, wt_id)
    wt = find_worktree(main, wt_id)
    fetch(main)
    teardown = Teardown(main, wt)
    reason = teardown.blocker(discard=True)
    if reason is not None:
        raise WtError(f'{teardown.id} stays: {reason}')
    tip = git_out(main, 'rev-parse', f'refs/heads/{teardown.branch}')
    if confirm != wt_id:
        print(f'dry run: `make wt-discard ID={wt_id}` would remove, with nothing pushed or closed on GitHub:')
        for name in teardown.skipped:
            print(f'  leave the Valet site {name} alone (it links outside this worktree)')
        for step in teardown.valet_steps:
            print(f'  valet {" ".join(step)}')
        if not is_sqlite(teardown.env):
            print(f'  DROP DATABASE {teardown.database}')
        print(f'  the worktree folder {wt}')
        print(f'  the local branch {teardown.branch} (at {tip})')
        print('to discard it, run:')
        print(f'  make wt-discard ID={wt_id} CONFIRM={wt_id}')
        return
    print(f'{teardown.branch} was at {tip}; restore it with: git branch {teardown.branch} {tip}')
    teardown.run()
    print(f'discarded: {teardown.id} ({teardown.what()} the worktree and {teardown.branch})')


def left_behind(wt: Path, prs: list[dict] | None) -> str | None:
    """Why a worktree is left behind (its PR closed unmerged, or 7 days without a commit or session)."""
    states = {pr.get('state') for pr in prs or []}
    if prs and 'MERGED' not in states and 'OPEN' not in states:
        return f'PR #{prs[0].get("number")} closed unmerged'
    now = datetime.now().timestamp()
    try:
        committed = int(git_out(wt, 'log', '-1', '--format=%ct', 'HEAD'))
    except (WtError, ValueError):
        return None
    if now - committed < IDLE_DAYS * 86400 or last_session(wt, now - IDLE_DAYS * 86400) is not None:
        return None
    return f'no commit or session for {int((now - committed) // 86400)} days'


def gc_worktrees(cwd: Path) -> int:
    main = main_checkout(cwd)
    fetch(main)
    failed = 0
    for wt in worktree_paths(main):
        if wt == main:
            continue
        if not wt.is_dir():
            print(f'missing: {wt} (its folder is gone; `git worktree prune` forgets it)')
            continue
        try:
            teardown = Teardown(main, wt)
        except WtError as error:
            print(f'kept: {wt.name} — {error}')
            continue
        try:
            reason = teardown.blocker()
        except WtError as error:
            reason = str(error)
        if reason is None:
            try:
                teardown.run()
            except WtError as error:
                failed += 1
                print(f'failed: {teardown.id} — {error}')
                continue
            print(f'torn down: {teardown.id} ({teardown.branch})')
            continue
        why = left_behind(wt, teardown.prs)
        if why is not None:
            print(f'left behind: {teardown.id} — {why}; never torn down on its own '
                  f'(`make wt-discard ID={teardown.id}` lists what throwing it away removes)')
        else:
            print(f'kept: {teardown.id} — {reason}')
    return 1 if failed else 0


# --- CLI ---------------------------------------------------------------------------------------

def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__.split('\n', 1)[0])
    sub = parser.add_subparsers(dest='command', required=True)
    p_label = sub.add_parser('label', help='print the label of a worktree name or ID')
    p_label.add_argument('name')
    p_new = sub.add_parser('new', help='create .claude/worktrees/<id> from origin/main with its deps')
    p_new.add_argument('id')
    p_new.add_argument('--branch', help='an existing branch to check out, or a new one to make')
    p_new.add_argument('--slug', help='kebab-case words for the default branch feature/<id>-<slug>')
    sub.add_parser('up', help='serve this worktree: Valet sites, its own DB copy, env files, migrate')
    sub.add_parser('app', help="start this worktree's Vite dev server on its own port")
    p_down = sub.add_parser('down', help='tear a merged, clean, quiet worktree down (Valet, DB copy, folder, branch)')
    p_down.add_argument('id')
    p_discard = sub.add_parser('discard', help='tear an UNMERGED worktree down; a dry run unless --confirm repeats the ID')
    p_discard.add_argument('id')
    p_discard.add_argument('--confirm', help='the same ID again: only then is anything removed')
    sub.add_parser('gc', help='down on every worktree that passes; list the left-behind ones with why')
    sub.add_parser('port', help="print this checkout's e2e port (make e2e exports it as E2E_PORT)")
    args = parser.parse_args(argv)
    try:
        if args.command == 'label':
            print(label(args.name, main_checkout(Path.cwd()).name))
        elif args.command == 'new':
            new_worktree(Path.cwd(), args.id, args.branch or None, args.slug or None)
        elif args.command == 'up':
            up_worktree(Path.cwd())
        elif args.command == 'app':
            return run_app(Path.cwd())
        elif args.command == 'down':
            down_worktree(Path.cwd(), args.id)
        elif args.command == 'discard':
            discard_worktree(Path.cwd(), args.id, args.confirm)
        elif args.command == 'gc':
            return gc_worktrees(Path.cwd())
        elif args.command == 'port':
            print(e2e_port(Path.cwd()))
    except WtError as error:
        print(f'worktree: {error}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
