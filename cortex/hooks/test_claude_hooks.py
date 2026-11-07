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
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
HOOKS = ROOT / '.claude' / 'hooks'


def run_hook(script: str, payload: object, dry_run: bool = False) -> subprocess.CompletedProcess:
    env = {**os.environ, 'CLAUDE_PROJECT_DIR': str(ROOT)}
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
