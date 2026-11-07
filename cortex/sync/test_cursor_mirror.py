#!/usr/bin/env python3
"""Tests for cursor_mirror.py — the .claude → .cursor/rules parity gate.

The gate once covered rules and commands only, so three skill mirrors drifted for months while
`--check` stayed green. These tests build a throwaway tree and check that a drifted skill mirror
is reported, then run the check against the real repository.

Run: python3 cortex/sync/test_cursor_mirror.py
"""
from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import cursor_mirror  # noqa: E402

SKILL = '---\nname: demo\ndescription: A demo skill.\n---\n# Demo\n\nThe canonical body.\n'
MIRROR_FRONTMATTER = '---\ndescription: Demo for Cursor\nglobs: demo/**/*\n---\n'


def build_tree(root: Path, mirror_body: str | None) -> None:
    (root / '.claude' / 'rules').mkdir(parents=True)
    (root / '.claude' / 'commands').mkdir(parents=True)
    (root / '.claude' / 'skills' / 'demo').mkdir(parents=True)
    (root / '.claude' / 'skills' / 'no-mirror').mkdir(parents=True)
    (root / '.cursor' / 'rules').mkdir(parents=True)

    (root / '.claude' / 'skills' / 'demo' / 'SKILL.md').write_text(SKILL, encoding='utf-8')
    (root / '.claude' / 'skills' / 'no-mirror' / 'SKILL.md').write_text(SKILL, encoding='utf-8')

    if mirror_body is not None:
        mirror = root / '.cursor' / 'rules' / 'demo.mdc'
        mirror.write_text(MIRROR_FRONTMATTER + mirror_body, encoding='utf-8')


class SkillMirrorTest(unittest.TestCase):
    def test_a_drifted_skill_mirror_fails_the_check(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            build_tree(root, '# Demo\n\nAn old body.\n')

            self.assertEqual(cursor_mirror.run(root, check=True), 1)

    def test_an_identical_skill_mirror_passes(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            build_tree(root, '# Demo\n\nThe canonical body.\n')

            self.assertEqual(cursor_mirror.run(root, check=True), 0)

    def test_sync_rewrites_the_body_and_keeps_the_mdc_frontmatter(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            build_tree(root, 'stale\n')

            self.assertEqual(cursor_mirror.run(root, check=False), 0)

            mirror = (root / '.cursor' / 'rules' / 'demo.mdc').read_text(encoding='utf-8')
            self.assertEqual(mirror, MIRROR_FRONTMATTER + '# Demo\n\nThe canonical body.\n')
            self.assertEqual(cursor_mirror.run(root, check=True), 0)

    def test_a_skill_without_a_mirror_is_not_given_one(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            build_tree(root, None)

            self.assertEqual(cursor_mirror.run(root, check=False), 0)
            self.assertFalse((root / '.cursor' / 'rules' / 'no-mirror.mdc').exists())
            self.assertFalse((root / '.cursor' / 'rules' / 'demo.mdc').exists())


class RepositoryTest(unittest.TestCase):
    def test_the_repository_mirrors_are_in_sync(self):
        self.assertEqual(cursor_mirror.run(cursor_mirror.ROOT, check=True), 0)


if __name__ == '__main__':
    unittest.main(verbosity=1)
