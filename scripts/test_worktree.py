#!/usr/bin/env python3
"""Tests for scripts/worktree.py: labels, port blocks, the environment's .env files, new, up, app, down, discard, gc, port.

Everything runs against throwaway git repos (with a bare `origin`) and files under a temp folder.
The machine paths the script touches are overridden through the environment (`WT_PORT_FILE`,
`WT_VALET_HOME`, `WT_PROJECTS_DIR`), and `lsof`, `mysql`, `mysqldump`, `sudo`, `valet`, `gh`,
`php`, `composer` and `npm` are fake executables placed first on PATH that log every call to
`FAKE_LOG`: `lsof` "sees" a port listening when that port is in `FAKE_LISTEN`, `mysql` "has" the
databases named in `FAKE_DBS`, `gh` answers each branch's PRs from `FAKE_GH_PRS`. The repo folder is named after an invented project
(`boanerges`, `chapaja`) because the script derives the project and its label prefix from it.

Run: python3 scripts/test_worktree.py  (or `make wt-test`)
"""
from __future__ import annotations

import fcntl
import io
import json
import os
import re
import stat
import subprocess
import sys
import tempfile
import time
import unittest
from contextlib import redirect_stdout
from pathlib import Path
from unittest import mock

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))

import worktree  # noqa: E402

GIT_ENV = {
    'GIT_AUTHOR_NAME': 'Test Person',
    'GIT_AUTHOR_EMAIL': 'test@example.com',
    'GIT_COMMITTER_NAME': 'Test Person',
    'GIT_COMMITTER_EMAIL': 'test@example.com',
    'GIT_CONFIG_NOSYSTEM': '1',
}

INVENTED_BREW = '/invented/brew'

FAKE_LSOF = """#!/bin/sh
echo "lsof $*" >> "$FAKE_LOG"
for arg in "$@"; do
  case "$arg" in
    -iTCP:*) port="${arg#-iTCP:}" ;;
  esac
done
if [ -z "$port" ]; then
  for p in $FAKE_LISTEN; do printf 'p4242\nn*:%s\n' "$p"; done
  exit 0
fi
for p in $FAKE_LISTEN; do
  if [ "$p" = "$port" ]; then echo 4242; exit 0; fi
done
exit 1
"""


def git(cwd: Path, *args: str) -> str:
    return subprocess.run(
        ['git', *args], cwd=cwd, check=True, capture_output=True, text=True,
        env={**os.environ, **GIT_ENV},
    ).stdout.strip()


class Sandbox(unittest.TestCase):
    """A temp folder, a fake `lsof` first on PATH and a port file of our own."""

    def setUp(self) -> None:
        self._tmp = tempfile.TemporaryDirectory()
        self.tmp = Path(self._tmp.name).resolve()
        bin_dir = self.tmp / 'bin'
        bin_dir.mkdir()
        lsof = bin_dir / 'lsof'
        lsof.write_text(FAKE_LSOF)
        lsof.chmod(lsof.stat().st_mode | stat.S_IXUSR)
        self.log = self.tmp / 'calls.log'
        self.port_file = self.tmp / 'config' / 'port-blocks.json'
        patcher = mock.patch.dict(os.environ, {
            'PATH': f'{bin_dir}{os.pathsep}{os.environ["PATH"]}',
            'FAKE_LOG': str(self.log),
            'FAKE_LISTEN': '',
            'WT_PORT_FILE': str(self.port_file),
            'HOMEBREW_PREFIX': INVENTED_BREW,
            **GIT_ENV,
        })
        patcher.start()
        self.addCleanup(patcher.stop)
        self.addCleanup(self._tmp.cleanup)

    def assert_trust_hint(self, stderr: str) -> None:
        # `valet trust` asks for the sudo password, which an `!` command cannot read, and the owner's
        # zsh wraps `valet` in a broken function: the line names a real terminal and the binary's path.
        self.assertIn(f'{INVENTED_BREW}/bin/valet trust', stderr)
        self.assertIn('real terminal', stderr)
        self.assertNotIn('! valet trust', stderr)

    def listen(self, *ports: int) -> None:
        os.environ['FAKE_LISTEN'] = ' '.join(str(p) for p in ports)

    def make_repo(self, name: str = 'boanerges') -> Path:
        repo = self.tmp / name
        repo.mkdir()
        git(repo, 'init', '-q', '-b', 'main')
        (repo / 'README.md').write_text('invented\n')
        git(repo, 'add', '.')
        git(repo, 'commit', '-q', '-m', 'init')
        return repo

    def add_worktree(self, repo: Path, wt_id: str, record: dict | None = None) -> Path:
        path = repo / '.claude' / 'worktrees' / wt_id
        git(repo, 'worktree', 'add', '-q', '-b', f'feature/{wt_id}', str(path), 'main')
        if record is not None:
            (path / '.worktree.json').write_text(json.dumps(record))
        return path


class LabelTest(unittest.TestCase):
    def test_a4_feature_id_and_other_names(self) -> None:
        self.assertEqual(worktree.label('boanerges-7', 'boanerges'), 'b7')
        self.assertEqual(worktree.label('permissions-audit', 'boanerges'), 'permissionsaudit')
        self.assertEqual(worktree.label('Fix_QA', 'boanerges'), 'fixqa')

    def test_prefix_comes_from_the_project(self) -> None:
        self.assertEqual(worktree.label('chapaja-12', 'chapaja'), 'c12')
        self.assertEqual(worktree.label('boanerges-12', 'chapaja'), 'boanerges12')

    def test_a_name_with_no_letter_or_digit_is_refused(self) -> None:
        with self.assertRaises(worktree.WtError):
            worktree.label('__--', 'boanerges')

    def test_cli_derives_the_project_from_the_main_checkout(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            repo = Path(tmp) / 'chapaja'
            repo.mkdir()
            git(repo, 'init', '-q', '-b', 'main')
            out = subprocess.run(
                [sys.executable, str(HERE / 'worktree.py'), 'label', 'chapaja-4'],
                cwd=repo, capture_output=True, text=True,
            )
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertEqual(out.stdout.strip(), 'c4')


class PortBlockTest(Sandbox):
    def test_a5_blocks_of_200_from_6000_per_project(self) -> None:
        self.assertEqual(worktree.claim_block('boanerges', self.port_file), 6000)
        self.assertEqual(worktree.claim_block('chapaja', self.port_file), 6200)
        self.assertEqual(worktree.claim_block('boanerges', self.port_file), 6000)
        saved = json.loads(self.port_file.read_text())
        self.assertEqual(saved['blocks'], {'boanerges': 6000, 'chapaja': 6200})

    def test_a_freed_gap_is_reused_before_a_new_block(self) -> None:
        self.port_file.parent.mkdir(parents=True)
        self.port_file.write_text(json.dumps({'blocks': {'chapaja': 6200}}))
        self.assertEqual(worktree.claim_block('boanerges', self.port_file), 6000)

    def test_a_claim_waits_for_the_lock_and_reads_what_the_holder_wrote(self) -> None:
        # Hold the lock as another `wt-up` would, write a block while holding it, then release:
        # the waiting claim must see that block and take the next one, not race past the lock.
        self.port_file.parent.mkdir(parents=True)
        code = (
            'import sys; sys.path.insert(0, sys.argv[1]); import worktree, pathlib; '
            'print(worktree.claim_block("chapaja", pathlib.Path(sys.argv[2])))'
        )
        with open(self.port_file, 'a+', encoding='utf-8') as holder:
            fcntl.flock(holder, fcntl.LOCK_EX)
            proc = subprocess.Popen(
                [sys.executable, '-c', code, str(HERE), str(self.port_file)],
                stdout=subprocess.PIPE, text=True,
            )
            time.sleep(0.5)
            self.assertIsNone(proc.poll(), 'the claim ran without waiting for the lock')
            holder.write(json.dumps({'blocks': {'boanerges': 6000}}))
            holder.flush()
        self.assertEqual(int(proc.communicate(timeout=10)[0]), 6200)
        self.assertEqual(json.loads(self.port_file.read_text())['blocks'], {'boanerges': 6000, 'chapaja': 6200})

    def test_unreadable_port_file_is_refused_not_overwritten(self) -> None:
        self.port_file.parent.mkdir(parents=True)
        self.port_file.write_text('{not json')
        with self.assertRaises(worktree.WtError):
            worktree.claim_block('boanerges', self.port_file)
        self.assertEqual(self.port_file.read_text(), '{not json')


class SlotTest(Sandbox):
    def test_a5_feature_gets_base_plus_n_and_e2e_base_plus_100_plus_n(self) -> None:
        repo = self.make_repo()
        wt = self.add_worktree(repo, 'boanerges-7')
        ports = worktree.allocate_ports(wt)
        self.assertEqual((ports['vite'], ports['e2e'], ports['slot']), (6007, 6107, 7))

    def test_a5_feature_whose_e2e_port_listens_is_refused(self) -> None:
        repo = self.make_repo()
        wt = self.add_worktree(repo, 'boanerges-7')
        self.listen(6107)
        with self.assertRaisesRegex(worktree.WtError, '6107'):
            worktree.allocate_ports(wt)

    def test_a5_non_feature_skips_a_slot_whose_e2e_port_listens(self) -> None:
        repo = self.make_repo()
        wt = self.add_worktree(repo, 'permissions-audit')
        self.listen(6180)
        self.assertEqual(worktree.allocate_ports(wt)['vite'], 6081)

    def test_a5_non_feature_takes_6080_then_6081_when_6080_listens(self) -> None:
        repo = self.make_repo()
        wt = self.add_worktree(repo, 'permissions-audit')
        self.assertEqual(worktree.allocate_ports(wt)['vite'], 6080)
        self.listen(6080)
        self.assertEqual(worktree.allocate_ports(wt)['vite'], 6081)
        self.assertIn('-iTCP:6080', self.log.read_text())

    def test_a_slot_held_by_another_worktrees_record_is_skipped(self) -> None:
        repo = self.make_repo()
        self.add_worktree(repo, 'fix-a', {'id': 'fix-a', 'ports': {'slot': 80, 'vite': 6080, 'e2e': 6180}})
        wt = self.add_worktree(repo, 'fix-b')
        self.assertEqual(worktree.allocate_ports(wt)['slot'], 81)

    def test_collision_feature_slot_held_by_another_record_is_refused(self) -> None:
        repo = self.make_repo()
        self.add_worktree(repo, 'by-hand', {'id': 'by-hand', 'ports': {'slot': 7, 'vite': 6007, 'e2e': 6107}})
        wt = self.add_worktree(repo, 'boanerges-7')
        with self.assertRaisesRegex(worktree.WtError, 'by-hand'):
            worktree.allocate_ports(wt)

    def test_feature_port_already_listening_is_refused(self) -> None:
        repo = self.make_repo()
        wt = self.add_worktree(repo, 'boanerges-7')
        self.listen(6007)
        with self.assertRaisesRegex(worktree.WtError, '6007'):
            worktree.allocate_ports(wt)

    def test_own_record_keeps_its_slot_even_while_its_vite_listens(self) -> None:
        repo = self.make_repo()
        wt = self.add_worktree(repo, 'fix-a', {'id': 'fix-a', 'ports': {'slot': 83, 'vite': 6083, 'e2e': 6183}})
        self.listen(6083)
        self.assertEqual(worktree.allocate_ports(wt)['vite'], 6083)

    def test_feature_number_80_or_more_is_refused(self) -> None:
        repo = self.make_repo()
        wt = self.add_worktree(repo, 'boanerges-80')
        with self.assertRaisesRegex(worktree.WtError, '80'):
            worktree.allocate_ports(wt)

    def test_all_slots_80_to_99_used_is_refused(self) -> None:
        repo = self.make_repo()
        for s in range(80, 90):
            self.add_worktree(repo, f'fix-{s}', {'id': f'fix-{s}', 'ports': {'slot': s}})
        self.listen(*range(6090, 6100))
        wt = self.add_worktree(repo, 'one-more')
        with self.assertRaisesRegex(worktree.WtError, '80.99'):
            worktree.allocate_ports(wt)

    def test_another_project_uses_its_own_block(self) -> None:
        self.assertEqual(worktree.claim_block('boanerges', self.port_file), 6000)
        repo = self.make_repo('chapaja')
        wt = self.add_worktree(repo, 'chapaja-3')
        ports = worktree.allocate_ports(wt)
        self.assertEqual((ports['base'], ports['vite'], ports['e2e']), (6200, 6203, 6303))



class PortCommandTest(Sandbox):
    """`worktree.py port` (make e2e): this checkout's e2e port (R9)."""

    def run_port(self, cwd: Path) -> subprocess.CompletedProcess:
        return subprocess.run(
            [sys.executable, str(HERE / 'worktree.py'), 'port'], cwd=cwd, capture_output=True, text=True,
        )

    def write_blocks(self, blocks: dict) -> None:
        self.port_file.parent.mkdir(parents=True, exist_ok=True)
        self.port_file.write_text(json.dumps({'blocks': blocks}))

    def test_a8_main_checkout_gets_slot_0_of_its_block(self) -> None:
        self.write_blocks({'chapaja': 6000, 'boanerges': 6200})
        repo = self.make_repo()
        out = self.run_port(repo)
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertEqual(out.stdout.strip(), '6300')

    def test_a8_main_checkout_with_boanerges_at_6000_prints_6100(self) -> None:
        self.write_blocks({'boanerges': 6000})
        repo = self.make_repo()
        out = self.run_port(repo)
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertEqual(out.stdout.strip(), '6100')

    def test_a8_from_a_subfolder_of_the_main_checkout_too(self) -> None:
        self.write_blocks({'boanerges': 6000})
        repo = self.make_repo()
        (repo / 'app.boanerges').mkdir()
        out = self.run_port(repo / 'app.boanerges')
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertEqual(out.stdout.strip(), '6100')

    def test_a8_an_empty_port_file_claims_the_block_first(self) -> None:
        self.port_file.parent.mkdir(parents=True)
        self.port_file.write_text('')
        repo = self.make_repo()
        out = self.run_port(repo)
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertEqual(out.stdout.strip(), '6100')
        self.assertEqual(json.loads(self.port_file.read_text())['blocks'], {'boanerges': 6000})

    def test_a8_a_worktree_prints_its_records_e2e_port(self) -> None:
        self.write_blocks({'boanerges': 6000})
        repo = self.make_repo()
        wt = self.add_worktree(repo, 'boanerges-14', {
            'id': 'boanerges-14', 'ports': {'base': 6000, 'slot': 14, 'vite': 6014, 'e2e': 6114},
        })
        out = self.run_port(wt)
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertEqual(out.stdout.strip(), '6114')

    def test_a8_a_record_wins_over_the_computed_slot(self) -> None:
        self.write_blocks({'boanerges': 6000})
        repo = self.make_repo()
        wt = self.add_worktree(repo, 'discard-probe', {
            'id': 'discard-probe', 'ports': {'base': 6000, 'slot': 85, 'vite': 6085, 'e2e': 6185},
        })
        self.assertEqual(self.run_port(wt).stdout.strip(), '6185')

    def test_a_recordless_worktree_gets_its_computed_port_and_no_record_is_written(self) -> None:
        self.write_blocks({'boanerges': 6000})
        repo = self.make_repo()
        wt = self.add_worktree(repo, 'boanerges-14')
        out = self.run_port(wt)
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertEqual(out.stdout.strip(), '6114')
        self.assertFalse((wt / '.worktree.json').exists(), 'port wrote a record')

    def test_a_port_file_that_cannot_be_read_is_refused(self) -> None:
        self.port_file.parent.mkdir(parents=True)
        self.port_file.write_text('not json')
        out = self.run_port(self.make_repo())
        self.assertNotEqual(out.returncode, 0)
        self.assertEqual(out.stdout, '')
        self.assertIn('port-block file', out.stderr)

API_ENV = """APP_NAME=Invented
APP_ENV=local
APP_KEY=base64:INVENTEDKEYinventedkeyINVENTEDKEY0000000000=
APP_URL=https://api.boanerges.test
FRONTEND_URL=https://app.boanerges.test:5187

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=boanerges
DB_USERNAME=root
DB_PASSWORD=invented-db-secret
SESSION_DRIVER=database
SESSION_DOMAIN=.boanerges.test
# a comment that mentions APP_URL=https://elsewhere
SANCTUM_STATEFUL_DOMAINS=app.boanerges.test,app.boanerges.test:5187,localhost:5173
CORS_ALLOWED_ORIGINS=https://app.boanerges.test,https://app.boanerges.test:5187
CORS_ALLOWED_ORIGINS_PATTERNS=#^https?://app\\.boanerges\\.test(:\\d+)?$#
MAIL_PASSWORD="invented mail secret"
"""

APP_ENV = """# invented app env
VITE_API_URL=https://api.boanerges.test/api
VITE_APP_URL=https://app.boanerges.test:5187
VITE_SENTRY=https://invented@sentry.example.com/1
VITE_SENTRY_PROPAGATION_TARGETS="['localhost', 'https://app.boanerges.test:5187']"
VITE_SENTRY_AUTH_TOKEN=invented-sentry-token
VITE_LOCAL_SSL_KEY=".config/valet/Certificates/app.boanerges.test.key"
VITE_LOCAL_SSL_CERT=".config/valet/Certificates/app.boanerges.test.crt"
VITE_ACTIVE_THEME=boanerges
"""

API_KEYS = {
    'APP_URL', 'FRONTEND_URL', 'DB_DATABASE', 'CORS_ALLOWED_ORIGINS',
    'SANCTUM_STATEFUL_DOMAINS', 'CORS_ALLOWED_ORIGINS_PATTERNS',
}
APP_KEYS = {
    'VITE_API_URL', 'VITE_APP_URL', 'VITE_SENTRY_PROPAGATION_TARGETS',
    'VITE_LOCAL_SSL_KEY', 'VITE_LOCAL_SSL_CERT',
}


def changed_keys(before: str, after: str) -> set[str]:
    old, new = before.splitlines(keepends=True), after.splitlines(keepends=True)
    assert len(old) == len(new), 'line count changed'
    return {b.split('=', 1)[0] for a, b in zip(old, new) if a != b}


def value_of(text: str, key: str) -> str:
    return next(line.split('=', 1)[1] for line in text.splitlines() if line.startswith(f'{key}='))


class EnvTest(Sandbox):
    def setUp(self) -> None:
        super().setUp()
        self.main = self.tmp / 'boanerges'
        self.wt = self.main / '.claude' / 'worktrees' / 'boanerges-7'
        for root in (self.main, self.wt):
            (root / 'api.boanerges').mkdir(parents=True)
            (root / 'app.boanerges').mkdir(parents=True)
        (self.main / 'api.boanerges' / '.env').write_text(API_ENV)
        (self.main / 'app.boanerges' / '.env').write_text(APP_ENV)

    def write(self) -> str:
        out = io.StringIO()
        with redirect_stdout(out):
            worktree.write_env_files(self.main, self.wt, 'boanerges', 'b7', 6007)
        return out.getvalue()

    def test_a6_only_the_r8_keys_change(self) -> None:
        self.write()
        api = (self.wt / 'api.boanerges' / '.env').read_text()
        app = (self.wt / 'app.boanerges' / '.env').read_text()
        self.assertTrue(api.endswith('SESSION_TABLE=boanerges.sessions\n'), api[-80:])
        self.assertEqual(changed_keys(API_ENV, api.removesuffix('SESSION_TABLE=boanerges.sessions\n')), API_KEYS)
        self.assertEqual(changed_keys(APP_ENV, app), APP_KEYS)

    def test_c6_sessions_live_in_the_main_databases_table(self) -> None:
        main_env = self.main / 'api.boanerges' / '.env'
        for driver in ('database', 'cookie'):
            main_env.write_text(API_ENV.replace('SESSION_DRIVER=database', f'SESSION_DRIVER={driver}'))
            self.write()
            api = (self.wt / 'api.boanerges' / '.env').read_text()
            self.assertEqual(value_of(api, 'SESSION_DRIVER'), 'database', driver)
            self.assertEqual(value_of(api, 'SESSION_TABLE'), 'boanerges.sessions', driver)

    def test_c6_a_main_session_table_is_qualified_with_the_main_database(self) -> None:
        main_env = self.main / 'api.boanerges' / '.env'
        main_env.write_text(API_ENV + 'SESSION_TABLE=app_sessions\n')
        self.write()
        api = (self.wt / 'api.boanerges' / '.env').read_text()
        self.assertEqual(value_of(api, 'SESSION_TABLE'), 'boanerges.app_sessions')

    def test_a6_values_name_only_the_environments_own_origin(self) -> None:
        self.write()
        api = (self.wt / 'api.boanerges' / '.env').read_text()
        app = (self.wt / 'app.boanerges' / '.env').read_text()
        self.assertEqual(value_of(api, 'CORS_ALLOWED_ORIGINS'), 'https://app.b7.boanerges.test:6007')
        self.assertEqual(value_of(api, 'DB_DATABASE'), 'boanerges_b7')
        self.assertEqual(value_of(api, 'APP_URL'), 'https://api.b7.boanerges.test')
        self.assertEqual(value_of(api, 'FRONTEND_URL'), 'https://app.b7.boanerges.test:6007')
        self.assertEqual(value_of(api, 'SANCTUM_STATEFUL_DOMAINS'), 'app.b7.boanerges.test:6007')
        self.assertEqual(value_of(api, 'CORS_ALLOWED_ORIGINS_PATTERNS'), '')
        self.assertEqual(value_of(app, 'VITE_API_URL'), 'https://api.b7.boanerges.test/api')
        self.assertEqual(value_of(app, 'VITE_APP_URL'), 'https://app.b7.boanerges.test:6007')
        self.assertEqual(
            value_of(app, 'VITE_SENTRY_PROPAGATION_TARGETS'),
            '"[\'localhost\', \'https://app.b7.boanerges.test:6007\']"',
        )
        self.assertEqual(value_of(app, 'VITE_LOCAL_SSL_KEY'), '".config/valet/Certificates/app.b7.boanerges.test.key"')
        self.assertEqual(value_of(app, 'VITE_LOCAL_SSL_CERT'), '".config/valet/Certificates/app.b7.boanerges.test.crt"')

    def test_a6_no_secret_reaches_stdout(self) -> None:
        out = self.write()
        self.assertTrue(out, 'it should say what it wrote')
        for secret in ('INVENTEDKEY', 'invented-db-secret', 'invented mail secret', 'invented-sentry-token'):
            self.assertNotIn(secret, out)

    def test_crlf_lines_and_a_missing_key_are_handled(self) -> None:
        source = API_ENV.replace('FRONTEND_URL=https://app.boanerges.test:5187\n', '').replace('\n', '\r\n')
        (self.main / 'api.boanerges' / '.env').write_bytes(source.encode())
        self.write()
        api = (self.wt / 'api.boanerges' / '.env').read_bytes().decode()
        self.assertTrue(api.startswith(source.split('APP_URL=')[0]))
        self.assertIn('DB_PASSWORD=invented-db-secret\r\n', api)
        self.assertIn('APP_URL=https://api.b7.boanerges.test\r\n', api)
        self.assertTrue(api.endswith('FRONTEND_URL=https://app.b7.boanerges.test:6007\r\nSESSION_TABLE=boanerges.sessions\r\n'))

    def test_a_missing_source_env_is_refused(self) -> None:
        (self.main / 'app.boanerges' / '.env').unlink()
        with self.assertRaisesRegex(worktree.WtError, 'app.boanerges/.env'):
            self.write()


FAKE_LOGGER = """#!/bin/sh
echo "$(basename "$0") $PWD $* E2E_PORT=${E2E_PORT:-}" >> "$FAKE_LOG"
"""

# Answers the "does the database exist" query with every name of FAKE_DBS the query mentions,
# and logs whether the password came through the environment (never its value).
FAKE_MYSQL = """#!/bin/sh
echo "mysql $* pwd=${MYSQL_PWD:+env}" >> "$FAKE_LOG"
case "$*" in
  *" -e "*) ;;
  *) echo "import: $(cat)" >> "$FAKE_LOG" ;;
esac
for db in $FAKE_DBS; do
  case "$*" in *"'$db'"*) echo "$db" ;; esac
done
exit 0
"""

# Prints a fake dump; fails when FAKE_DUMP_FAIL is set.
FAKE_MYSQLDUMP = """#!/bin/sh
echo "mysqldump $* pwd=${MYSQL_PWD:+env}" >> "$FAKE_LOG"
if [ -n "$FAKE_DUMP_FAIL" ]; then echo "mysqldump: invented failure" >&2; exit 2; fi
echo "-- invented dump"
"""

# Like the real sudo with `listpw=any`: `-n -l` needs no password once any NOPASSWD rule exists
# (FAKE_SUDO_OK=1) and then prints the rules (FAKE_SUDO_LIST); `-n -l <cmd>` passes for any command
# the `(ALL) ALL` rule allows, password or not. Runs nothing else.
FAKE_SUDO = """#!/bin/sh
echo "sudo $*" >> "$FAKE_LOG"
if [ "$FAKE_SUDO_OK" != "1" ]; then echo "sudo: a password is required" >&2; exit 1; fi
if [ "$*" = "-n -l" ]; then printf '%s\\n' "$FAKE_SUDO_LIST"; fi
exit 0
"""

# Composer's proxy (`~/.composer/vendor/bin/valet`): first on PATH, but Valet re-execs sudo with the
# real script's path, which the sudoers rule does not cover, so it would ask for a password.
FAKE_VALET_PROXY = """#!/bin/sh
echo "proxy-valet $PWD $*" >> "$FAKE_LOG"
exit 1
"""

# `gh pr list --head <branch> …`: answers with the PRs FAKE_GH_PRS (JSON, branch -> list) gives
# that branch; fails when FAKE_GH_FAIL is set.
FAKE_GH = f"""#!{sys.executable}
import json, os, sys
with open(os.environ['FAKE_LOG'], 'a') as log:
    log.write('gh ' + ' '.join(sys.argv[1:]) + '\\n')
if os.environ.get('FAKE_GH_FAIL'):
    sys.stderr.write('gh: invented failure\\n')
    sys.exit(1)
args = sys.argv[1:]
branch = args[args.index('--head') + 1]
print(json.dumps(json.loads(os.environ.get('FAKE_GH_PRS') or '{{}}').get(branch, [])))
"""

# A `cp` without `-c` (Linux): refuses the clone flag, copies like the real one otherwise.
FAKE_CP_NO_CLONE = """#!/bin/sh
for arg in "$@"; do
  if [ "$arg" = "-c" ]; then echo "cp: invalid option -- 'c'" >&2; exit 1; fi
done
exec /bin/cp "$@"
"""


class RepoSandbox(Sandbox):
    """A main checkout with a bare origin, installed deps, its local .env files and every stub."""

    def setUp(self) -> None:
        super().setUp()
        bin_dir = self.tmp / 'bin'
        stubs = (('composer', FAKE_LOGGER), ('npm', FAKE_LOGGER), ('mysql', FAKE_MYSQL),
                 ('mysqldump', FAKE_MYSQLDUMP), ('sudo', FAKE_SUDO), ('valet', FAKE_VALET_PROXY),
                 ('php', FAKE_LOGGER), ('gh', FAKE_GH), ('fnm', FAKE_LOGGER))
        for name, body in stubs:
            stub = bin_dir / name
            stub.write_text(body)
            stub.chmod(stub.stat().st_mode | stat.S_IXUSR)
        # The valet the sudoers rule covers (Homebrew's link), off PATH.
        self.trusted_valet = self.tmp / 'brew' / 'bin' / 'valet'
        self.trusted_valet.parent.mkdir(parents=True)
        self.trusted_valet.write_text(FAKE_LOGGER)
        self.trusted_valet.chmod(self.trusted_valet.stat().st_mode | stat.S_IXUSR)
        self.valet_home = self.tmp / 'valet'
        os.environ.update({
            'FAKE_DBS': '', 'FAKE_SUDO_OK': '1', 'WT_VALET_HOME': str(self.valet_home),
            'WT_FNM': str(bin_dir / 'fnm'),
            'FAKE_SUDO_LIST': ('User invented may run the following commands on host:\n    (ALL) ALL\n'
                               f'    (root) SETENV: NOPASSWD: {self.tmp}/brew/bin/brew *\n'
                               f'    (root) SETENV: NOPASSWD: {self.trusted_valet} *'),
        })
        self.origin = self.tmp / 'origin.git'
        git(self.tmp, 'init', '-q', '--bare', '-b', 'main', str(self.origin))
        self.repo = self.make_repo()
        for unit, manifest in (('api.boanerges', 'composer.json'), ('app.boanerges', 'package.json')):
            (self.repo / unit).mkdir()
            (self.repo / unit / manifest).write_text('{}\n')
        git(self.repo, 'add', '.')
        git(self.repo, 'commit', '-q', '-m', 'units')
        git(self.repo, 'remote', 'add', 'origin', str(self.origin))
        git(self.repo, 'push', '-q', 'origin', 'main')
        # The main checkout's installed deps and its local .env (all ignored, never versioned).
        (self.repo / '.gitignore').write_text('vendor/\nnode_modules/\n.env\n.worktree.json\n')
        git(self.repo, 'add', '.gitignore')
        git(self.repo, 'commit', '-q', '-m', 'ignore')
        git(self.repo, 'push', '-q', 'origin', 'main')
        vendor = self.repo / 'api.boanerges' / 'vendor' / 'invented'
        vendor.mkdir(parents=True)
        (vendor / 'lib.php').write_text('<?php // invented\n')
        modules = self.repo / 'app.boanerges' / 'node_modules'
        (modules / 'invented').mkdir(parents=True)
        (modules / 'invented' / 'index.js').write_text('// invented\n')
        (modules / '.bin').mkdir()
        (modules / '.bin' / 'invented').symlink_to('../invented/index.js')
        (self.repo / 'api.boanerges' / '.env').write_text(API_ENV)
        (self.repo / 'app.boanerges' / '.env').write_text(APP_ENV)

    def calls(self) -> str:
        return self.log.read_text() if self.log.exists() else ''


class NewTest(RepoSandbox):
    """`worktree.py new` (make wt-new): a worktree from origin/main with its deps and record."""

    def advance_origin(self) -> str:
        """A commit on origin/main the main checkout has not fetched yet; returns its sha."""
        other = self.tmp / 'other'
        git(self.tmp, 'clone', '-q', str(self.origin), str(other))
        (other / 'NEWS.md').write_text('invented\n')
        git(other, 'add', '.')
        git(other, 'commit', '-q', '-m', 'ahead')
        git(other, 'push', '-q', 'origin', 'main')
        return git(other, 'rev-parse', 'HEAD')

    def run_new(self, *args: str, cwd: Path | None = None) -> subprocess.CompletedProcess:
        return subprocess.run(
            [sys.executable, str(HERE / 'worktree.py'), 'new', *args],
            cwd=cwd or self.repo, capture_output=True, text=True,
        )

    def wt_path(self, wt_id: str) -> Path:
        return self.repo / '.claude' / 'worktrees' / wt_id

    def assert_nothing_created(self, wt_id: str, branch: str) -> None:
        self.assertFalse(self.wt_path(wt_id).exists(), 'the worktree folder was created')
        self.assertEqual(git(self.repo, 'branch', '--list', branch), '', 'the branch was created')
        self.assertNotIn('composer', self.calls())

    def test_a3_feature_worktree_from_origin_main_with_real_deps_and_record(self) -> None:
        ahead = self.advance_origin()
        status_before = git(self.repo, 'status', '--porcelain')
        out = self.run_new('boanerges-99', '--slug', 'invented-thing')
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        wt = self.wt_path('boanerges-99')
        self.assertTrue(wt.is_dir())
        self.assertEqual(git(wt, 'branch', '--show-current'), 'feature/boanerges-99-invented-thing')
        self.assertEqual(git(wt, 'rev-parse', 'HEAD'), ahead, 'not made from the fetched origin/main')
        for dep in (wt / 'api.boanerges' / 'vendor', wt / 'app.boanerges' / 'node_modules'):
            self.assertTrue(dep.is_dir() and not dep.is_symlink(), f'{dep} is not a real directory')
        self.assertEqual((wt / 'api.boanerges' / 'vendor' / 'invented' / 'lib.php').read_text(), '<?php // invented\n')
        self.assertTrue((wt / 'app.boanerges' / 'node_modules' / '.bin' / 'invented').is_symlink())
        record = json.loads((wt / '.worktree.json').read_text())
        self.assertEqual(
            (record['id'], record['label'], record['branch']),
            ('boanerges-99', 'b99', 'feature/boanerges-99-invented-thing'),
        )
        self.assertEqual(record['database'], 'boanerges_b99')
        self.assertIn('created_at', record)
        self.assertEqual(git(self.repo, 'branch', '--show-current'), 'main')
        self.assertEqual(git(self.repo, 'status', '--porcelain'), status_before)
        self.assertIn(f'composer {wt / "api.boanerges"} install', self.calls())
        self.assertIn(f'npm {wt / "app.boanerges"} install', self.calls())

    def test_a_new_branch_does_not_track_origin_main(self) -> None:
        self.assertEqual(self.run_new('boanerges-99').returncode, 0)
        wt = self.wt_path('boanerges-99')
        self.assertEqual(git(wt, 'branch', '--show-current'), 'feature/boanerges-99')
        tracking = subprocess.run(
            ['git', 'config', '--get', 'branch.feature/boanerges-99.merge'], cwd=wt, capture_output=True, text=True,
        )
        self.assertEqual(tracking.stdout.strip(), '', 'a push from the new branch could reach main')

    def test_branch_given_new_name_is_made_from_origin_main(self) -> None:
        ahead = self.advance_origin()
        out = self.run_new('fix-qa', '--branch', 'fix/boanerges-98-invented')
        self.assertEqual(out.returncode, 0, out.stderr)
        wt = self.wt_path('fix-qa')
        self.assertEqual(git(wt, 'branch', '--show-current'), 'fix/boanerges-98-invented')
        self.assertEqual(git(wt, 'rev-parse', 'HEAD'), ahead)
        self.assertEqual(json.loads((wt / '.worktree.json').read_text())['label'], 'fixqa')

    def test_branch_given_existing_local_branch_is_checked_out_as_is(self) -> None:
        git(self.repo, 'branch', 'fix/old-work', 'main~1')
        old = git(self.repo, 'rev-parse', 'fix/old-work')
        out = self.run_new('pr50', '--branch', 'fix/old-work')
        self.assertEqual(out.returncode, 0, out.stderr)
        wt = self.wt_path('pr50')
        self.assertEqual(git(wt, 'branch', '--show-current'), 'fix/old-work')
        self.assertEqual(git(wt, 'rev-parse', 'HEAD'), old)

    def test_branch_given_only_on_origin_gets_a_local_branch_at_it(self) -> None:
        git(self.repo, 'push', '-q', 'origin', 'main~1:refs/heads/feature/remote-only')
        remote = git(self.repo, 'rev-parse', 'main~1')
        out = self.run_new('remote-only', '--branch', 'feature/remote-only')
        self.assertEqual(out.returncode, 0, out.stderr)
        wt = self.wt_path('remote-only')
        self.assertEqual(git(wt, 'branch', '--show-current'), 'feature/remote-only')
        self.assertEqual(git(wt, 'rev-parse', 'HEAD'), remote)

    def test_runs_the_same_from_inside_another_worktree(self) -> None:
        first = self.run_new('boanerges-98')
        self.assertEqual(first.returncode, 0, first.stderr)
        out = self.run_new('boanerges-99', cwd=self.wt_path('boanerges-98') / 'api.boanerges')
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertTrue(self.wt_path('boanerges-99').is_dir())

    def test_label_held_by_another_worktrees_record_is_refused(self) -> None:
        self.add_worktree(self.repo, 'by-hand', {'id': 'by-hand', 'label': 'b99'})
        out = self.run_new('boanerges-99')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('by-hand', out.stderr)
        self.assert_nothing_created('boanerges-99', 'feature/boanerges-99')

    def test_label_of_a_worktree_without_record_is_refused(self) -> None:
        self.add_worktree(self.repo, 'B99')
        out = self.run_new('boanerges-99')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('B99', out.stderr)
        self.assert_nothing_created('boanerges-99', 'feature/boanerges-99')

    def test_database_of_the_label_existing_without_a_record_is_refused(self) -> None:
        os.environ['FAKE_DBS'] = 'boanerges_b99'
        out = self.run_new('boanerges-99')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('boanerges_b99', out.stderr)
        self.assert_nothing_created('boanerges-99', 'feature/boanerges-99')

    def test_the_database_password_goes_through_the_environment_never_argv(self) -> None:
        self.assertEqual(self.run_new('boanerges-99').returncode, 0)
        mysql = [line for line in self.calls().splitlines() if line.startswith('mysql ')]
        self.assertTrue(mysql, 'the database was never checked')
        self.assertTrue(all(line.endswith('pwd=env') for line in mysql))
        self.assertNotIn('invented-db-secret', self.calls())

    def test_a_main_env_not_local_is_refused_before_any_mysql_call(self) -> None:
        env = self.repo / 'api.boanerges' / '.env'
        env.write_text(API_ENV.replace('APP_ENV=local', 'APP_ENV=production'))
        out = self.run_new('boanerges-99')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('APP_ENV', out.stderr)
        self.assertNotIn('mysql', self.calls())
        self.assert_nothing_created('boanerges-99', 'feature/boanerges-99')

    def test_an_existing_folder_is_refused(self) -> None:
        self.wt_path('boanerges-99').mkdir(parents=True)
        out = self.run_new('boanerges-99')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('exists', out.stderr)

    def test_an_id_that_is_not_a_plain_name_is_refused(self) -> None:
        for bad in ('../escape', 'a/b', '.hidden'):
            out = self.run_new(bad)
            self.assertNotEqual(out.returncode, 0, bad)
        self.assertFalse((self.repo / '.claude' / 'worktrees').exists())

    def test_cp_without_clone_flag_falls_back_to_a_plain_copy(self) -> None:
        cp = self.tmp / 'bin' / 'cp'
        cp.write_text(FAKE_CP_NO_CLONE)
        cp.chmod(cp.stat().st_mode | stat.S_IXUSR)
        out = self.run_new('boanerges-99')
        self.assertEqual(out.returncode, 0, out.stderr)
        lib = self.wt_path('boanerges-99') / 'api.boanerges' / 'vendor' / 'invented' / 'lib.php'
        self.assertEqual(lib.read_text(), '<?php // invented\n')


class UpTest(RepoSandbox):
    """`worktree.py up` (make wt-up): guards, the Valet trust probe, the shared session, Valet, DB."""

    def setUp(self) -> None:
        super().setUp()
        self.wt = self.repo / '.claude' / 'worktrees' / 'boanerges-7'
        git(self.repo, 'worktree', 'add', '-q', '-b', 'feature/boanerges-7-invented', str(self.wt), 'main')
        self.main_env = self.repo / 'api.boanerges' / '.env'

    def run_cmd(self, *args: str, cwd: Path | None = None) -> subprocess.CompletedProcess:
        return subprocess.run(
            [sys.executable, str(HERE / 'worktree.py'), *args],
            cwd=cwd or self.wt, capture_output=True, text=True,
        )

    def run_up(self, cwd: Path | None = None) -> subprocess.CompletedProcess:
        return self.run_cmd('up', cwd=cwd)

    def lines(self, tool: str) -> list[str]:
        return [line for line in self.calls().splitlines() if line.startswith(f'{tool} ')]

    def assert_untouched(self) -> None:
        for tool in ('mysql', 'mysqldump', 'valet', 'php'):
            self.assertEqual(self.lines(tool), [], f'{tool} ran before the checks passed')
        self.assertFalse((self.wt / '.worktree.json').exists(), 'the record was written')
        self.assertFalse((self.wt / 'api.boanerges' / '.env').exists(), 'the env file was written')

    def test_a7_main_env_not_local_is_refused_before_any_call(self) -> None:
        self.main_env.write_text(API_ENV.replace('APP_ENV=local', 'APP_ENV=production'))
        out = self.run_up()
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('APP_ENV', out.stderr)
        self.assert_untouched()

    def test_a7_db_host_not_local_is_refused_before_any_call(self) -> None:
        self.main_env.write_text(API_ENV.replace('DB_HOST=127.0.0.1', 'DB_HOST=db.example.com'))
        out = self.run_up()
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('DB_HOST', out.stderr)
        self.assert_untouched()

    def test_a7_target_that_is_not_a_worktree_database_is_refused(self) -> None:
        (self.wt / '.worktree.json').write_text(json.dumps({'id': 'boanerges-7', 'label': 'b7', 'database': 'boanerges;drop'}))
        out = self.run_up()
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('boanerges;drop', out.stderr)
        for tool in ('mysql', 'mysqldump', 'valet', 'php'):
            self.assertEqual(self.lines(tool), [], tool)

    def test_a7_source_database_not_the_projects_is_refused(self) -> None:
        self.main_env.write_text(API_ENV.replace('DB_DATABASE=boanerges', 'DB_DATABASE=boanerges_old'))
        out = self.run_up()
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('DB_DATABASE', out.stderr)
        self.assert_untouched()

    def test_a8_untrusted_valet_stops_with_the_line_to_type(self) -> None:
        os.environ['FAKE_SUDO_OK'] = '0'
        out = self.run_up()
        self.assertNotEqual(out.returncode, 0)
        self.assert_trust_hint(out.stderr)
        self.assertEqual(self.lines('sudo'), ['sudo -n -l'])
        self.assert_untouched()
        self.assertEqual(self.main_env.read_text(), API_ENV)

    def test_a8_sudo_allowing_valet_only_with_a_password_is_not_trust(self) -> None:
        # `sudo -n -l <valet>` passes here (cached timestamp, or `(ALL) ALL` under listpw=any).
        os.environ['FAKE_SUDO_LIST'] = 'User invented may run the following commands on host:\n    (ALL) ALL'
        out = self.run_up()
        self.assertNotEqual(out.returncode, 0)
        self.assert_trust_hint(out.stderr)
        self.assert_untouched()
        self.assertEqual(self.lines('proxy-valet'), [])

    def test_a8_valet_runs_by_the_path_its_sudoers_rule_covers_not_the_first_on_path(self) -> None:
        out = self.run_up()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertEqual(self.lines('proxy-valet'), [])
        self.assertTrue(self.lines('valet'))

    def test_the_api_site_gets_the_main_api_sites_php_isolation(self) -> None:
        nginx = self.valet_home / 'Nginx'
        nginx.mkdir(parents=True)
        (nginx / 'api.boanerges.test').write_text('# ISOLATED_PHP_VERSION=84\nserver {}\n')
        out = self.run_up()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        api = self.wt / 'api.boanerges'
        self.assertIn(f'valet {api} isolate php@8.4 --site=api.b7.boanerges E2E_PORT=', self.lines('valet'))
        link = self.lines('valet').index(f'valet {api} link api.b7.boanerges E2E_PORT=')
        isolate = self.lines('valet').index(f'valet {api} isolate php@8.4 --site=api.b7.boanerges E2E_PORT=')
        self.assertLess(link, isolate, 'the site is linked before it is isolated')

    def test_an_api_site_already_isolated_alike_is_not_isolated_again(self) -> None:
        nginx = self.valet_home / 'Nginx'
        nginx.mkdir(parents=True)
        (nginx / 'api.boanerges.test').write_text('# ISOLATED_PHP_VERSION=84\nserver {}\n')
        (nginx / 'api.b7.boanerges.test').write_text('# ISOLATED_PHP_VERSION=php@8.4\nserver {}\n')
        out = self.run_up()
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertFalse(any(' isolate ' in line for line in self.lines('valet')), self.lines('valet'))

    def test_a_main_api_site_on_the_default_php_isolates_nothing(self) -> None:
        out = self.run_up()
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertFalse(any(' isolate ' in line for line in self.lines('valet')), self.lines('valet'))

    def test_r9_session_domain_not_shared_stops_before_anything(self) -> None:
        self.main_env.write_text(API_ENV.replace('SESSION_DOMAIN=.boanerges.test', 'SESSION_DOMAIN=null'))
        out = self.run_up()
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('SESSION_DOMAIN=.boanerges.test', out.stderr)
        self.assert_untouched()

    def test_c6_main_on_database_is_left_byte_identical(self) -> None:
        before = self.main_env.read_bytes()
        out = self.run_up()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertEqual(self.main_env.read_bytes(), before)
        self.assertNotIn('log in once more', out.stdout)
        wt_env = (self.wt / 'api.boanerges' / '.env').read_text()
        self.assertIn('SESSION_DRIVER=database\n', wt_env)
        self.assertIn('SESSION_TABLE=boanerges.sessions\n', wt_env)

    def test_c6_cookie_left_by_an_old_wt_up_goes_back_to_database_once(self) -> None:
        self.main_env.write_text(API_ENV.replace('SESSION_DRIVER=database', 'SESSION_DRIVER=cookie'))
        out = self.run_up()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertEqual(changed_keys(API_ENV, self.main_env.read_text()), set())
        self.assertIn('log in once more', out.stdout)
        before = self.main_env.read_bytes()
        again = self.run_up()
        self.assertEqual(again.returncode, 0, again.stderr)
        self.assertEqual(self.main_env.read_bytes(), before)
        self.assertNotIn('log in once more', again.stdout)

    def test_c6_another_main_session_driver_stops_before_anything(self) -> None:
        self.main_env.write_text(API_ENV.replace('SESSION_DRIVER=database', 'SESSION_DRIVER=file'))
        out = self.run_up()
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('SESSION_DRIVER=database', out.stderr)
        self.assert_untouched()

    def test_r4_links_secures_copies_writes_env_and_migrates(self) -> None:
        out = self.run_up()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        api, app_dist = self.wt / 'api.boanerges', self.wt / 'app.boanerges' / 'dist'
        valet = self.lines('valet')
        self.assertIn(f'valet {api} link api.b7.boanerges E2E_PORT=', valet)
        self.assertIn(f'valet {app_dist} link app.b7.boanerges E2E_PORT=', valet)
        self.assertTrue(any(' secure api.b7.boanerges ' in line for line in valet), valet)
        self.assertTrue(any(' secure app.b7.boanerges ' in line for line in valet), valet)
        mysql = self.lines('mysql')
        create = [line for line in mysql if 'CREATE DATABASE' in line]
        self.assertEqual(len(create), 1, mysql)
        self.assertIn('`boanerges_b7`', create[0])
        self.assertIn('utf8mb4_unicode_ci', create[0])
        dump = self.lines('mysqldump')
        self.assertEqual(len(dump), 1)
        self.assertTrue(dump[0].split(' pwd=')[0].endswith(' boanerges'), dump)
        self.assertIn('import: -- invented dump', self.calls())
        self.assertTrue(any(line.split(' pwd=')[0].endswith(' boanerges_b7') for line in mysql), mysql)
        self.assertTrue(all(line.endswith('pwd=env') for line in mysql + dump))
        self.assertNotIn('invented-db-secret', self.calls() + out.stdout + out.stderr)
        self.assertIn(f'php {api} artisan migrate --force', self.calls())
        wt_api = (api / '.env').read_text()
        self.assertEqual(value_of(wt_api, 'DB_DATABASE'), 'boanerges_b7')
        self.assertEqual(value_of((self.wt / 'app.boanerges' / '.env').read_text(), 'VITE_APP_URL'),
                         'https://app.b7.boanerges.test:6007')
        record = json.loads((self.wt / '.worktree.json').read_text())
        self.assertEqual((record['id'], record['label'], record['branch'], record['database']),
                         ('boanerges-7', 'b7', 'feature/boanerges-7-invented', 'boanerges_b7'))
        self.assertEqual((record['ports']['vite'], record['ports']['e2e']), (6007, 6107))
        self.assertIn('https://app.b7.boanerges.test:6007', out.stdout)
        order = [line.split()[0] for line in self.calls().splitlines() if line.split()[0] in ('sudo', 'valet', 'mysql')]
        self.assertEqual(order[0], 'sudo', 'Valet trust is probed before Valet or MySQL')

    def test_rerun_keeps_the_existing_database(self) -> None:
        os.environ['FAKE_DBS'] = 'boanerges_b7'
        (self.wt / '.worktree.json').write_text(json.dumps({'id': 'boanerges-7', 'label': 'b7', 'database': 'boanerges_b7'}))
        out = self.run_up()
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertEqual([line for line in self.lines('mysql') if 'CREATE' in line or 'DROP' in line], [])
        self.assertEqual(self.lines('mysqldump'), [])
        self.assertIn('kept', out.stdout)
        self.assertIn('artisan migrate --force', self.calls())

    def test_a_site_already_linked_and_secured_is_left_as_it_is(self) -> None:
        sites, certs = self.valet_home / 'Sites', self.valet_home / 'Certificates'
        sites.mkdir(parents=True)
        certs.mkdir(parents=True)
        (sites / 'api.b7.boanerges').symlink_to(self.wt / 'api.boanerges')
        (self.wt / 'app.boanerges' / 'dist').mkdir()
        (sites / 'app.b7.boanerges').symlink_to(self.wt / 'app.boanerges' / 'dist')
        for host in ('api.b7.boanerges.test', 'app.b7.boanerges.test'):
            (certs / f'{host}.crt').write_text('invented\n')
        out = self.run_up()
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertEqual(self.lines('valet'), [])

    def test_a_failed_copy_drops_the_database_it_just_made(self) -> None:
        os.environ['FAKE_DUMP_FAIL'] = '1'
        out = self.run_up()
        self.assertNotEqual(out.returncode, 0)
        self.assertTrue(any('DROP DATABASE IF EXISTS `boanerges_b7`' in line for line in self.lines('mysql')))
        self.assertNotIn('artisan migrate', self.calls())

    def test_run_from_the_main_checkout_is_refused(self) -> None:
        out = self.run_up(cwd=self.repo)
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('inside a worktree', out.stderr)
        self.assertEqual(self.calls(), '')

    def test_a_label_held_by_another_worktrees_record_is_refused(self) -> None:
        self.add_worktree(self.repo, 'by-hand', {'id': 'by-hand', 'label': 'b7'})
        out = self.run_up()
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('by-hand', out.stderr)
        self.assert_untouched()

    # --- app ---

    def test_r7_app_runs_vite_with_the_e2e_port_exported(self) -> None:
        self.assertEqual(self.run_up().returncode, 0)
        out = self.run_cmd('app')
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertIn(f'npm {self.wt / "app.boanerges"} run dev E2E_PORT=6107', self.calls())
        self.assertNotIn('Vite servers', out.stdout + out.stderr)

    def test_r7_app_warns_above_four_vite_servers_in_the_blocks(self) -> None:
        self.assertEqual(self.run_up().returncode, 0)
        worktree_blocks = json.loads(self.port_file.read_text())
        worktree_blocks['blocks']['chapaja'] = 6200
        self.port_file.write_text(json.dumps(worktree_blocks))
        self.listen(6001, 6002, 6003, 6204, 6107)
        quiet = self.run_cmd('app')
        self.assertNotIn('Vite servers', quiet.stdout + quiet.stderr, 'an e2e port is not a Vite server')
        self.listen(6001, 6002, 6003, 6204, 6299)
        loud = self.run_cmd('app')
        self.assertEqual(loud.returncode, 0, loud.stderr)
        self.assertIn('5 Vite servers', loud.stdout + loud.stderr)
        self.assertIn('run dev', self.lines('npm')[-1])

    def test_app_without_an_environment_is_refused(self) -> None:
        out = self.run_cmd('app')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('make wt-up', out.stderr)
        self.assertEqual(self.lines('npm'), [])

    def test_app_whose_port_already_listens_is_refused(self) -> None:
        self.assertEqual(self.run_up().returncode, 0)
        self.listen(6007)
        out = self.run_cmd('app')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('6007', out.stderr)
        self.assertEqual(self.lines('npm'), [])


class NodeVersionTest(RepoSandbox):
    """npm runs under the Node a unit's nearest `.nvmrc` names (fnm), never the shell's, so no lockfile is rewritten."""

    def setUp(self) -> None:
        super().setUp()
        self.bin_dir = self.tmp / 'bin'

    def commit_files(self, files: dict[str, str]) -> None:
        for rel, text in files.items():
            (self.repo / rel).write_text(text)
        git(self.repo, 'add', '.')
        git(self.repo, 'commit', '-q', '-m', 'node versions')
        git(self.repo, 'push', '-q', 'origin', 'main')

    def run_new(self, wt_id: str = 'boanerges-98') -> subprocess.CompletedProcess:
        return subprocess.run([sys.executable, str(HERE / 'worktree.py'), 'new', wt_id],
                              cwd=self.repo, capture_output=True, text=True)

    def app(self, wt_id: str = 'boanerges-98') -> Path:
        return self.repo / '.claude' / 'worktrees' / wt_id / 'app.boanerges'

    def tool_lines(self, tool: str) -> list[str]:
        return [line for line in self.calls().splitlines() if line.startswith(f'{tool} ')]

    def fake_node(self, version: str) -> None:
        node = self.bin_dir / 'node'
        node.write_text(f'#!/bin/sh\necho "node $PWD $*" >> "$FAKE_LOG"\necho {version}\n')
        node.chmod(node.stat().st_mode | stat.S_IXUSR)

    def test_new_runs_npm_under_the_units_nvmrc_node(self) -> None:
        self.commit_files({'app.boanerges/.nvmrc': '24\n'})
        out = self.run_new()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertIn(f'fnm {self.app()} exec --using=24 -- npm install --no-audit --no-fund', self.calls())
        self.assertEqual(self.tool_lines('npm'), [], "npm ran under the shell's Node")
        self.assertNotEqual(self.tool_lines('composer'), [], 'composer did not run')

    def test_the_units_own_nvmrc_wins_over_the_roots(self) -> None:
        self.commit_files({'.nvmrc': 'v22\n', 'app.boanerges/.nvmrc': '24\n'})
        self.assertEqual(self.run_new().returncode, 0)
        self.assertIn('exec --using=24 -- npm install', self.calls())
        self.assertNotIn('--using=v22', self.calls())

    def test_a_unit_without_its_own_nvmrc_takes_the_roots(self) -> None:
        self.commit_files({'.nvmrc': 'v22\n'})
        self.assertEqual(self.run_new().returncode, 0)
        self.assertIn(f'fnm {self.app()} exec --using=v22 -- npm install', self.calls())

    def test_without_fnm_a_node_that_does_not_match_is_refused(self) -> None:
        self.commit_files({'app.boanerges/.nvmrc': '24\n'})
        os.environ['WT_FNM'] = ''
        self.fake_node('v22.23.4')
        out = self.run_new()
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('Node 24', out.stderr)
        self.assertIn('v22.23.4', out.stderr)
        self.assertIn('fnm', out.stderr)
        self.assertEqual(self.tool_lines('npm'), [], 'npm ran under the wrong Node')

    def test_without_fnm_a_matching_node_runs_npm_directly(self) -> None:
        self.commit_files({'app.boanerges/.nvmrc': 'v24\n'})
        os.environ['WT_FNM'] = ''
        self.fake_node('v24.19.0')
        out = self.run_new()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertIn(f'npm {self.app()} install --no-audit --no-fund', self.calls())

    def test_new_warns_when_an_install_leaves_the_worktree_dirty(self) -> None:
        npm = self.bin_dir / 'npm'
        npm.write_text('#!/bin/sh\necho "npm $PWD $*" >> "$FAKE_LOG"\necho "{ }" > package.json\n')
        out = self.run_new()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertIn('warning:', out.stdout)
        self.assertIn('app.boanerges/package.json', out.stdout)
        self.assertIn('wt-discard', out.stdout)

    def test_a_clean_install_prints_no_dirty_warning(self) -> None:
        out = self.run_new()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertNotIn('warning:', out.stdout)

    def test_app_runs_vite_under_the_nvmrc_node(self) -> None:
        wt = self.repo / '.claude' / 'worktrees' / 'boanerges-7'
        git(self.repo, 'worktree', 'add', '-q', '-b', 'feature/boanerges-7-invented', str(wt), 'main')
        (wt / 'app.boanerges' / '.nvmrc').write_text('24\n')

        def run(*args: str) -> subprocess.CompletedProcess:
            return subprocess.run([sys.executable, str(HERE / 'worktree.py'), *args],
                                  cwd=wt, capture_output=True, text=True)

        self.assertEqual(run('up').returncode, 0)
        out = run('app')
        self.assertEqual(out.returncode, 0, out.stderr)
        self.assertIn(f'fnm {wt / "app.boanerges"} exec --using=24 -- npm run dev E2E_PORT=6107', self.calls())
        self.assertFalse(any('run dev' in line for line in self.tool_lines('npm')))


DAY = 24 * 3600


class TeardownSandbox(RepoSandbox):
    """Worktrees made from origin/main, merged "on GitHub" (another clone), Valet state and transcripts."""

    def setUp(self) -> None:
        super().setUp()
        self.projects = self.tmp / 'projects'
        os.environ['WT_PROJECTS_DIR'] = str(self.projects)
        (self.repo / '.gitignore').write_text('vendor/\nnode_modules/\n.env\n.worktree.json\ndist/\ndata/\n**/storage/framework/\n')
        git(self.repo, 'add', '.gitignore')
        git(self.repo, 'commit', '-q', '-m', 'ignore more')
        git(self.repo, 'push', '-q', 'origin', 'main')
        self.github = self.tmp / 'github'
        git(self.tmp, 'clone', '-q', str(self.origin), str(self.github))
        self.prs: dict[str, list] = {}

    def run_cmd(self, *args: str, cwd: Path | None = None) -> subprocess.CompletedProcess:
        return subprocess.run(
            [sys.executable, str(HERE / 'worktree.py'), *args], cwd=cwd or self.repo, capture_output=True, text=True,
        )

    def lines(self, tool: str) -> list[str]:
        return [line for line in self.calls().splitlines() if line.startswith(f'{tool} ')]

    def worktree(self, wt_id: str, record: bool = True, days_old: float = 0, commit: bool = True) -> Path:
        path = self.repo / '.claude' / 'worktrees' / wt_id
        git(self.repo, 'worktree', 'add', '-q', '--no-track', '-b', f'feature/{wt_id}', str(path), 'origin/main')
        if commit:
            (path / f'{wt_id}.md').write_text('invented\n')
            git(path, 'add', '.')
            when = f'@{int(time.time() - days_old * DAY)} +0000'
            subprocess.run(['git', 'commit', '-q', '-m', f'work on {wt_id}'], cwd=path, check=True,
                           env={**os.environ, 'GIT_AUTHOR_DATE': when, 'GIT_COMMITTER_DATE': when})
        if record:
            n = int(wt_id.rsplit('-', 1)[1])
            (path / '.worktree.json').write_text(json.dumps({
                'id': wt_id, 'label': f'b{n}', 'branch': f'feature/{wt_id}', 'database': f'boanerges_b{n}',
                'ports': {'base': 6000, 'slot': n, 'vite': 6000 + n, 'e2e': 6100 + n},
            }))
        return path

    def pr(self, wt_id: str, number: int, state: str) -> None:
        merged = '2026-11-11T09:40:00Z' if state == 'MERGED' else None
        self.prs.setdefault(f'feature/{wt_id}', []).append({'number': number, 'state': state, 'mergedAt': merged})
        os.environ['FAKE_GH_PRS'] = json.dumps(self.prs)

    def merge(self, wt_id: str, number: int) -> None:
        """The branch pushed and merged with a merge commit on origin, as GitHub does; local main stays behind."""
        branch = f'feature/{wt_id}'
        git(self.repo, 'push', '-q', 'origin', branch)
        git(self.github, 'fetch', '-q', 'origin')
        git(self.github, 'merge', '-q', '--no-ff', '-m', f'Merge pull request #{number}', f'origin/{branch}')
        git(self.github, 'push', '-q', 'origin', 'main')
        self.pr(wt_id, number, 'MERGED')

    def served(self, wt_id: str, lbl: str) -> None:
        """Valet state as `wt-up` leaves it: both sites linked and secured, the api one isolated."""
        path = self.repo / '.claude' / 'worktrees' / wt_id
        sites, certs, nginx = (self.valet_home / d for d in ('Sites', 'Certificates', 'Nginx'))
        for folder in (sites, certs, nginx):
            folder.mkdir(parents=True, exist_ok=True)
        (path / 'app.boanerges' / 'dist').mkdir(exist_ok=True)
        for site, target in ((f'api.{lbl}.boanerges', path / 'api.boanerges'),
                             (f'app.{lbl}.boanerges', path / 'app.boanerges' / 'dist')):
            (sites / site).symlink_to(target)
            (certs / f'{site}.test.crt').write_text('invented\n')
            (nginx / f'{site}.test').write_text('server {}\n')
        (nginx / f'api.{lbl}.boanerges.test').write_text('# ISOLATED_PHP_VERSION=php@8.4\nserver {}\n')

    def session(self, cwd: Path, minutes_ago: float, rel: str = '-invented-slug/0001.jsonl') -> Path:
        path = self.projects / rel
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps({'type': 'summary'}) + '\n' + json.dumps({'type': 'user', 'cwd': str(cwd)}) + '\n')
        when = time.time() - minutes_ago * 60
        os.utime(path, (when, when))
        return path

    def branch_exists(self, branch: str) -> bool:
        return subprocess.run(['git', 'show-ref', '--verify', '--quiet', f'refs/heads/{branch}'], cwd=self.repo).returncode == 0

    def drops(self) -> list[str]:
        return [line for line in self.lines('mysql') if 'DROP DATABASE' in line]


class DownTest(TeardownSandbox):
    """`worktree.py down <id>` (make wt-down): every check first, then Valet, the DB copy, the worktree, the branch."""

    def setUp(self) -> None:
        super().setUp()
        self.wt = self.worktree('boanerges-9')
        self.merge('boanerges-9', 51)
        self.served('boanerges-9', 'b9')

    def run_down(self, wt_id: str = 'boanerges-9') -> subprocess.CompletedProcess:
        return self.run_cmd('down', wt_id)

    def assert_untouched(self, out: subprocess.CompletedProcess, check: str) -> None:
        self.assertNotEqual(out.returncode, 0)
        self.assertIn(check, out.stderr)
        self.assertIn('boanerges-9', out.stderr)
        self.assertTrue(self.wt.is_dir(), 'the worktree folder was removed')
        self.assertTrue(self.branch_exists('feature/boanerges-9'), 'the branch was deleted')
        self.assertEqual(self.lines('valet'), [], 'Valet was touched')
        self.assertEqual(self.drops(), [], 'the database was dropped')
        self.assertTrue((self.valet_home / 'Sites' / 'api.b9.boanerges').is_symlink())

    def test_a10_merged_clean_quiet_worktree_is_torn_down(self) -> None:
        for rel in ('api.boanerges/vendor/invented/lib.php', 'api.boanerges/.env', 'app.boanerges/.env',
                    'api.boanerges/storage/framework/views/invented.php', 'app.boanerges/dist/index.html'):
            (self.wt / rel).parent.mkdir(parents=True, exist_ok=True)
            (self.wt / rel).write_text('invented\n')
        self.session(self.repo, 5)                       # the main checkout's session: not this worktree's
        self.session(self.wt, 120, '-other/0002.jsonl')  # this worktree's, two hours ago
        out = self.run_down()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        valet = [line.split(' ', 2)[2].rsplit(' E2E_PORT=', 1)[0] for line in self.lines('valet')]
        self.assertEqual(valet, [
            'unisolate --site=api.b9.boanerges', 'unsecure api.b9.boanerges', 'unlink api.b9.boanerges',
            'unsecure app.b9.boanerges', 'unlink app.b9.boanerges',
        ])
        self.assertEqual(self.lines('proxy-valet'), [], 'Valet runs by the path its sudoers rule covers')
        self.assertEqual(len(self.drops()), 1)
        self.assertIn('DROP DATABASE IF EXISTS `boanerges_b9`', self.drops()[0])
        self.assertTrue(self.drops()[0].endswith('pwd=env'))
        self.assertNotIn('invented-db-secret', self.calls() + out.stdout + out.stderr)
        self.assertFalse(self.wt.exists(), 'the worktree folder is still there')
        self.assertNotIn(str(self.wt), git(self.repo, 'worktree', 'list'))
        self.assertFalse(self.branch_exists('feature/boanerges-9'), 'the local branch is still there')
        order = [line.split()[0] for line in self.calls().splitlines() if line.split()[0] in ('gh', 'valet', 'mysql')]
        self.assertEqual(order[0], 'gh', 'the PR is checked before Valet or MySQL')

    def test_a9_a_commit_not_in_origin_main_is_refused(self) -> None:
        (self.wt / 'later.md').write_text('invented\n')
        git(self.wt, 'add', '.')
        git(self.wt, 'commit', '-q', '-m', 'after the merge')
        self.assert_untouched(self.run_down(), 'origin/main')

    def test_a9_an_untracked_file_is_refused(self) -> None:
        (self.wt / 'notes.txt').write_text('invented\n')
        self.assert_untouched(self.run_down(), 'git status')

    def test_a9_a_session_in_the_worktree_5_minutes_ago_is_refused(self) -> None:
        # A subagent file, under a folder slug that is not the worktree's: the check reads `cwd`.
        self.session(self.wt / 'api.boanerges', 5, '-unrelated-slug/0003/subagents/agent-1.jsonl')
        self.assert_untouched(self.run_down(), 'session')

    def test_collision_a_label_another_worktree_holds_is_refused(self) -> None:
        """A hand-made `b9` labels to boanerges-9's `b9`: its teardown would drop the other's database."""
        other = self.worktree('b9', record=False)
        self.merge('b9', 52)
        out = self.run_down('b9')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('boanerges-9', out.stderr)
        self.assertEqual(self.drops(), [], "the other worktree's database was dropped")
        self.assertEqual(self.lines('valet'), [], 'Valet was touched')
        self.assertTrue(other.is_dir())

    def test_a_fresh_worktree_with_nothing_ahead_and_no_pr_is_refused(self) -> None:
        fresh = self.worktree('boanerges-10', commit=False)
        out = self.run_down('boanerges-10')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('merged', out.stderr)
        self.assertTrue(fresh.is_dir())
        self.assertTrue(self.branch_exists('feature/boanerges-10'))
        self.assertEqual(self.drops(), [])

    def test_a_pr_closed_unmerged_is_refused(self) -> None:
        self.prs = {}
        self.pr('boanerges-9', 51, 'CLOSED')
        self.assert_untouched(self.run_down(), 'merged')

    def test_an_ignored_file_outside_the_regenerable_set_is_refused(self) -> None:
        (self.wt / 'data').mkdir()
        (self.wt / 'data' / 'invented.csv').write_text('invented\n')
        out = self.run_down()
        self.assert_untouched(out, 'ignored')
        self.assertIn('data/', out.stderr)

    def test_a_listening_vite_port_is_refused(self) -> None:
        self.listen(6009)
        out = self.run_down()
        self.assert_untouched(out, '6009')

    def test_a_main_env_not_local_is_refused_before_any_call(self) -> None:
        (self.repo / 'api.boanerges' / '.env').write_text(API_ENV.replace('APP_ENV=local', 'APP_ENV=production'))
        out = self.run_down()
        self.assert_untouched(out, 'APP_ENV')
        self.assertEqual(self.lines('mysql'), [])

    def test_a_record_database_that_is_not_a_worktree_database_is_refused(self) -> None:
        record = json.loads((self.wt / '.worktree.json').read_text())
        (self.wt / '.worktree.json').write_text(json.dumps({**record, 'database': 'boanerges'}))
        self.assert_untouched(self.run_down(), 'boanerges_<label>')

    def test_untrusted_valet_is_refused_before_anything_is_undone(self) -> None:
        os.environ['FAKE_SUDO_OK'] = '0'
        out = self.run_down()
        self.assert_untouched(out, 'valet trust')
        self.assert_trust_hint(out.stderr)

    def test_gh_failing_is_refused(self) -> None:
        os.environ['FAKE_GH_FAIL'] = '1'
        self.assert_untouched(self.run_down(), 'gh')

    def test_a_worktree_never_served_drops_only_the_database(self) -> None:
        self.worktree('boanerges-11')
        self.merge('boanerges-11', 52)
        out = self.run_down('boanerges-11')
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertEqual(self.lines('valet'), [])
        self.assertEqual(self.lines('sudo'), [], 'no Valet work, so no trust probe')
        self.assertIn('`boanerges_b11`', self.drops()[0])
        self.assertFalse((self.repo / '.claude' / 'worktrees' / 'boanerges-11').exists())

    def test_a_worktree_without_record_follows_the_same_checks(self) -> None:
        bare = self.worktree('boanerges-12', record=False)
        (bare / 'notes.txt').write_text('invented\n')
        self.merge('boanerges-12', 53)
        refused = self.run_down('boanerges-12')
        self.assertNotEqual(refused.returncode, 0)
        self.assertIn('git status', refused.stderr)
        (bare / 'notes.txt').unlink()
        out = self.run_down('boanerges-12')
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertIn('`boanerges_b12`', self.drops()[0])
        self.assertFalse(bare.exists())

    def test_a_valet_site_linked_to_another_folder_is_left_alone(self) -> None:
        link = self.valet_home / 'Sites' / 'app.b9.boanerges'
        link.unlink()
        elsewhere = self.tmp / 'elsewhere'
        elsewhere.mkdir()
        link.symlink_to(elsewhere)
        out = self.run_down()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertFalse(any('app.b9.boanerges' in line for line in self.lines('valet')), self.lines('valet'))
        self.assertIn('app.b9.boanerges', out.stdout)

    def test_an_unknown_id_is_refused(self) -> None:
        out = self.run_down('boanerges-77')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('boanerges-77', out.stderr)
        self.assertEqual(self.calls(), '')

    def test_the_main_checkout_is_never_torn_down(self) -> None:
        out = self.run_down('boanerges')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('main checkout', out.stderr)
        self.assertTrue((self.repo / '.git').is_dir())
        self.assertEqual(self.drops(), [])


class GcTest(TeardownSandbox):
    """`worktree.py gc` (make wt-gc): down on every worktree that passes; left-behind ones only listed."""

    def run_gc(self) -> subprocess.CompletedProcess:
        return self.run_cmd('gc')

    def test_a11_only_the_merged_clean_one_is_torn_down(self) -> None:
        merged = self.worktree('boanerges-50')
        self.merge('boanerges-50', 50)
        closed = self.worktree('boanerges-51', record=False)
        self.pr('boanerges-51', 51, 'CLOSED')
        old = self.worktree('boanerges-52', days_old=8)
        out = self.run_gc()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertFalse(merged.exists())
        self.assertFalse(self.branch_exists('feature/boanerges-50'))
        self.assertIn('`boanerges_b50`', self.drops()[0])
        self.assertEqual(len(self.drops()), 1)
        for path in (closed, old):
            self.assertTrue(path.is_dir(), f'{path.name} was torn down')
        report = out.stdout
        self.assertRegex(report, r'torn down: boanerges-50')
        self.assertRegex(report, r'left behind: boanerges-51 .*PR #51 closed unmerged')
        self.assertRegex(report, r'left behind: boanerges-52 .*no commit or session for 8 days')
        self.assertIn('make wt-discard ID=boanerges-51', report)
        self.assertNotIn('make wt-down ID=boanerges-51', report)

    def test_a7_down_and_gc_leave_an_unmerged_worktree_and_point_to_the_discard_dry_run(self) -> None:
        closed = self.worktree('boanerges-58')
        self.pr('boanerges-58', 58, 'CLOSED')
        down = self.run_cmd('down', 'boanerges-58')
        self.assertNotEqual(down.returncode, 0, down.stdout)
        self.assertTrue(closed.is_dir(), 'wt-down removed an unmerged worktree')
        out = self.run_gc()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertTrue(closed.is_dir(), 'wt-gc removed an unmerged worktree')
        self.assertTrue(self.branch_exists('feature/boanerges-58'))
        self.assertEqual(self.drops(), [])
        line = next(line for line in out.stdout.splitlines() if line.startswith('left behind: boanerges-58'))
        self.assertIn('make wt-discard ID=boanerges-58', line)
        self.assertNotIn('CONFIRM', line, 'the gc line must point to the dry run, not the confirmed command')

    def test_a_worktree_without_record_that_passes_is_torn_down(self) -> None:
        bare = self.worktree('boanerges-53', record=False)
        self.merge('boanerges-53', 53)
        out = self.run_gc()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertFalse(bare.exists())
        self.assertIn('`boanerges_b53`', self.drops()[0])

    def test_an_active_worktree_is_kept_with_its_reason_not_left_behind(self) -> None:
        active = self.worktree('boanerges-54')
        self.pr('boanerges-54', 54, 'OPEN')
        out = self.run_gc()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertTrue(active.is_dir())
        self.assertRegex(out.stdout, r'kept: boanerges-54 .*merged')
        self.assertNotIn('left behind', out.stdout)
        self.assertEqual(self.drops(), [])

    def test_a_recent_session_keeps_an_old_commit_from_being_left_behind(self) -> None:
        old = self.worktree('boanerges-55', days_old=8)
        self.session(old, 2 * 24 * 60)
        out = self.run_gc()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertNotIn('left behind', out.stdout)
        self.assertRegex(out.stdout, r'kept: boanerges-55')

    def test_a_merged_worktree_with_a_live_session_is_kept(self) -> None:
        busy = self.worktree('boanerges-56')
        self.merge('boanerges-56', 56)
        self.session(busy, 1)
        out = self.run_gc()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertTrue(busy.is_dir())
        self.assertRegex(out.stdout, r'kept: boanerges-56 .*session')
        self.assertEqual(self.drops(), [])

    def test_a_broken_worktree_is_reported_and_the_sweep_goes_on(self) -> None:
        broken = self.worktree('boanerges-60')
        (broken / '.git').write_text(f'gitdir: {self.tmp / "nowhere"}\n')
        merged = self.worktree('boanerges-61')
        self.merge('boanerges-61', 61)
        out = self.run_gc()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertRegex(out.stdout, r'kept: boanerges-60 ')
        self.assertFalse(merged.exists())
        self.assertTrue(broken.is_dir())

    def test_gc_leaves_the_main_checkout_alone(self) -> None:
        self.worktree('boanerges-57')
        out = self.run_gc()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertNotIn(f'kept: {self.repo.name} ', out.stdout)
        self.assertEqual(git(self.repo, 'branch', '--show-current'), 'main')


class DiscardTest(TeardownSandbox):
    """`worktree.py discard <id> [--confirm <id>]` (make wt-discard): an unmerged worktree, dry run unless confirmed."""

    def setUp(self) -> None:
        super().setUp()
        self.wt = self.worktree('boanerges-9')
        git(self.repo, 'push', '-q', 'origin', 'feature/boanerges-9')   # pushed, its PR closed unmerged
        self.pr('boanerges-9', 51, 'CLOSED')
        self.served('boanerges-9', 'b9')
        self.tip = git(self.repo, 'rev-parse', 'refs/heads/feature/boanerges-9')

    def run_discard(self, wt_id: str = 'boanerges-9', confirm: str | None = 'boanerges-9') -> subprocess.CompletedProcess:
        return self.run_cmd('discard', wt_id, *(['--confirm', confirm] if confirm is not None else []))

    def valet_calls(self) -> list[str]:
        return [line.split(' ', 2)[2].rsplit(' E2E_PORT=', 1)[0] for line in self.lines('valet')]

    def assert_untouched(self, out: subprocess.CompletedProcess, check: str, wt: Path | None = None,
                         branch: str = 'feature/boanerges-9') -> None:
        wt = wt or self.wt
        self.assertNotEqual(out.returncode, 0, out.stdout)
        self.assertIn(check, out.stderr)
        self.assertTrue(wt.is_dir(), 'the worktree folder was removed')
        self.assertTrue(self.branch_exists(branch), 'the branch was deleted')
        self.assertEqual(self.lines('valet'), [], 'Valet was touched')
        self.assertEqual(self.drops(), [], 'the database was dropped')
        self.assertIn(str(wt), git(self.repo, 'worktree', 'list'), 'the worktree was removed from git')

    def test_a_dirty_worktree_is_refused_naming_the_whole_first_path(self) -> None:
        (self.wt / 'boanerges-9.md').write_text('changed\n')
        out = self.run_discard()
        self.assert_untouched(out, 'git status --porcelain is not empty')
        self.assertIn('(boanerges-9.md)', out.stderr)

    def test_a1_closed_unmerged_served_worktree_is_discarded_api_first(self) -> None:
        out = self.run_discard()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertEqual(self.valet_calls(), [
            'unisolate --site=api.b9.boanerges', 'unsecure api.b9.boanerges', 'unlink api.b9.boanerges',
            'unsecure app.b9.boanerges', 'unlink app.b9.boanerges',
        ])
        self.assertEqual(self.lines('proxy-valet'), [], 'Valet runs by the path its sudoers rule covers')
        self.assertEqual(len(self.drops()), 1)
        self.assertIn('DROP DATABASE IF EXISTS `boanerges_b9`', self.drops()[0])
        self.assertTrue(self.drops()[0].endswith('pwd=env'))
        self.assertFalse(self.wt.exists(), 'the worktree folder is still there')
        self.assertNotIn(str(self.wt), git(self.repo, 'worktree', 'list'))
        self.assertFalse(self.branch_exists('feature/boanerges-9'), 'the local branch is still there')
        self.assertIn('discarded: boanerges-9', out.stdout)

    def test_a2_never_served_and_no_pr_drops_only_the_database(self) -> None:
        bare = self.worktree('boanerges-11')
        out = self.run_discard('boanerges-11', 'boanerges-11')
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertEqual(self.lines('valet'), [])
        self.assertEqual(self.lines('sudo'), [], 'no Valet work, so no trust probe')
        self.assertEqual(len(self.drops()), 1)
        self.assertIn('`boanerges_b11`', self.drops()[0])
        self.assertFalse(bare.exists())
        self.assertFalse(self.branch_exists('feature/boanerges-11'))

    def test_a3_an_untracked_file_is_refused(self) -> None:
        (self.wt / 'notes.txt').write_text('invented\n')
        self.assert_untouched(self.run_discard(), 'git status')

    def test_a3_an_ignored_file_outside_the_regenerable_set_is_refused(self) -> None:
        (self.wt / 'data').mkdir()
        (self.wt / 'data' / 'invented.csv').write_text('invented\n')
        out = self.run_discard()
        self.assert_untouched(out, 'ignored')
        self.assertIn('data/', out.stderr)

    def test_a3_a_session_5_minutes_ago_is_refused(self) -> None:
        self.session(self.wt, 5)
        self.assert_untouched(self.run_discard(), 'session')

    def test_a3_a_listening_vite_port_is_refused(self) -> None:
        self.listen(6009)
        self.assert_untouched(self.run_discard(), '6009')

    def test_a3_a_main_env_not_local_is_refused(self) -> None:
        (self.repo / 'api.boanerges' / '.env').write_text(API_ENV.replace('APP_ENV=local', 'APP_ENV=production'))
        out = self.run_discard()
        self.assert_untouched(out, 'APP_ENV')
        self.assertEqual(self.lines('mysql'), [])

    def test_a3_a_label_another_worktree_holds_is_refused(self) -> None:
        other = self.worktree('b9', record=False)
        out = self.run_discard('b9', 'b9')
        self.assert_untouched(out, 'boanerges-9', wt=other, branch='feature/b9')

    def test_a3_detached_head_is_refused(self) -> None:
        git(self.wt, 'checkout', '-q', '--detach')
        out = self.run_discard()
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('detached', out.stderr)
        self.assertTrue(self.wt.is_dir())
        self.assertEqual(self.drops(), [])

    def test_a3_gh_failing_is_refused(self) -> None:
        os.environ['FAKE_GH_FAIL'] = '1'
        self.assert_untouched(self.run_discard(), 'gh')

    def test_a4_an_open_pr_is_refused_naming_it(self) -> None:
        self.prs = {}
        self.pr('boanerges-9', 7, 'OPEN')
        self.assert_untouched(self.run_discard(), 'close PR #7 first')

    def test_a_merged_pr_does_not_block(self) -> None:
        self.prs = {}
        self.merge('boanerges-9', 51)
        out = self.run_discard()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertFalse(self.wt.exists())

    def test_a5_no_confirm_is_a_dry_run_listing_what_would_go(self) -> None:
        for confirm in (None, 'other'):
            with self.subTest(confirm=confirm):
                out = self.run_discard(confirm=confirm)
                self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
                self.assertEqual(self.lines('valet'), [])
                self.assertEqual(self.drops(), [])
                self.assertTrue(self.wt.is_dir())
                self.assertTrue(self.branch_exists('feature/boanerges-9'))
                self.assertIn(str(self.wt), git(self.repo, 'worktree', 'list'))
                for item in ('api.b9.boanerges', 'app.b9.boanerges', 'unisolate', 'unsecure', 'unlink',
                             'DROP DATABASE boanerges_b9', str(self.wt), 'feature/boanerges-9', self.tip):
                    self.assertIn(item, out.stdout)
                self.assertEqual(out.stdout.strip().splitlines()[-1].strip(),
                                 'make wt-discard ID=boanerges-9 CONFIRM=boanerges-9')

    def test_a5_a_dry_run_still_runs_the_checks(self) -> None:
        (self.wt / 'notes.txt').write_text('invented\n')
        out = self.run_discard(confirm=None)
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('git status', out.stderr)
        self.assertNotIn('CONFIRM=', out.stdout)

    def test_a6_prints_the_restore_line_and_never_touches_the_remote(self) -> None:
        out = self.run_discard()
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertIn(self.tip, out.stdout)
        self.assertIn(f'git branch feature/boanerges-9 {self.tip}', out.stdout)
        self.assertLess(out.stdout.index(self.tip), out.stdout.index('discarded:'))
        self.assertFalse(any('pr close' in line for line in self.lines('gh')), self.lines('gh'))
        self.assertTrue(all(line.startswith('gh pr list ') for line in self.lines('gh')), self.lines('gh'))
        remote = git(self.origin, 'rev-parse', 'refs/heads/feature/boanerges-9')
        self.assertEqual(remote, self.tip, 'the remote branch was touched')

    def test_the_main_checkout_is_never_discarded(self) -> None:
        out = self.run_discard('boanerges', 'boanerges')
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('main checkout', out.stderr)
        self.assertTrue((self.repo / '.git').is_dir())
        self.assertEqual(self.drops(), [])
        self.assertEqual(self.lines('valet'), [])


REPO = HERE.parent
UNTRACKED_NAMES = {'.env', '.worktree.json', 'port-blocks.json'}
INVENTED_PEOPLE = {'Test Person'}
INVENTED_HOST_ENDS = ('.test', 'example.com', 'localhost', 'elsewhere', 'invented')



SQLITE_API_ENV = API_ENV.replace('DB_HOST=127.0.0.1', 'DB_CONNECTION=sqlite').replace('DB_DATABASE=boanerges', '# no DB_DATABASE')


class SqliteProjectTest(TeardownSandbox):
    """A project on SQLite (rnc: `php artisan serve`, no Valet, no MySQL): new and discard work, up refuses."""

    def setUp(self) -> None:
        super().setUp()
        (self.repo / 'api.boanerges' / '.env').write_text(SQLITE_API_ENV)

    def test_new_makes_the_worktree_without_asking_mysql(self) -> None:
        out = self.run_cmd('new', 'boanerges-98', '--slug', 'sqlite')
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertTrue((self.repo / '.claude' / 'worktrees' / 'boanerges-98').is_dir())
        self.assertEqual(self.lines('mysql'), [], 'MySQL was asked about a SQLite project')

    def test_discard_removes_the_worktree_and_drops_no_database(self) -> None:
        wt = self.worktree('boanerges-9')
        git(self.repo, 'push', '-q', 'origin', 'feature/boanerges-9')
        self.pr('boanerges-9', 51, 'CLOSED')
        out = self.run_cmd('discard', 'boanerges-9', '--confirm', 'boanerges-9')
        self.assertEqual(out.returncode, 0, out.stderr + out.stdout)
        self.assertFalse(wt.exists())
        self.assertFalse(self.branch_exists('feature/boanerges-9'))
        self.assertEqual(self.drops(), [], 'a database was dropped for a SQLite project')
        self.assertNotIn('DROP DATABASE', out.stdout)

    def test_up_refuses_naming_sqlite_before_valet_or_mysql(self) -> None:
        wt = self.repo / '.claude' / 'worktrees' / 'boanerges-7'
        git(self.repo, 'worktree', 'add', '-q', '-b', 'feature/boanerges-7-x', str(wt), 'main')
        out = self.run_cmd('up', cwd=wt)
        self.assertNotEqual(out.returncode, 0)
        self.assertIn('SQLite', out.stderr)
        self.assertEqual(self.lines('valet') + self.lines('mysql'), [])


class TrackedFilesTest(unittest.TestCase):
    """A17: nothing machine- or person-specific is tracked, and the fixtures use invented names only."""

    def test_a17_no_env_record_or_port_file_is_tracked(self) -> None:
        tracked = subprocess.run(['git', 'ls-files', '-z'], cwd=REPO, check=True, capture_output=True,
                                 text=True).stdout.split('\0')
        self.assertEqual([f for f in tracked if f and os.path.basename(f) in UNTRACKED_NAMES], [])

    def test_a17_fixtures_name_only_invented_people_and_hosts(self) -> None:
        source = Path(__file__).read_text()
        people = {GIT_ENV['GIT_AUTHOR_NAME'], GIT_ENV['GIT_COMMITTER_NAME']}
        self.assertEqual(people - INVENTED_PEOPLE, set())
        emails = set(re.findall(r'[\w.+-]+@((?:[\w-]+\.)+[a-z]{2,})\b', source))
        self.assertEqual({d for d in emails if not d.endswith(INVENTED_HOST_ENDS)}, set())
        hosts = set(re.findall(r'https?://(?:[^@/\s\'"]+@)?([\w.-]+)', source))
        self.assertEqual({h for h in hosts if not h.endswith(INVENTED_HOST_ENDS)}, set())


if __name__ == '__main__':
    unittest.main(verbosity=1)
