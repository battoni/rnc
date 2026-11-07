#!/usr/bin/env python3
"""Tests for the client-handoff Makefile rewrite (cortex/sync/handoff.py).

A handoff copy has no cortex/, so any make target left behind that still runs
something under cortex/ is a broken command handed to the client, and any
prerequisite that names a dropped target makes the rule that lists it fail.

Run:  python3 cortex/sync/test_handoff.py   (exit 0 = pass)
"""
from __future__ import annotations

import re
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from handoff import MAKE_DROP_TARGETS, TARGET_RE, strip_make_targets  # noqa: E402

REPO = Path(__file__).resolve().parents[2]
MAKEFILE = REPO / 'Makefile'


def parse_rules(text: str) -> dict[str, dict[str, list[str]]]:
    """target -> {'prerequisites': [...], 'recipe': [...]} (rule lines merged)."""
    rules: dict[str, dict[str, list[str]]] = {}
    current: str | None = None

    for line in text.split('\n'):
        if line.startswith('\t') and current:
            rules[current]['recipe'].append(line)
            continue

        match = TARGET_RE.match(line)

        if not match or line.startswith('.'):
            current = None
            continue

        current = match.group(1)
        head = line[match.end():].split('##', 1)[0]
        entry = rules.setdefault(current, {'prerequisites': [], 'recipe': []})
        entry['prerequisites'].extend(head.split())
        entry['recipe'].append(line)

    return rules


class HandoffMakefileTest(unittest.TestCase):
    def setUp(self) -> None:
        self.stripped = strip_make_targets(MAKEFILE.read_text(encoding='utf-8'))
        self.rules = parse_rules(self.stripped)

    def test_no_kept_target_cites_cortex(self) -> None:
        offenders = sorted(
            name for name, rule in self.rules.items()
            if any('cortex/' in line for line in rule['recipe'])
        )
        self.assertEqual(offenders, [], f'targets still running cortex/ in a handoff copy: {offenders}')

    def test_no_prerequisite_names_a_dropped_target(self) -> None:
        dangling = sorted(
            f'{name} -> {prerequisite}'
            for name, rule in self.rules.items()
            for prerequisite in rule['prerequisites']
            if prerequisite not in self.rules
        )
        self.assertEqual(dangling, [], f'prerequisites with no rule in a handoff copy: {dangling}')

    def test_phony_lists_no_dropped_target(self) -> None:
        phony = re.search(r'^\.PHONY:(.*)$', self.stripped, re.MULTILINE)
        self.assertIsNotNone(phony)
        self.assertEqual(sorted(set(phony.group(1).split()) & MAKE_DROP_TARGETS), [])

    def test_pre_commit_dry_runs(self) -> None:
        if not shutil.which('make'):
            self.skipTest('make is not installed')

        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'Makefile'
            path.write_text(self.stripped, encoding='utf-8')
            result = subprocess.run(
                ['make', '-n', '-f', str(path), 'pre-commit'],
                cwd=directory, capture_output=True, text=True,
            )

        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertNotIn('cortex/', result.stdout)


if __name__ == '__main__':
    unittest.main()
