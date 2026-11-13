"""Tests for scripts/flow-status.py — run: python3 scripts/test_flow_status.py

Every repository here is a throwaway git repo in a temp folder, with invented features.
"""
import importlib.util
import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('flow_status', HERE / 'flow-status.py')
flow = importlib.util.module_from_spec(spec)
sys.modules['flow_status'] = flow
spec.loader.exec_module(flow)

STATE = """# State: {id} — {title}
Stage: {stage}

## Next
{next}

## Pitfalls
- none
"""


def git(cwd, *args):
    env = {**os.environ, 'GIT_AUTHOR_NAME': 't', 'GIT_AUTHOR_EMAIL': 't@t', 'GIT_COMMITTER_NAME': 't', 'GIT_COMMITTER_EMAIL': 't@t'}
    subprocess.run(['git', '-C', str(cwd), *args], check=True, capture_output=True, env=env)


def write_state(repo, folder, feature_id, stage, next_line, title='Invented feature'):
    path = repo / 'specs' / folder / 'state.md'
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(STATE.format(id=feature_id, title=title, stage=stage, next=next_line))


class FlowStatusTest(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp())
        self.repo = self.tmp / 'acme'
        self.repo.mkdir()
        git(self.repo, 'init', '-q', '-b', 'main')
        (self.repo / '.claude' / 'commands').mkdir(parents=True)
        (self.repo / '.claude' / 'commands' / 'battoni-intent.md').write_text('# Intent\n')
        (self.repo / '.cortex-sync.conf').write_text('PROJECT=acme\nTASK_PREFIX=acme\n')
        write_state(self.repo, 'acme-1-old-thing', 'acme-1', 'delivered — PR #3', 'Nothing.')
        git(self.repo, 'add', '-A')
        git(self.repo, 'commit', '-q', '-m', 'init')

    def feature_branch(self, branch, folder, feature_id, stage, next_line, worktree=None):
        if worktree:
            git(self.repo, 'worktree', 'add', '-q', '-b', branch, str(worktree), 'main')
            write_state(worktree, folder, feature_id, stage, next_line)
            git(worktree, 'add', '-A')
            git(worktree, 'commit', '-q', '-m', f'{feature_id}: state')
            return

        git(self.repo, 'checkout', '-q', '-b', branch)
        write_state(self.repo, folder, feature_id, stage, next_line)
        git(self.repo, 'add', '-A')
        git(self.repo, 'commit', '-q', '-m', f'{feature_id}: state')
        git(self.repo, 'checkout', '-q', 'main')

    def items(self, show_delivered=False):
        return {item.id: item for item in flow.collect(self.repo, with_prs=False, show_delivered=show_delivered)}

    def test_feature_on_its_branch_is_read_from_that_branch(self):
        self.feature_branch('feature/acme-2-new-thing', 'acme-2-new-thing', 'acme-2', 'build F3/9', '**Next:** `/battoni-start-session acme-2` builds F4.', worktree=self.tmp / 'wt-2')

        item = self.items()['acme-2']

        self.assertEqual(item.stage, 'build F3/9')
        self.assertEqual(item.next, 'Next: /battoni-start-session acme-2 builds F4.')
        self.assertEqual(item.branch, 'feature/acme-2-new-thing')
        self.assertEqual(Path(item.worktree).resolve(), (self.tmp / 'wt-2').resolve())

    def test_delivered_feature_on_main_is_hidden_unless_asked(self):
        self.assertNotIn('acme-1', self.items())
        self.assertEqual(self.items(show_delivered=True)['acme-1'].stage, 'delivered — PR #3')

    def test_small_track_branch_without_a_folder_is_listed(self):
        git(self.repo, 'checkout', '-q', '-b', 'fix/acme-5-typo')
        (self.repo / 'x.txt').write_text('x')
        git(self.repo, 'add', '-A')
        git(self.repo, 'commit', '-q', '-m', 'acme-5: Fix the typo')
        git(self.repo, 'checkout', '-q', 'main')

        item = self.items()['acme-5']

        self.assertEqual(item.stage, 'small (no spec folder)')
        self.assertEqual(item.worktree, None)

    def test_merged_branch_is_not_listed_as_ongoing(self):
        git(self.repo, 'checkout', '-q', '-b', 'fix/acme-6-done')
        (self.repo / 'y.txt').write_text('y')
        git(self.repo, 'add', '-A')
        git(self.repo, 'commit', '-q', '-m', 'acme-6: Fix it')
        git(self.repo, 'checkout', '-q', 'main')
        git(self.repo, 'merge', '-q', '--no-ff', '-m', 'merge', 'fix/acme-6-done')

        self.assertNotIn('acme-6', self.items())

    def test_merged_small_track_is_read_from_the_history_when_asked(self):
        git(self.repo, 'checkout', '-q', '-b', 'fix/acme-6-done')
        (self.repo / 'y.txt').write_text('y')
        git(self.repo, 'add', '-A')
        git(self.repo, 'commit', '-q', '-m', 'acme-6: Fix the login link')
        (self.repo / 'z.txt').write_text('z')
        git(self.repo, 'add', '-A')
        git(self.repo, 'commit', '-q', '-m', 'acme-6: Clear the leftover row')
        git(self.repo, 'checkout', '-q', 'main')
        git(self.repo, 'merge', '-q', '--no-ff', '-m', 'Merge pull request #9 from someone/fix/acme-6-done', 'fix/acme-6-done')
        git(self.repo, 'branch', '-q', '-D', 'fix/acme-6-done')

        self.assertNotIn('acme-6', self.items())

        rows = [[part.strip() for part in line.split(' | ')] for line in flow.render([(self.repo, flow.collect(self.repo, with_prs=False, show_delivered=True))]).splitlines()]

        self.assertIn(['acme-6', 'merged · PR #9', 'Fix the login link', '—'], rows)

    def test_feature_branch_checked_out_in_the_main_folder_is_flagged(self):
        self.feature_branch('feature/acme-7-risky', 'acme-7-risky', 'acme-7', 'spec', 'Write it.')
        git(self.repo, 'checkout', '-q', 'feature/acme-7-risky')

        item = self.items()['acme-7']

        self.assertTrue(item.in_main_folder)
        self.assertIn('main folder', flow.render([(self.repo, flow.collect(self.repo, with_prs=False))]))

    def test_discover_finds_flow_repos_and_skips_excluded_ones(self):
        other = self.tmp / 'animalsfirst' / 'code'
        other.mkdir(parents=True)
        git(other, 'init', '-q', '-b', 'main')
        (other / '.claude' / 'commands').mkdir(parents=True)
        (other / '.claude' / 'commands' / 'battoni-intent.md').write_text('# Intent\n')
        plain = self.tmp / 'plain'
        plain.mkdir()
        git(plain, 'init', '-q', '-b', 'main')

        found = [path.resolve() for path in flow.discover([self.tmp])]

        self.assertEqual(found, [self.repo.resolve()])

    def test_render_says_how_to_resume_in_the_worktree(self):
        self.feature_branch('feature/acme-2-new-thing', 'acme-2-new-thing', 'acme-2', 'build F3/9', 'Build F4.', worktree=self.tmp / 'wt-2')

        text = flow.render([(self.repo, flow.collect(self.repo, with_prs=False))])

        row = [part.strip() for part in text.splitlines()[0].split(' | ')]

        self.assertEqual(row, ['acme-2', 'build F3/9', 'Invented feature', f"cd {(self.tmp / 'wt-2').resolve()} && cc acme-2"])

    def test_one_line_per_feature_with_short_state(self):
        self.feature_branch('feature/acme-2-new-thing', 'acme-2-new-thing', 'acme-2', 'build F3/9', 'Build F4.', worktree=self.tmp / 'wt-2')
        self.feature_branch('feature/acme-3-other', 'acme-3-other', 'acme-3', 'verified — PR not opened (remote empty)', 'Publish.')

        lines = flow.render([(self.repo, flow.collect(self.repo, with_prs=False, show_delivered=True))]).splitlines()
        rows = [[part.strip() for part in line.split(' | ')] for line in lines]

        self.assertEqual([row[:2] for row in rows], [['acme-1', 'merged · PR #3'], ['acme-2', 'build F3/9'], ['acme-3', 'verified']])
        self.assertEqual(rows[0][3], '—')
        self.assertEqual(rows[2][3], f"git -C {self.repo} worktree add {self.repo}/.claude/worktrees/acme-3 feature/acme-3-other")
        bars = lambda line: tuple(index for index in range(len(line)) if line.startswith(' | ', index))
        self.assertEqual(len({bars(line) for line in lines}), 1)

    def test_small_track_what_is_the_branch_slug_in_words(self):
        git(self.repo, 'checkout', '-q', '-b', 'fix/acme-5-fix-the-typo')
        (self.repo / 'x.txt').write_text('x')
        git(self.repo, 'add', '-A')
        git(self.repo, 'commit', '-q', '-m', 'acme-5: Fix the typo')
        git(self.repo, 'checkout', '-q', 'main')

        row = [part.strip() for part in flow.render([(self.repo, flow.collect(self.repo, with_prs=False))]).splitlines()[0].split(' | ')]

        self.assertEqual(row[:3], ['acme-5', 'small', 'fix the typo'])

    def test_next_is_shown_whole_not_cut(self):
        long_next = 'Build F4: ' + 'every route gets its own key, ' * 10 + 'then F5.'
        self.feature_branch('feature/acme-2-new-thing', 'acme-2-new-thing', 'acme-2', 'build F3/9', long_next, worktree=self.tmp / 'wt-2')

        self.assertEqual(self.items()['acme-2'].next, long_next)

    def test_current_repo_from_a_worktree_is_its_main_folder(self):
        self.feature_branch('feature/acme-2-new-thing', 'acme-2-new-thing', 'acme-2', 'build F3/9', 'Build F4.', worktree=self.tmp / 'wt-2')
        (self.tmp / 'wt-2' / 'specs').mkdir(exist_ok=True)

        self.assertEqual(flow.current_repo(self.tmp / 'wt-2' / 'specs').resolve(), self.repo.resolve())
        self.assertEqual(flow.current_repo(self.repo).resolve(), self.repo.resolve())
        self.assertIsNone(flow.current_repo(self.tmp))


if __name__ == '__main__':
    unittest.main(verbosity=1)
