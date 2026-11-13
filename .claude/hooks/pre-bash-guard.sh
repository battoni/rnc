#!/bin/bash
# Blocks dangerous bash operations before they run.
# Claude Code sends the PreToolUse payload as JSON on stdin (the command is tool_input.command,
# the session's working directory is cwd).
# Exit code 2 = block the command and show the stderr message to Claude.
# Anything this script cannot read lets the command through (fail-open): a guard that breaks
# every Bash call is worse than no guard. Tests: python3 cortex/hooks/test_claude_hooks.py
#
# Branch guard (boanerges-7): the main checkout stays on main and each worktree stays on its own
# branch. On 10/11/2026 a fix session ran `git checkout -b` in the main checkout and carried another
# session's work with it, so the guard now reads where a command runs, not only what it says.
# It acts only inside this hook's own repo (same git common dir as CLAUDE_PROJECT_DIR).

HOOK_INPUT="$(cat)" python3 - <<'PY'
import json
import os
import re
import shlex
import subprocess
import sys

try:
    payload = json.loads(os.environ.get('HOOK_INPUT', ''))
    command = payload['tool_input']['command']
except (ValueError, KeyError, TypeError):
    sys.exit(0)

if not isinstance(command, str):
    sys.exit(0)

END = r'(?=\s|$|[;&|)"\'])'

# Each entry: (what the message names, pattern). `rm -rf /` must not match `rm -rf /tmp/x`,
# so the root and home forms only match when nothing but `/`, `/*` or `~/` follows.
BLOCKED = [
    ('rm -rf /', r'\brm\s+-[a-zA-Z]*[rR][a-zA-Z]*\s+(--no-preserve-root\s+)?/\*?' + END),
    ('rm -rf ~', r'\brm\s+-[a-zA-Z]*[rR][a-zA-Z]*\s+~/?\*?' + END),
    ('drop table', r'(?i)\bdrop\s+table\b'),
    ('git push --force', r'\bgit\b[^;&|]*\bpush\b[^;&|]*\s(--force\S*|-[a-zA-Z]*f[a-zA-Z]*)' + END),
    ('> /dev/sda', r'>\s*/dev/sd[a-z]'),
]

for label, pattern in BLOCKED:
    if re.search(pattern, command):
        print(f'BLOCKED: Dangerous command pattern detected: {label}', file=sys.stderr)
        sys.exit(2)


# ---- Branch guard -----------------------------------------------------------------------------

def segments(text):
    """Split into simple commands at ; && || | & ( ) and newlines, honouring quotes."""
    lexer = shlex.shlex(text, posix=True, punctuation_chars=';&|()\n')
    lexer.whitespace = ' \t\r'
    lexer.whitespace_split = True
    # shlex ends the line at any `#`, bash only at one that starts a word: `echo a#b; git commit` hid
    # the commit. With no comment character a real comment is read as words, which can only block more.
    lexer.commenters = ''
    current = []
    for token in lexer:
        if token and set(token) <= set(';&|()\n'):
            if current:
                yield current
            current = []
        else:
            current.append(token)
    if current:
        yield current


# Wrappers, and the shell words that can stand before a command (`if true; then git commit`).
WRAPPERS = {'rtk', 'proxy', 'env', 'command', 'exec', 'time', 'nice',
            'if', 'then', 'elif', 'else', 'while', 'until', 'do', '!', '{'}
ASSIGNMENT = re.compile(r'^[A-Za-z_][A-Za-z0-9_]*=')


def strip_prefix(words):
    """Drop `VAR=x`, `rtk`, `rtk proxy`, `env` and the like in front of the real command."""
    while words and (words[0] in WRAPPERS or ASSIGNMENT.match(words[0])):
        words = words[1:]
    return words


def git_place(directory):
    """(git dir, common dir, branch) of a directory, or None when it is not in a repo."""
    try:
        out = subprocess.run(
            ['git', '-C', directory, 'rev-parse', '--path-format=absolute',
             '--git-dir', '--git-common-dir', '--abbrev-ref', 'HEAD'],
            capture_output=True, text=True, timeout=5,
        )
    except (OSError, subprocess.SubprocessError):
        return None
    lines = out.stdout.split('\n')
    if out.returncode != 0 or len(lines) < 3:
        return None
    return os.path.realpath(lines[0]), os.path.realpath(lines[1]), lines[2]


def resolve(base, path):
    return os.path.normpath(os.path.join(base, os.path.expanduser(path)))


def git_parts(words):
    """For `git [global options] <sub> <args>`: (directory from -C or None, sub, args)."""
    directory, i = None, 1
    while i < len(words):
        word = words[i]
        if word == '-C' and i + 1 < len(words):
            directory, i = words[i + 1], i + 2
        elif word in ('-c', '--git-dir', '--work-tree', '--namespace') and i + 1 < len(words):
            i += 2
        elif word.startswith('-'):
            i += 1
        else:
            return directory, word, words[i + 1:]
    return directory, None, []


CREATE_FLAGS = {'-b', '-B', '-c', '-C', '--orphan', '--detach', '--create', '--force-create'}


def switch_target(sub, args, cwd):
    """The branch a checkout/switch moves to: a name, '' for a new or unnamed one, None for a path form."""
    if '--' in args and args.index('--') < len(args) - 1:
        return None
    if any(a in ('-p', '--patch') for a in args):
        return None
    if any(a in CREATE_FLAGS or a.startswith(('--orphan=', '--detach')) for a in args):
        return ''
    positional = [a for a in args if a != '--' and (a == '-' or not a.startswith('-'))]
    if not positional:
        return None
    if sub == 'checkout':
        if len(positional) > 1:
            return None  # <tree-ish> <path>: restores files, HEAD does not move
        if os.path.exists(resolve(cwd, positional[0])):
            return None  # `git checkout file.php`
    return '' if positional[0] == '-' else positional[0]


MAIN_ONLY = {'commit', 'merge', 'cherry-pick', 'revert'}


def check(words, cwd, project_common):
    """A block reason for one simple command, or None."""
    words = strip_prefix(words)
    if not words:
        return None
    tool = os.path.basename(words[0])

    if tool == 'gh':
        if words[1:3] == ['pr', 'checkout']:
            action, sub, args, directory = 'gh pr checkout', 'switch', [''], cwd
        else:
            return None
    elif tool == 'git':
        directory, sub, args = git_parts(words)
        directory = resolve(cwd, directory) if directory else cwd
        if sub not in MAIN_ONLY | {'checkout', 'switch', 'branch'}:
            return None
        action = f'git {sub}'
    else:
        return None

    place = git_place(directory)
    if place is None or place[1] != project_common:
        return None
    git_dir, common_dir, branch = place
    in_main = git_dir == common_dir

    if sub in ('checkout', 'switch'):
        target = '' if tool == 'gh' else switch_target(sub, args, directory)
        if target is None:
            return None
        if in_main:
            return f'`{action}` in the main checkout'
        if target != branch:
            return f'`{action}` would move this worktree off its branch `{branch}`'
        return None

    if not in_main:
        return None
    if sub == 'branch':
        forced = any(a == '--force' or re.match(r'^-[a-zA-Z]*f', a) for a in args)
        return '`git branch -f` in the main checkout' if forced else None
    return f'`{action}` in the main checkout'


def branch_guard():
    project = os.environ.get('CLAUDE_PROJECT_DIR')
    cwd = payload.get('cwd') if isinstance(payload, dict) else None
    cwd = cwd if isinstance(cwd, str) and cwd else os.getcwd()
    if not project:
        return None
    project_place = git_place(project)
    if project_place is None:
        return None
    try:
        parts = list(segments(command))
    except ValueError:
        return None  # unbalanced quotes: let it through, the shell will refuse it anyway
    for words in parts:
        if words and words[0] in ('cd', 'pushd') and len(words) > 1:
            cwd = resolve(cwd, words[1])
            continue
        reason = check(words, cwd, project_place[1])
        if reason:
            return reason
    return None


reason = branch_guard()
if reason:
    print(
        f'BLOCKED: {reason}. The main checkout stays on main and each worktree on its own branch, '
        'so parallel sessions never take each other\'s files. Work in a worktree under '
        '.claude/worktrees/ (`rtk make wt-new ID=<id>`, then enter it). File restores stay allowed: '
        '`git checkout -- <path>`, `git restore <path>`. If the main checkout is already off main, '
        'the owner types `! git switch main` (owner commands skip this hook).',
        file=sys.stderr,
    )
    sys.exit(2)

sys.exit(0)
PY
