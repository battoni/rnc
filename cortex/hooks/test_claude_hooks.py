#!/usr/bin/env python3
"""Feed the Claude Code hooks a real hook payload on stdin and check what they do.

Claude Code hands a PreToolUse/PostToolUse hook its input as JSON on stdin, with the tool's
arguments under `tool_input` and `file_path` as an ABSOLUTE path. The first version of these
hooks read a `CLAUDE_TOOL_INPUT` variable that is never set and matched `api.rnc/*.php` against
an absolute path, so neither ever fired. These tests pipe the payload exactly as it arrives.

`CLAUDE_HOOK_DRY_RUN=1` makes post-edit.sh print the formatter command instead of running it.

The tests live here, not in .claude/hooks/, because every file in that directory becomes a
row of the features catalogue (app.rnc/scripts/build-features.mjs).

Run: python3 cortex/hooks/test_claude_hooks.py
"""
from __future__ import annotations

import json
import os
import subprocess
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
HOOKS = ROOT / '.claude' / 'hooks'


def run_hook(script: str, payload: object, dry_run: bool = False, project_dir: Path = ROOT) -> subprocess.CompletedProcess:
    env = {**os.environ, 'CLAUDE_PROJECT_DIR': str(project_dir)}
    env.pop('CLAUDE_TOOL_INPUT', None)

    if dry_run:
        env['CLAUDE_HOOK_DRY_RUN'] = '1'

    stdin = payload if isinstance(payload, str) else json.dumps(payload)

    return subprocess.run(
        ['bash', str(HOOKS / script)],
        input=stdin,
        capture_output=True,
        text=True,
        env=env,
        cwd='/',
        timeout=30,
    )


def bash_payload(command: str) -> dict:
    return {'hook_event_name': 'PreToolUse', 'tool_name': 'Bash', 'tool_input': {'command': command}}


def edit_payload(relative: str) -> dict:
    return {
        'hook_event_name': 'PostToolUse',
        'tool_name': 'Edit',
        'tool_input': {'file_path': str(ROOT / relative)},
    }


class PreBashGuardTest(unittest.TestCase):
    BLOCKED = [
        'git push --force',
        'git push -f origin main',
        'git push origin main --force',
        'git push origin main -f',
        'rm -rf /',
        'rm -rf / --no-preserve-root',
        'rm -rf /*',
        'rm -rf ~',
        'rm -rf ~/',
        'sqlite3 db "DROP TABLE users"',
        'mysql -e "drop table users"',
        'cat x > /dev/sda',
    ]

    ALLOWED = [
        'ls -la',
        'git push origin feature/x',
        'git status',
        'rm -rf /private/tmp/claude-501/scratch',
        'rm -rf ~/Work/scratch',
        'git log --format=%f',
    ]

    def test_blocks_the_patterns_it_names(self):
        for command in self.BLOCKED:
            with self.subTest(command=command):
                result = run_hook('pre-bash-guard.sh', bash_payload(command))
                self.assertEqual(result.returncode, 2, result.stderr)
                self.assertIn('BLOCKED', result.stderr)

    def test_lets_ordinary_commands_through(self):
        for command in self.ALLOWED:
            with self.subTest(command=command):
                result = run_hook('pre-bash-guard.sh', bash_payload(command))
                self.assertEqual(result.returncode, 0, result.stderr)

    def test_fails_open_on_a_payload_it_cannot_read(self):
        for payload in ['', 'not json', '[]', '{"tool_input": "x"}']:
            with self.subTest(payload=payload):
                self.assertEqual(run_hook('pre-bash-guard.sh', payload).returncode, 0)


def git(cwd: Path, *args: str) -> str:
    return subprocess.run(
        ['git', *args], cwd=cwd, check=True, capture_output=True, text=True,
        env={**os.environ, 'GIT_CONFIG_GLOBAL': '/dev/null', 'GIT_CONFIG_SYSTEM': '/dev/null'},
    ).stdout.strip()


def make_repo(path: Path) -> Path:
    path.mkdir(parents=True)
    git(path, 'init', '-q', '-b', 'main')
    git(path, 'config', 'user.email', 'test@example.com')
    git(path, 'config', 'user.name', 'Test')
    (path / 'api').mkdir()
    (path / 'api' / 'x.php').write_text('<?php\n')
    git(path, 'add', '.')
    git(path, 'commit', '-q', '-m', 'init')
    return path


class BranchGuardTest(unittest.TestCase):
    """The guard keeps the main checkout on main and each worktree on its own branch (A1, A2).

    On 10/11/2026 a fix session ran `git checkout -b` in the main checkout and took another
    session's files with it. The guard reads the hook's `cwd`, so it knows where a command runs.
    """

    @classmethod
    def setUpClass(cls):
        cls.tmp = tempfile.TemporaryDirectory()
        base = Path(os.path.realpath(cls.tmp.name))
        cls.main = make_repo(base / 'repo')
        cls.worktree = base / 'repo' / '.claude' / 'worktrees' / 'a'
        git(cls.main, 'worktree', 'add', '-q', '-b', 'feature/a', str(cls.worktree))
        cls.other = make_repo(base / 'other')

    @classmethod
    def tearDownClass(cls):
        cls.tmp.cleanup()

    def guard(self, command: str, cwd: Path) -> subprocess.CompletedProcess:
        payload = {**bash_payload(command), 'cwd': str(cwd)}
        return run_hook('pre-bash-guard.sh', payload, project_dir=self.main)

    def assert_blocked(self, command: str, cwd: Path):
        result = self.guard(command, cwd)
        self.assertEqual(result.returncode, 2, f'{command!r} in {cwd} passed')
        self.assertIn('BLOCKED', result.stderr)

    def assert_allowed(self, command: str, cwd: Path):
        result = self.guard(command, cwd)
        self.assertEqual(result.returncode, 0, f'{command!r} in {cwd}: {result.stderr}')

    def test_blocks_branch_moves_and_commits_in_the_main_checkout(self):
        for command in [
            'git checkout -b chore/x',
            'git checkout feature/y',
            'git checkout feature/a',
            'git checkout main',
            'git checkout -',
            'git switch main',
            'git switch -c chore/x',
            'git commit -m x',
            'rtk git commit -m x',
            'git -c core.pager=cat commit -m x',
            'git merge feature/a',
            'git cherry-pick abc123',
            'git revert HEAD',
            'git branch -f main HEAD~1',
            'gh pr checkout 50',
            'rtk gh pr checkout 50',
            'rtk git status && rtk git checkout -b chore/x',
            'GIT_EDITOR=true git commit',
            'echo a#b; git commit -m x',
            'curl https://x.test/#top && git commit -m x',
            'if true; then git commit -m x; fi',
            'for f in a; do git commit -m x; done',
            '! git commit -m x',
            '{ git commit -m x; }',
        ]:
            with self.subTest(command=command):
                self.assert_blocked(command, self.main)

    def test_blocks_from_a_subfolder_of_the_main_checkout(self):
        self.assert_blocked('git checkout -b chore/x', self.main / 'api')

    def test_cd_and_dash_c_move_the_place_a_command_runs(self):
        self.assert_blocked(f'cd {self.main} && git commit -m x', self.worktree)
        self.assert_blocked(f'git -C {self.main} switch main', self.worktree)
        self.assert_allowed(f'cd {self.worktree} && git commit -m x', self.main)
        self.assert_allowed(f'rtk git -C {self.worktree} commit -m x', self.main)

    def test_lets_paths_worktrees_and_reads_through_in_the_main_checkout(self):
        for command in [
            'git checkout -- api/x.php',
            'git checkout origin/main -- api/x.php',
            'git checkout HEAD api/x.php',
            'git checkout api/x.php',
            'git restore api/x.php',
            'git worktree add -b feature/z .claude/worktrees/z origin/main',
            'rtk git worktree add .claude/worktrees/z',
            'git status',
            'rtk git log --oneline',
            'git branch -a',
            'git fetch origin',
            'git pull --ff-only',
            'git push origin feature/a',
            'ls -la',
        ]:
            with self.subTest(command=command):
                self.assert_allowed(command, self.main)

    def test_text_inside_a_quoted_argument_never_matches(self):
        for command in [
            'gh pr create --title x --body "then git checkout -b y; git commit -m z"',
            "echo 'git switch main && git commit'",
            'rtk git log --grep="git commit"',
        ]:
            with self.subTest(command=command):
                self.assert_allowed(command, self.main)

    def test_a_worktree_may_commit_but_not_leave_its_branch(self):
        for command in ['git checkout feature/b', 'git switch main', 'git checkout -b feature/c',
                        'git switch -c x', 'git checkout main', 'gh pr checkout 50 && git status']:
            with self.subTest(command=command):
                self.assert_blocked(command, self.worktree)

        for command in ['git commit -m x', 'rtk git commit -m x', 'git checkout feature/a',
                        'git switch feature/a', 'git checkout -- api/x.php', 'git merge origin/main',
                        'git restore --staged api/x.php']:
            with self.subTest(command=command):
                self.assert_allowed(command, self.worktree)

    def test_another_repo_is_not_its_business(self):
        for command in ['git checkout -b chore/x', 'git commit -m x', 'git switch main']:
            with self.subTest(command=command):
                self.assert_allowed(command, self.other)

    def test_fails_open_outside_a_repo_or_on_broken_input(self):
        self.assert_allowed('git checkout -b chore/x', Path('/'))
        self.assert_allowed('git commit -m "unterminated', self.main)
        bad = run_hook('pre-bash-guard.sh', '{"tool_input": {"command": "git commit"}, "cwd": ', project_dir=self.main)
        self.assertEqual(bad.returncode, 0)

    def test_the_message_names_the_recovery(self):
        result = self.guard('git commit -m x', self.main)
        self.assertIn('main checkout', result.stderr)
        self.assertIn('! git switch main', result.stderr)
        self.assertIn('wt-new', result.stderr)


class PostEditTest(unittest.TestCase):
    def test_runs_pint_on_the_edited_php_file_only(self):
        result = run_hook('post-edit.sh', edit_payload('api.rnc/app/Models/User.php'), dry_run=True)

        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('vendor/bin/pint', result.stdout)
        self.assertIn('app/Models/User.php', result.stdout)
        self.assertNotIn('--dirty', result.stdout)

    def test_runs_eslint_on_the_edited_frontend_file_only(self):
        for relative, expected in [('app.rnc/src/main.ts', 'src/main.ts'), ('app.rnc/src/App.vue', 'src/App.vue')]:
            with self.subTest(relative=relative):
                result = run_hook('post-edit.sh', edit_payload(relative), dry_run=True)

                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertIn('eslint --fix', result.stdout)
                self.assertIn(expected, result.stdout)
                self.assertNotIn('npm run lint', result.stdout)

    def test_ignores_files_outside_the_two_projects(self):
        for relative in ['CLAUDE.md', 'app.rnc/vite.config.ts', 'cortex/sync/sync.py', 'app.rnc/src/does-not-exist.ts']:
            with self.subTest(relative=relative):
                result = run_hook('post-edit.sh', edit_payload(relative), dry_run=True)

                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertEqual(result.stdout.strip(), '')

    def test_fails_open_on_a_payload_it_cannot_read(self):
        for payload in ['', 'not json', '{"tool_input": {}}']:
            with self.subTest(payload=payload):
                result = run_hook('post-edit.sh', payload, dry_run=True)

                self.assertEqual(result.returncode, 0)
                self.assertEqual(result.stdout.strip(), '')


if __name__ == '__main__':
    unittest.main(verbosity=1)
