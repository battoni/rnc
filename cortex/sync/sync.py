#!/usr/bin/env python3
"""
Cortex sync — propagate pendulum's tooling and code to a downstream project.

Pendulum is the source of truth. Downstream projects (impressao, etc.) keep the
same rule FILENAMES (celer-07-view-patterns.mdc, arcus-api-architecture.mdc) but
their CONTENT refers to the project's own folder names (app.impressao / api.impressao).

This tool copies shared files and rewrites the project WORDS in their content,
while protecting the rule filename TOKENS (celer-NN, celer-folder-, arcus-).

Classification comes from cortex/sync/manifest.tsv, which assigns each path a MODE:
    OVERWRITE  write straight to the project worktree (the AI-tooling layer)
    MERGE      land on the `pendulum-upstream` vendor branch + git-merge (B2a; not yet built)
    LOCAL      project owns it; never touched
    SKIP       never synced
    RETIRED    a path pendulum renamed or removed; reported downstream, deleted with --prune-retired
First match wins; there is no implicit default (the manifest's final `*` catch-all
guarantees every tracked file resolves to a mode).

Per-target name mapping comes from the target's .cortex-sync.conf.
The applied source version is stamped into the target's .cortex-version.

Usage:
    python3 cortex/sync/sync.py --to ../impressao [--dry-run] [--yes]
    python3 cortex/sync/sync.py --audit          # classify every tracked file; verify coverage

    --audit     classify all git-tracked files, report mode coverage, exit 1 on any gap. No sync.
    --dry-run   show what would change; write nothing (default behaviour is to PROMPT)
    --yes       apply without the interactive confirmation
    --force     skip the source-green CI gate (B2b) — e.g. an unpushed local commit you trust

Before a real sync, the source-green gate (B2b) checks pendulum's own CI for the SHA
being synced and refuses to propagate from a red/pending/unverified source unless --force.

A run applies OVERWRITE files straight to the target worktree and regenerates the
`pendulum-upstream` vendor branch for the MERGE set, then stops for the human to merge.
"""
from __future__ import annotations

import argparse
import fnmatch
import json
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

UPSTREAM_BRANCH = "pendulum-upstream"

# Pendulum's OPTIONAL sibling sub-projects (see the monorepo CLAUDE.md table). celer/arcus are
# CORE — they become the project's app/api and always sync. These extras are only propagated to a
# target that bootstrapped them; a project that deleted one ("clone → delete folders I won't use")
# must not have it forced back on every sync. Their target-side path equals their pendulum name
# (no celer/arcus token), so existence is checked by the literal directory.
OPTIONAL_SUBPROJECTS = ("codelumen", "liquen", "vitrum")

# Filename-token guards: substrings that are rule FILENAMES, not project words.
# Protected before the word-rewrite, restored after, so 'celer-07' never mutates
# while 'celer/src' (a path) does.
# Substrings hidden before the word rewrite and restored after it.
TOKEN_GUARDS = ("celer-", "arcus-")

# The unit's own `origin: pendulum` is hidden too, but ONLY in the frontmatter that opens the
# file. The frontmatter says which project AUTHORED a unit, and the word it holds is the same
# word the rewrite replaces. Unguarded, every synced unit arrived downstream claiming the
# project as its own origin — which is how all four projects ended up with
# `origin: <themselves>` on units they had inherited, and how the one field that distinguishes
# "we wrote this" from "we received this" stopped carrying any signal at all.
#
# Retired: `origin: pendulum` as a TOKEN_GUARD, hidden wherever it appeared. Everywhere else it
# is prose or a template, and there it has to follow the project: the review commands say
# "(pendulum-origin units) … frontmatter has `origin: pendulum`", which arrived downstream as
# "(lifeshifter-origin units) … `origin: pendulum`" — the rule contradicting itself — and the
# generate-rules templates stamped `origin: pendulum` on units the child itself was writing.
FRONTMATTER_ORIGIN_RE = re.compile(r"\A(---\n(?:(?!---\n).*\n)*?)origin: pendulum$", re.M)
FRONTMATTER_ORIGIN_MARK = "\x00origin\x00"

# Hosts that name BOTH a folder and the project (`celer.pendulum.test`). Rewritten as one
# unit, before the words: word by word, `celer` became `app.X` and `pendulum` became `X`, and
# the dev host arrived as `app.X.X.test` in every `.env.example`, CORS default and config.
DOTTED_HOSTS = (("celer.pendulum", "APP"), ("arcus.pendulum", "API"))

# A project word is rewritten only when it stands as a word. `str.replace` also rewrote it
# INSIDE other words — `accelerometer` arrived as `acapp.Xometer` in every Permissions-Policy
# header. An uppercase letter or a `_`/digit after the word still counts (`CelerPreset`,
# `celer_`): those are identifiers IDENT_COLLISION_RE exists to report, not English.
PROJECT_WORDS = (
    ("celer", "APP"),
    ("arcus", "API"),
    ("Pendulum", "PROJECT"),
    ("pendulum", "PROJECT"),
    ("Celer", "APP"),
)


def run(cmd: list[str], cwd: Path) -> str:
    return subprocess.run(
        cmd, cwd=cwd, capture_output=True, text=True
    ).stdout.strip()


def load_conf(target: Path) -> dict[str, str]:
    conf_path = target / ".cortex-sync.conf"
    if not conf_path.exists():
        sys.exit(
            f"error: {conf_path} not found.\n"
            "Create it with: PROJECT=<name>  APP=<app-dir>  API=<api-dir>\n"
            "Example for impressao:\n"
            "  PROJECT=impressao\n  APP=app.impressao\n  API=api.impressao"
        )
    conf: dict[str, str] = {}
    for line in conf_path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        conf[key.strip()] = value.strip()

    for required in ("PROJECT", "APP", "API"):
        if required not in conf:
            sys.exit(f"error: {conf_path} missing required key {required}")
    return conf


def load_manifest(pendulum: Path) -> list[tuple[str, str]]:
    manifest_path = pendulum / "cortex" / "sync" / "manifest.tsv"
    rules: list[tuple[str, str]] = []
    for line in manifest_path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "\t" not in line:
            continue
        cls, _, pattern = line.partition("\t")
        rules.append((cls.strip(), pattern.strip()))
    return rules


def classify(rel: str, rules: list[tuple[str, str]]) -> str:
    """Return the MODE for a path. First match wins. No implicit default — an
    unmatched path returns UNCLASSIFIED (a manifest gap the audit flags)."""
    for mode, pattern in rules:
        if pattern.endswith("/") and rel.startswith(pattern):
            return mode
        if "*" in pattern and fnmatch.fnmatch(rel, pattern):
            return mode
        if rel == pattern:
            return mode
    return "UNCLASSIFIED"


def tracked_files(root: Path) -> list[str]:
    """Every git-tracked path in the repo, relative to root. The only file source
    the engine considers — build artifacts are gitignored and never seen."""
    return [line for line in run(["git", "ls-files"], root).splitlines() if line]


def files_by_mode(root: Path, rules: list[tuple[str, str]]) -> dict[str, list[str]]:
    """Classify every tracked file into its MODE bucket (OVERWRITE / MERGE / LOCAL /
    SKIP / UNCLASSIFIED). The shared enumeration primitive the audit and the
    vendor-branch generator both build on."""
    buckets: dict[str, list[str]] = {}
    for rel in tracked_files(root):
        buckets.setdefault(classify(rel, rules), []).append(rel)
    return buckets


# A project folder token (celer/arcus) glued to the START of a code identifier.
# specialize() rewrites celer->app.X / arcus->api.X (DOTTED), so such a token inside an
# identifier becomes invalid syntax downstream — e.g. `CelerPreset` -> `app.zionPreset`.
# Excludes the hyphen filename tokens (celer-01, arcus-api) via the `-` in the lookbehind
# and the identifier-char lookahead (which a hyphen fails). Caught by e2e, but far too late.
#
# The token can also sit in the MIDDLE or at the END of an identifier: specialize() rewrites it
# after a `_` or a digit as well (only a letter before it protects it), so pendulum's
# `test_runs_eslint_on_the_edited_celer_file_only` arrived in boanerges as
# `..._edited_app.boanerges_file_only`, a SyntaxError that kept its hook suite from ever running.
# The second alternative catches it: `_`/digit before, and no lowercase letter after (the
# condition under which specialize() rewrites it).
IDENT_COLLISION_RE = re.compile(
    r"(?<![\w-])([Cc]eler|[Aa]rcus)(?=[A-Za-z0-9_])"
    r"|(?<=[0-9_])([Cc]eler|[Aa]rcus)(?![a-z])"
)
CODE_SUFFIXES = (".ts", ".tsx", ".js", ".mjs", ".cjs", ".vue", ".php", ".py")

# The same collision one level up: a project token used as a YAML MAPPING KEY. The identifier
# rule above cannot see it — a key is followed by `:`, not by an identifier character — so a
# whole `celer:` escaped the guard while `celerConfig` was caught.
#
# It is the same failure and a worse one, because YAML keys feed grammars stricter than YAML.
# A GitHub Actions job id must match ^[A-Za-z_][A-Za-z0-9_-]*$; a dot is illegal, and
# specialize() makes every such key dotted. GitHub then refuses the ENTIRE workflow rather than
# failing a job: the run reports "failure" with zero jobs and no logs, and nothing anywhere says
# the suite never ran. new.impressao shipped 20 such runs before anyone noticed the app's
# type-check, lint, unit and e2e had never gated a merge.
#
# Deliberately broad — ANY YAML key that is exactly the token, at any depth, not just under
# `jobs:`. A dotted key is legal in plenty of YAML, so this can in principle flag something
# harmless; the audit is the right place to argue about that, and today it flags nothing else
# in this repo. Silent CI death is not a trade worth a narrower rule.
YAML_KEY_COLLISION_RE = re.compile(r"^\s*['\"]?([Cc]eler|[Aa]rcus)['\"]?\s*:")
YAML_SUFFIXES = (".yml", ".yaml")


# Pendulum is its own origin, so a unit here has nothing upstream to be "based on" but itself:
# `based-on` must equal `version` (SCHEMA.md). A gap is not cosmetic — the triage compares a
# project's `based-on` against pendulum's `version` to decide what changed, so a drifted unit
# reports as diverged on a sync where nothing moved, and the signal that tells a project what to
# review becomes noise it learns to skip.
#
# This was already fixed once. In 2026-06 thirteen units were realigned and an assertion was
# added — to `/reviewVueConventions`, a review command a human has to remember to run. By
# 2026-09 six units had drifted again, three of them invisible to the triage (a LOCAL unit it
# never compares, and two carrying a `based-on` from the project they were back-ported FROM,
# higher than their own version). An invariant guarded only where someone might look is an
# invariant that comes back; this is the same check where `make sync-audit` already runs it.
BASED_ON_FIELD_RE = {
    field: re.compile(rf"^{field}:\s*(\S+)\s*$", re.M)
    for field in ("origin", "version", "based-on")
}


def based_on_drift_in_text(text: str) -> tuple[str, str] | None:
    """(version, based_on) when this RULES.md is a pendulum-origin unit whose two disagree,
    else None. Pure → unit-tested. A unit missing any of the three fields is not this check's
    business (the coverage audit above is what notices a malformed header)."""
    fields = {name: pattern.search(text) for name, pattern in BASED_ON_FIELD_RE.items()}
    if not all(fields.values()):
        return None
    if fields["origin"].group(1) != "pendulum":
        return None
    version, based_on = fields["version"].group(1), fields["based-on"].group(1)
    return None if version == based_on else (version, based_on)


def find_based_on_drift(pendulum: Path) -> list[tuple[str, str, str]]:
    """Units declaring `origin: pendulum` whose `based-on` != `version`.
    Returns [(rel, version, based_on)]."""
    hits: list[tuple[str, str, str]] = []
    # `tracked_files` and not rglob: git already excludes node_modules, vendor and build output,
    # and an untracked RULES.md is not a unit this repo ships.
    for rel in tracked_files(pendulum):
        if not rel.endswith("RULES.md"):
            continue
        try:
            text = (pendulum / rel).read_text(encoding="utf-8")
        except (UnicodeDecodeError, OSError):
            continue
        drift = based_on_drift_in_text(text)
        if drift:
            hits.append((rel[: -len("/RULES.md")], *drift))
    return hits


def find_token_collisions(
    pendulum: Path, rules: list[tuple[str, str]]
) -> list[tuple[str, int, str]]:
    """Shipped files (MERGE, and OVERWRITE outside the engine) where a project folder token sits somewhere the name transform would turn
    into invalid syntax — the start of a code identifier, or a YAML mapping key.
    Returns [(rel, line_no, line)]."""
    hits: list[tuple[str, int, str]] = []
    by_mode = files_by_mode(pendulum, rules)
    # OVERWRITE ships code too (cortex/'s Python, hooks, the tests beside them), and it is
    # specialized the same way. The engine's own files are exempt: they reach the child verbatim.
    shipped = by_mode.get("MERGE", []) + [
        rel for rel in by_mode.get("OVERWRITE", []) if not is_engine_internal(rel)
    ]
    for rel in shipped:
        if rel.endswith(CODE_SUFFIXES):
            patterns = (IDENT_COLLISION_RE,)
        elif rel.endswith(YAML_SUFFIXES):
            # Both: a YAML file can carry `celerFoo:` as well as a bare `celer:`.
            patterns = (YAML_KEY_COLLISION_RE, IDENT_COLLISION_RE)
        else:
            continue
        try:
            text = (pendulum / rel).read_text(encoding="utf-8")
        except (UnicodeDecodeError, OSError):
            continue
        for line_no, line in enumerate(text.splitlines(), 1):
            if any(pattern.search(line) for pattern in patterns):
                hits.append((rel, line_no, line.strip()[:100]))
    return hits


def audit(pendulum: Path, rules: list[tuple[str, str]]) -> int:
    """Classify every git-tracked file; report mode coverage; fail on any gap."""
    by_mode = files_by_mode(pendulum, rules)
    total = sum(len(v) for v in by_mode.values())

    print(f"Classified {total} tracked files against the manifest:\n")
    for mode in ("OVERWRITE", "MERGE", "LOCAL", "SKIP"):
        print(f"  {mode:11} {len(by_mode.get(mode, [])):>4}")

    # LOCAL + SKIP are small and worth eyeballing — print them in full.
    for mode in ("LOCAL", "SKIP"):
        for rel in by_mode.get(mode, []):
            print(f"    {mode:9} {rel}")

    unclassified = by_mode.get("UNCLASSIFIED", [])
    if unclassified:
        print(f"\n  UNCLASSIFIED {len(unclassified)}  — manifest gap, every file must classify:")
        for rel in unclassified:
            print(f"    {rel}")
        return 1

    retired_tracked = by_mode.get("RETIRED", [])
    if retired_tracked:
        print(f"\n  RETIRED BUT TRACKED {len(retired_tracked)}  — a RETIRED entry names a file pendulum still "
              "has; downstream it would be reported for deletion (remove the file or the entry):")
        for rel in retired_tracked:
            print(f"    {rel}")
        return 1

    collisions = find_token_collisions(pendulum, rules)
    if collisions:
        print(f"\n  TOKEN COLLISIONS {len(collisions)}  — project token sits inside a code identifier "
              "or is a YAML key; the name transform would emit invalid syntax downstream "
              "(rename in source):")
        for rel, line_no, line in collisions:
            print(f"    {rel}:{line_no}  {line}")
        return 1

    drift = find_based_on_drift(pendulum)
    if drift:
        print(f"\n  BASED-ON DRIFT {len(drift)}  — pendulum is its own origin, so `based-on` must "
              "equal `version`; the triage reads the gap as divergence and reports a fresh sync "
              "as changed (set based-on to version):")
        for rel, version, based_on in drift:
            print(f"    {rel}  version={version}  based-on={based_on}")
        return 1

    print("\nCoverage complete — every tracked file resolves to a mode; "
          "no project-token collisions; no based-on drift.")
    leaks = find_brand_leaks(pendulum, rules)
    if leaks:
        files = sorted({rel for rel, _no, _line in leaks})
        print(f"\n  ⚠️  brand-leak warning: {len(leaks)} reference(s) to pendulum's brand in "
              f"{len(files)} shipped file(s). Run --brand-audit for detail (these would reach a "
              "client verbatim; move to LOCAL or neutralize).")
    return 0


# ---- Brand-leak audit: keep pendulum's brand out of the shipped (MERGE) surface ----
#
# A client project must never receive pendulum's brand verbatim. The pendulum->project word-map
# rewrites celer/arcus/pendulum, but NOT brand literals (the `battoni.dev` domain, the author
# name) — so they ride along inside MERGE code/config and surface in the client's UI (impressão's
# login shipped "battoni.dev" until it was rebranded). This scans the shipped surface for those
# literals so they get neutralized or reclassified LOCAL. The named reference theme is exempt
# (battoni-dev is an opt-in example theme, not active brand).
BRAND_LEAK_RE = re.compile(r"battoni\.dev|Guilherme Battoni", re.IGNORECASE)
# Each entry names a file that mentions the brand ON PURPOSE:
#   - the battoni-dev reference theme, an opt-in example and not the active brand;
#   - attribution.css, which pins the colour of the battoni.dev attribution mark so a
#     client's theme cannot repaint someone else's logo. Naming that brand is its whole job, and it
#     is meant to reach every client.
BRAND_AUDIT_ALLOW = (
    "*/themes/battoni-dev.css",
    "*/styles/components/attribution.css",
)


def brand_audit_allows(rel: str) -> bool:
    """True when a shipped file is allowed to name pendulum's brand. Pure → unit-tested."""
    return any(fnmatch.fnmatch(rel, pat) for pat in BRAND_AUDIT_ALLOW)


def find_brand_leaks_in_text(text: str) -> list[tuple[int, str]]:
    """Lines carrying a pendulum brand literal. Pure → unit-tested. The `battoni-dev` theme
    token (hyphen) is NOT a match; only the `battoni.dev` domain and the author name are."""
    return [
        (no, line.strip()[:100])
        for no, line in enumerate(text.splitlines(), 1)
        if BRAND_LEAK_RE.search(line)
    ]


def find_brand_leaks(pendulum: Path, rules: list[tuple[str, str]]) -> list[tuple[str, int, str]]:
    """Brand literals in the SHIPPED surface (MERGE files — the app/api code+config a client
    receives), excluding the allowlisted reference theme. Returns [(rel, line_no, line)]."""
    hits: list[tuple[str, int, str]] = []
    for rel in files_by_mode(pendulum, rules).get("MERGE", []):
        if rel.split("/", 1)[0] in OPTIONAL_SUBPROJECTS:
            continue  # opt-in sub-projects (codelumen handbook, etc.) — not the shipped app/api
        if brand_audit_allows(rel):
            continue
        try:
            text = (pendulum / rel).read_text(encoding="utf-8")
        except (UnicodeDecodeError, OSError):
            continue
        for line_no, line in find_brand_leaks_in_text(text):
            hits.append((rel, line_no, line))
    return hits


def brand_audit(pendulum: Path, rules: list[tuple[str, str]]) -> int:
    """Report every brand literal in the shipped surface; exit nonzero if any (a gate the
    maintainer runs to keep the bootstrap white-label clean). Not part of --audit's hard pass."""
    leaks = find_brand_leaks(pendulum, rules)
    if not leaks:
        print("Brand audit clean — no pendulum brand literals in the shipped (MERGE) surface.")
        return 0
    files = sorted({rel for rel, _no, _line in leaks})
    print(f"Brand audit — {len(leaks)} brand literal(s) in {len(files)} shipped file(s):")
    print("(these reach a client verbatim — neutralize the string or reclassify the file LOCAL)\n")
    for rel in files:
        print(f"  {rel}")
        for _r, line_no, line in [hit for hit in leaks if hit[0] == rel][:4]:
            print(f"      {line_no}: {line}")
    return 1


def specialize(text: str, conf: dict[str, str]) -> str:
    """Rewrite project WORDS, preserving celer-/arcus- filename tokens."""
    # 1. hide filename tokens and the unit's own origin
    text = FRONTMATTER_ORIGIN_RE.sub(lambda match: match.group(1) + FRONTMATTER_ORIGIN_MARK, text, count=1)
    for i, guard in enumerate(TOKEN_GUARDS):
        text = text.replace(guard, f"\x00{i}\x00")
    # 2. rewrite hosts, then words (most-specific first)
    for src, key in DOTTED_HOSTS:
        text = re.sub(rf"(?<![A-Za-z]){re.escape(src)}(?![a-z])", lambda _match, key=key: conf[key], text)
        # The same host written inside a regex (`celer\.pendulum\.test` in a CORS pattern) keeps its
        # dots escaped: `app\.X\.test`, not `app.X\.X\.test`.
        escaped = src.replace(".", "\\.")
        text = re.sub(
            rf"(?<![A-Za-z]){re.escape(escaped)}(?![a-z])",
            lambda _match, key=key: conf[key].replace(".", "\\."),
            text,
        )
    text = text.replace(" (Celer)", "").replace("(Celer)", "")
    for src, key in PROJECT_WORDS:
        text = re.sub(rf"(?<![A-Za-z]){src}(?![a-z])", lambda _match, key=key: conf[key], text)
    # 3. restore filename tokens and the origin
    for i, guard in enumerate(TOKEN_GUARDS):
        text = text.replace(f"\x00{i}\x00", guard)
    return text.replace(FRONTMATTER_ORIGIN_MARK, "origin: pendulum")


# pendulum's own landing site is `public.battoni.dev/`, a name with no project word in it, so
# specialize() left the manifest's `SKIP public.battoni.dev/` verbatim downstream and the child's
# `public.<project>/` fell into `MERGE *` (boanerges had to add the line by hand, and every sync
# overwrote it). Only the manifest rule is rewritten: elsewhere the literal names pendulum's
# template (the landing-page skill), which stays what it is.
MANIFEST_REL = "cortex/sync/manifest.tsv"
PUBLIC_SITE_SKIP_RE = re.compile(r"^SKIP\tpublic\.battoni\.dev/$", re.MULTILINE)


def specialize_file(rel: str, text: str, conf: dict[str, str]) -> str:
    """specialize() a file's content, plus the rewrites that belong to one file only."""
    text = specialize(text, conf)
    if rel == MANIFEST_REL:
        text = PUBLIC_SITE_SKIP_RE.sub(lambda _match: f"SKIP\tpublic.{conf['PROJECT']}/", text)
    return text


def build_merge_plan(
    pendulum: Path,
    merge_files: list[str],
    conf: dict[str, str],
) -> list[tuple[str, bytes, bool]]:
    """Compute what the `pendulum-upstream` branch should contain for the MERGE set.

    For each MERGE file: name-map its PATH (celer/ -> app.X/, arcus/ -> api.X/) and
    transform its CONTENT (text only; binaries copy verbatim). Returns a list of
    (target_rel_path, new_bytes, is_binary). Pure — reads pendulum and computes the
    desired branch content; performs no writes and no git. The vendor-branch
    generator (B2a.4) executes this plan; --dry-run reports it.
    """
    plan: list[tuple[str, bytes, bool]] = []
    for rel in merge_files:
        raw = (pendulum / rel).read_bytes()
        try:
            text = raw.decode("utf-8")
            is_binary = False
        except UnicodeDecodeError:
            is_binary = True
        new_bytes = raw if is_binary else specialize(text, conf).encode("utf-8")
        target_rel = specialize(rel, conf)  # name-map the path itself
        plan.append((target_rel, new_bytes, is_binary))
    return plan


# ---- Loss report: surface downstream content a sync could silently drop ----
#
# The deepest failure mode of a sync is a MERGE file the project EXTENDED (a route
# table, nav, locales, seeders, config) being reverted to pendulum's version. The
# unit-metadata triage can't see file-level extensions, and a T0 "take theirs" or a
# same-region conflict discards them silently — caught (if at all) only by failing
# tests, after the damage. This computes, per MERGE file, the meaningful lines the
# PROJECT has that pendulum's incoming version does NOT — i.e. content at risk — so the
# operator reviews it BEFORE merging, not via a red suite afterward.

# Lines too trivial to flag as "lost work" (pure punctuation/markup scaffolding, short
# fragments). A real extension — a route, an import, an i18n key, a permission — is longer
# and carries an identifier, so this keeps the report signal-dense.
_TRIVIAL_LINE = re.compile(r"^[\s{}()\[\];,.<>/*\-+=:'\"`|&]*$")
# Unit metadata differs between any two copies BY CONSTRUCTION — a project's `version`, `origin`
# and `based-on` are never pendulum's — so it is never loss. In a real report 11 of 21 sections were
# nothing but these lines, burying the five lines of a layout the project had actually extended.
_METADATA_LINE = re.compile(r"^(?:version|origin|based-on):\s*\S|^>\s*For AI agents\.\s*Last updated:")


def project_only_lines(project_text: str, pendulum_text: str) -> list[str]:
    """Meaningful lines present in the project's file but absent from pendulum's incoming
    version (order-preserving, de-duplicated). Pure → unit-tested. Compares on stripped
    content so reindentation alone is not flagged; trivial scaffolding lines are dropped."""
    incoming = {ln.strip() for ln in pendulum_text.splitlines()}
    seen: set[str] = set()
    out: list[str] = []
    for raw in project_text.splitlines():
        stripped = raw.strip()
        if not stripped or stripped in incoming or stripped in seen:
            continue
        if _TRIVIAL_LINE.match(stripped) or len(stripped) < 4:
            continue
        if _METADATA_LINE.match(stripped):
            continue
        seen.add(stripped)
        out.append(stripped)
    return out


def loss_report(
    target: Path, plan: list[tuple[str, bytes, bool]]
) -> list[tuple[str, list[str]]]:
    """For each text MERGE file that already exists downstream, the project-only lines the
    incoming pendulum version lacks. Returns [(target_rel, [lines]), …] for at-risk files,
    sorted most-at-risk first. Pure read of the target worktree; no git, no writes."""
    rows: list[tuple[str, list[str]]] = []
    for target_rel, new_bytes, is_binary in plan:
        if is_binary:
            continue
        existing = target / target_rel
        if not existing.is_file():
            continue  # pendulum-new file: an add, nothing to lose
        try:
            project_text = existing.read_text(encoding="utf-8")
            pendulum_text = new_bytes.decode("utf-8")
        except UnicodeDecodeError:
            continue
        lost = project_only_lines(project_text, pendulum_text)
        if lost:
            rows.append((target_rel, lost))
    rows.sort(key=lambda row: len(row[1]), reverse=True)
    return rows


def render_loss_report(rows: list[tuple[str, list[str]]], report_path: Path | None) -> None:
    """Print a signal-dense summary of at-risk files and (when `report_path` is given —
    i.e. a real run, not a dry-run) write the full detail to that SKIP file in the target
    for use during the merge."""
    total = sum(len(lines) for _rel, lines in rows)
    print(
        f"\n⚠️  LOSS REPORT — {len(rows)} MERGE file(s) hold {total} line(s) of local content "
        f"not in pendulum's incoming version."
    )
    print("    These are SAFE if the 3-way merge keeps them, but a conflict resolved to")
    print("    upstream (e.g. --resolve-t0) would DROP them. Review before/while merging.")
    for target_rel, lines in rows[:12]:
        print(f"  · {target_rel}  ({len(lines)} line(s))")
        for line in lines[:3]:
            shown = line if len(line) <= 96 else line[:93] + "…"
            print(f"        {shown}")
        if len(lines) > 3:
            print(f"        … and {len(lines) - 3} more")
    if len(rows) > 12:
        print(f"  · … and {len(rows) - 12} more file(s)")

    detail = [
        "# Cortex sync — loss report",
        "# Lines the project has that pendulum's incoming version lacks (at risk in a merge).",
        "# Generated before the vendor branch was regenerated; review while resolving conflicts.",
        "",
    ]
    for target_rel, lines in rows:
        detail.append(f"## {target_rel}  ({len(lines)} line(s))")
        detail.extend(f"  {line}" for line in lines)
        detail.append("")
    if report_path is not None:
        report_path.parent.mkdir(parents=True, exist_ok=True)
        report_path.write_text("\n".join(detail), encoding="utf-8")
        print(f"  → full detail: {report_path}")


# ---- Retired paths: what pendulum renamed or removed must not live on downstream ----
#
# The sync writes what pendulum HAS; it never deletes what pendulum stopped having. When the
# commands were renamed to `battoni-*`, every project kept the old generation beside the new one —
# 28 command files for 16 commands in one of them — and the manifest still protected the OLD
# `start-session.md` as LOCAL, so the command a person actually invokes was overwritten with
# pendulum's roster while the project's adaptation sat in a file nobody runs. A RETIRED entry names
# such a path. It is reported on every sync and deleted only with --prune-retired: an old file can
# hold the project's own edits, and moving them is a person's decision.


def retired_patterns(rules: list[tuple[str, str]]) -> list[str]:
    return [pattern for mode, pattern in rules if mode == "RETIRED"]


def find_retired_present(target: Path, rules: list[tuple[str, str]]) -> list[str]:
    """Files in the target that match a RETIRED entry, relative to the target, sorted."""
    found: set[str] = set()
    for pattern in retired_patterns(rules):
        if pattern.endswith("/"):
            base = target / pattern
            if base.is_dir():
                found.update(str(path.relative_to(target)) for path in base.rglob("*") if path.is_file())
        elif "*" in pattern:
            found.update(str(path.relative_to(target)) for path in target.glob(pattern) if path.is_file())
        elif (target / pattern).is_file():
            found.add(pattern)
    return sorted(found)


# ---- CLAUDE.md drift: the one always-loaded context is LOCAL, so nothing refreshes it ----
#
# A project's CLAUDE.md is written once at bootstrap and never touched again by the sync. In the
# first project audited it named a command that no longer existed and omitted two sub-projects
# that did — and it is the file every session reads before anything else.

_CLAUDE_COMMAND_REF = re.compile(r"`/([a-z][a-z0-9-]*)`")
_CLAUDE_SUBPROJECT_ROW = re.compile(r"^\|\s*`([A-Za-z0-9._-]+)/`\s*\|", re.M)


def claude_md_drift(claude_text: str, commands: set[str], subprojects: set[str], folders: set[str]) -> list[str]:
    """What CLAUDE.md says that the repository no longer backs up. Pure → unit-tested.

    `subprojects` are the folders that must be listed (they build on their own); `folders` is every
    top-level folder, so a listed knowledge folder like `cortex/` is not mistaken for a stale row."""
    warnings: list[str] = []
    for name in sorted(set(_CLAUDE_COMMAND_REF.findall(claude_text))):
        if name not in commands:
            warnings.append(f"names `/{name}`, which is not a command in .claude/commands/")
    listed = set(_CLAUDE_SUBPROJECT_ROW.findall(claude_text))
    for name in sorted(listed - folders):
        warnings.append(f"lists sub-project `{name}/`, which does not exist")
    for name in sorted(subprojects - listed):
        warnings.append(f"does not list sub-project `{name}/`, which exists")
    return warnings


def target_subprojects(target: Path) -> set[str]:
    """Top-level folders that are a project of their own (a package.json or composer.json)."""
    return {
        path.name for path in target.iterdir()
        if path.is_dir() and not path.name.startswith(".")
        and ((path / "package.json").is_file() or (path / "composer.json").is_file())
    }


def report_claude_md_drift(pendulum: Path, target: Path, rules: list[tuple[str, str]]) -> None:
    claude = target / "CLAUDE.md"
    if not claude.is_file():
        return
    retired = {Path(rel).stem for rel in find_retired_present(target, rules)}
    commands = {path.stem for root in (target, pendulum) for path in (root / ".claude" / "commands").glob("*.md")}
    folders = {path.name for path in target.iterdir() if path.is_dir() and not path.name.startswith(".")}
    warnings = claude_md_drift(claude.read_text(encoding="utf-8"), commands - retired, target_subprojects(target), folders)
    if not warnings:
        return
    print(f"\n⚠️  CLAUDE.md is LOCAL and nothing refreshes it — {len(warnings)} statement(s) no longer true:")
    for warning in warnings:
        print(f"  · CLAUDE.md {warning}")


def backup_tag(target: Path, src_sha: str) -> str | None:
    """Stamp a movable rollback tag on the project's current HEAD before the sync mutates
    anything, so `git reset --hard <tag>` always undoes a bad merge. Best-effort."""
    name = f"cortex-presync-{src_sha[:8]}"
    if git(target, "tag", "-f", name, check=False).returncode == 0:
        return name
    return None


# Core/local extension-point stubs. These are LOCAL (the project owns them and the sync never
# OVERWRITES them) BUT the synced CORE statically imports them (routes.ts -> ./routes.local,
# i18n -> locales/local/*, TheNavbar -> ./navItems.local), so they MUST exist or the project's
# build breaks the moment it adopts the split. The narrow exception: create them in the target
# IF ABSENT (never overwrite), so an existing project can adopt the split without a missing-import
# break. (arcus guards its includes with file_exists(), so it doesn't strictly need this — but the
# stubs are scaffolded for parity + discoverability.)
LOCAL_STUB_PATTERNS = (
    "*.local.ts",
    "*.local.vue",
    "*.local.php",
    "*.local.json",
    "Makefile.local",
    "*/locales/local/*",
)


def is_local_stub(rel: str) -> bool:
    return any(fnmatch.fnmatch(rel, pat) for pat in LOCAL_STUB_PATTERNS)


def scaffold_local_stubs(
    pendulum: Path, target: Path, local_files: list[str], conf: dict[str, str], dry_run: bool
) -> list[str]:
    """Create the extension-point LOCAL stubs in the target IF ABSENT (never overwrite), so the
    synced core's static imports resolve. Returns the created target-relative paths."""
    created: list[str] = []
    for rel in local_files:
        if not is_local_stub(rel):
            continue
        target_rel = specialize(rel, conf)
        dst = target / target_rel
        if dst.exists():
            continue
        created.append(target_rel)
        if dry_run:
            continue
        dst.parent.mkdir(parents=True, exist_ok=True)
        raw = (pendulum / rel).read_bytes()
        try:
            dst.write_bytes(specialize(raw.decode("utf-8"), conf).encode("utf-8"))
        except UnicodeDecodeError:
            dst.write_bytes(raw)
    return created


def git(repo: Path, *args: str, stdin: str | None = None,
        check: bool = True) -> subprocess.CompletedProcess[str]:
    """Run a git command in `repo`. Exits with the stderr on failure when check=True."""
    proc = subprocess.run(
        ["git", "-C", str(repo), *args],
        capture_output=True, text=True, input=stdin,
    )
    if check and proc.returncode != 0:
        sys.exit(f"error: git {' '.join(args)} failed in {repo}:\n{proc.stderr.strip()}")
    return proc


def branch_exists(repo: Path, branch: str) -> bool:
    return git(repo, "show-ref", "--verify", "--quiet",
               f"refs/heads/{branch}", check=False).returncode == 0


def generate_upstream(target: Path, plan: list[tuple[str, bytes, bool]],
                      src_sha: str, dry_run: bool) -> int:
    """Regenerate the `pendulum-upstream` vendor branch in `target` to hold exactly
    `plan` (the transformed + name-mapped MERGE set), via a throwaway `git worktree`
    so the user's checkout is never disturbed. Commits and STOPS — the human merges.

    First run seeds an orphan branch (plumbing: empty tree -> root commit, no main-tree
    checkout, git-2.39 compatible). Later runs check it out, clear it, rewrite the plan,
    and commit — so adds, edits, and deletions all flow. Does NOT advance .cortex-version
    (that happens post-merge, per DESIGN lifecycle step 5).
    """
    exists = branch_exists(target, UPSTREAM_BRANCH)
    count = len(plan)

    if dry_run:
        action = "update" if exists else "create (orphan)"
        print(f"\n[dry-run] MERGE: would {action} '{UPSTREAM_BRANCH}' in {target.name} "
              f"with {count} file(s):")
        for target_rel, _bytes, is_bin in plan[:8]:
            print(f"    {'BIN ' if is_bin else '    '}{target_rel}")
        if count > 8:
            print(f"    … and {count - 8} more")
        return 0

    # Seed an orphan branch on first run, without checking out in the main worktree.
    if not exists:
        empty_tree = git(target, "mktree", stdin="").stdout.strip()
        root = git(target, "commit-tree", empty_tree, "-m",
                   "chore: seed pendulum-upstream vendor branch").stdout.strip()
        git(target, "branch", UPSTREAM_BRANCH, root)

    parent = Path(tempfile.mkdtemp(prefix="pendulum-sync-"))
    worktree = parent / "upstream"  # git worktree add creates this
    try:
        git(target, "worktree", "add", "--quiet", str(worktree), UPSTREAM_BRANCH)

        # Clear tracked content (so deletions propagate), keep .git.
        for child in worktree.iterdir():
            if child.name == ".git":
                continue
            shutil.rmtree(child) if child.is_dir() else child.unlink()

        for target_rel, new_bytes, _is_bin in plan:
            dst = worktree / target_rel
            dst.parent.mkdir(parents=True, exist_ok=True)
            dst.write_bytes(new_bytes)

        git(worktree, "add", "-A")
        if git(worktree, "diff", "--cached", "--quiet", check=False).returncode == 0:
            print(f"  MERGE: '{UPSTREAM_BRANCH}' already at pendulum@{src_sha[:12]} — no new commit.")
        else:
            git(worktree, "commit", "--quiet", "-m",
                f"sync: pendulum@{src_sha[:12]} -> {UPSTREAM_BRANCH} ({count} files)")
            print(f"  MERGE: committed {count} file(s) to '{UPSTREAM_BRANCH}' (pendulum@{src_sha[:12]}).")
    finally:
        git(target, "worktree", "remove", "--force", str(worktree), check=False)
        shutil.rmtree(parent, ignore_errors=True)
    return 0


# ---- Source-green gate (Phase B2b): don't propagate from a red/unverified source ----

# GitHub check-run conclusions that mean "this check did not pass".
CHECK_FAIL_CONCLUSIONS = {
    "failure", "timed_out", "cancelled", "action_required", "startup_failure", "stale",
}


def evaluate_source_checks(
    runs: list[dict], statuses: list[dict]
) -> tuple[str, str]:
    """Pure rollup of a commit's CI signal — no network, so it is unit-tested directly.

    `runs`     = GitHub check-run objects   (each {name, status, conclusion}).
    `statuses` = legacy commit-status objects (each {context, state}).
    Returns (state, detail) with state in GREEN / RED / PENDING / NO_CHECKS.
    A failure outranks a pending; a pending outranks success; no signal at all is NO_CHECKS.
    """
    if not runs and not statuses:
        return "NO_CHECKS", "no CI checks reported for this commit"

    failing = [run.get("name", "?") for run in runs
               if run.get("conclusion") in CHECK_FAIL_CONCLUSIONS]
    failing += [status.get("context", "?") for status in statuses
                if status.get("state") in ("failure", "error")]
    if failing:
        return "RED", "failing: " + ", ".join(sorted(set(failing))[:6])

    pending = [run.get("name", "?") for run in runs if run.get("status") != "completed"]
    pending += [status.get("context", "?") for status in statuses
                if status.get("state") == "pending"]
    if pending:
        return "PENDING", "still running: " + ", ".join(sorted(set(pending))[:6])

    return "GREEN", f"{len(runs) + len(statuses)} check(s) passed"


def source_status(pendulum: Path, sha: str) -> tuple[str, str]:
    """Query the source repo's CI rollup for `sha` via `gh`, then `evaluate_source_checks`.

    Adds two states the pure evaluator can't see: UNPUSHED (the SHA is not on the remote —
    GitHub 422/404, the common 'committed but not pushed' case) and UNKNOWN (no gh / no
    remote — verification impossible). Network-bound; isolated so the decision logic stays pure.
    """
    if shutil.which("gh") is None:
        return "UNKNOWN", "gh CLI not installed — cannot verify source CI"

    nwo = run(["gh", "repo", "view", "--json", "nameWithOwner", "-q", ".nameWithOwner"], pendulum)
    if not nwo:
        return "UNKNOWN", "no GitHub remote — cannot verify source CI"

    runs_proc = subprocess.run(
        ["gh", "api", f"repos/{nwo}/commits/{sha}/check-runs",
         "--paginate", "--jq", ".check_runs[] | {name, status, conclusion}"],
        cwd=pendulum, capture_output=True, text=True,
    )
    if runs_proc.returncode != 0:
        err = (runs_proc.stderr or "").strip()
        if "No commit found" in err or "(HTTP 422)" in err or "(HTTP 404)" in err:
            return "UNPUSHED", f"commit {sha[:12]} is not on {nwo} — push it before syncing"
        return "UNKNOWN", f"gh error: {err.splitlines()[0] if err else 'unknown'}"
    runs = [json.loads(line) for line in runs_proc.stdout.splitlines() if line.strip()]

    status_proc = subprocess.run(
        ["gh", "api", f"repos/{nwo}/commits/{sha}/status",
         "--jq", ".statuses[] | {context, state}"],
        cwd=pendulum, capture_output=True, text=True,
    )
    statuses: list[dict] = []
    if status_proc.returncode == 0:
        statuses = [json.loads(line) for line in status_proc.stdout.splitlines() if line.strip()]

    return evaluate_source_checks(runs, statuses)


def enforce_source_green(pendulum: Path, sha: str, force: bool, dry_run: bool) -> None:
    """B2b precondition: refuse to sync from a source whose CI isn't green.

    GREEN proceeds; every other state BLOCKS unless --force. --force short-circuits the
    network call entirely (keeps the engine proof hermetic). On --dry-run the gate is
    informational — it reports the state but never blocks (a dry run writes nothing).
    """
    if force:
        print("Source CI gate:     skipped (--force)")
        return

    state, detail = source_status(pendulum, sha)
    glyph = {"GREEN": "✓", "RED": "✗", "PENDING": "…",
             "NO_CHECKS": "?", "UNPUSHED": "✗", "UNKNOWN": "?"}.get(state, "?")
    print(f"Source CI gate:     {glyph} {state} — {detail}")

    if state == "GREEN":
        return
    if dry_run:
        print("  [dry-run] gate is informational; a real run would BLOCK here without --force.")
        return

    sys.exit(
        f"error: source CI is not green ({state}). Refusing to sync.\n"
        "  Wait for / fix CI at the synced SHA, or pass --force to override\n"
        "  (e.g. an unpushed local commit you trust)."
    )


# ---- T0 conflict auto-resolution (Phase C follow-up): mechanical by-mode merge ----

DEP_SECTIONS = {
    "package.json": ("dependencies", "devDependencies"),
    "composer.json": ("require", "require-dev"),
}

# Junction / extension-point files: shared, synced files that downstream projects routinely
# EXTEND (route tables, nav, locales, seeders, permission registries, build aliases). A blind
# "take theirs" here is exactly what silently reverted impressão's features at T0 — so the
# resolver NEVER auto-resolves these; it leaves them conflicted for a human to merge keeping
# both sides (guided by the loss report). The long-term fix is the core/local split that moves
# project extensions into LOCAL files (so these stop conflicting at all); this is the guardrail
# for files that remain MERGE and for projects that predate the split. Matched by fnmatch on the
# project-side path ('*' spans '/').
#
# Retired: "*/data/permission-groups.json" (frontend registry). Nothing imported it — the groups
# arrive from the API — and it had drifted to two groups against seven; the file was deleted.
JUNCTION_PATTERNS = (
    "*/libraries/router/routes.ts",     # frontend route aggregation
    "*/routes/api.php",                 # API route table
    "*/components/unique/TheNavbar/*.vue",  # nav items
    "*/locales/*.json",                 # i18n messages
    "*/database/seeders/DatabaseSeeder.php",  # seeder registry
    "*PermissionGroupsHelper.php",      # permission group registry (API)
    "*/vite.config.ts",                 # module aliases
    "*/tsconfig.app.json",              # module path aliases
    "Makefile",                         # build targets
)


def is_junction(rel: str) -> bool:
    """True for shared extension-point files the T0 resolver must not auto-take-theirs on."""
    return any(fnmatch.fnmatch(rel, pat) for pat in JUNCTION_PATTERNS)


def union_json(ours_text: str, theirs_text: str, sections: tuple[str, ...]) -> str:
    """Union two JSON dependency manifests. Upstream (theirs) is the base — it carries the
    test toolchain — then any project-only keys from ours are added back (theirs wins on a
    shared key). Other top-level keys come from theirs. Raises on unparseable input."""
    ours, theirs = json.loads(ours_text), json.loads(theirs_text)
    merged = dict(theirs)
    for section in sections:
        combined = dict(theirs.get(section, {}))
        for key, value in ours.get(section, {}).items():
            combined.setdefault(key, value)
        if combined:
            merged[section] = dict(sorted(combined.items()))
    return json.dumps(merged, indent=2) + "\n"


def project_classify(rel: str, rules: list[tuple[str, str]], conf: dict[str, str]) -> str:
    """Classify a PROJECT-side path by reverse-mapping it to its pendulum path first
    (app.X -> celer, api.X -> arcus before project -> pendulum, so the dotted names unwind)."""
    pend_rel = rel.replace(conf["APP"], "celer").replace(conf["API"], "arcus").replace(conf["PROJECT"], "pendulum")
    return classify(pend_rel, rules)


def resolve_t0(pendulum: Path, target: Path, conf: dict[str, str],
               rules: list[tuple[str, str]], dry_run: bool) -> int:
    """Mechanically resolve the conflicts of a T0 baseline merge, by MODE:
        junction extension points     -> manual:            LEFT conflicted — never auto-revert
        package.json / composer.json  -> union:             upstream base + project-only deps
        MERGE                         -> upstream (theirs):  T0 adopts pendulum's code/tests/config
        OVERWRITE / LOCAL             -> project  (ours):    AI layer is already current; locks/brand stay

    Junction files (routes, nav, locales, seeders, permission registries, build aliases) are the
    ones a project EXTENDS, so blindly taking theirs drops local features — these are deliberately
    left unresolved for a human to merge keeping both sides (use the loss report). Stages the rest;
    never commits. **T0 only.** At Tn a MERGE conflict is an intentional-divergence judgement call,
    so this refuses unless a merge is actually in progress, and is documented for the baseline alone.
    """
    if not dry_run and not (target / ".git" / "MERGE_HEAD").exists():
        sys.exit(f"error: --resolve-t0 expects a merge in progress (run `git merge {UPSTREAM_BRANCH}` first).")

    conflicted = git(target, "diff", "--name-only", "--diff-filter=U").stdout.split()
    if not conflicted:
        print("No conflicts to resolve.")
        return 0

    plan: dict[str, list[str]] = {"manual": [], "union": [], "theirs": [], "ours": []}
    for rel in conflicted:
        base = rel.rsplit("/", 1)[-1]
        if is_junction(rel):
            plan["manual"].append(rel)
        elif base in DEP_SECTIONS:
            plan["union"].append(rel)
        elif project_classify(rel, rules, conf) == "MERGE":
            plan["theirs"].append(rel)
        else:
            plan["ours"].append(rel)

    print(f"T0 auto-resolve ({'dry-run' if dry_run else 'applied'}) — {len(conflicted)} conflict(s):")
    print(f"  manual (junction): {len(plan['manual'])}   union: {len(plan['union'])}   "
          f"upstream (theirs): {len(plan['theirs'])}   project (ours): {len(plan['ours'])}")
    if plan["manual"]:
        print("  ⚠️  junction files NOT auto-resolved — merge by hand, keeping your local extensions:")
        for rel in plan["manual"]:
            print(f"        {rel}")
    if dry_run:
        for side in ("union", "theirs", "ours"):
            for rel in plan[side][:5]:
                print(f"    {side:7} {rel}")
            if len(plan[side]) > 5:
                print(f"    {side:7} … and {len(plan[side]) - 5} more")
        print("\n[dry-run] nothing staged.")
        return 0

    for side in ("theirs", "ours"):
        for rel in plan[side]:
            if git(target, "checkout", f"--{side}", "--", rel, check=False).returncode == 0:
                git(target, "add", "--", rel)
                continue
            # one side absent (UD/DU): keep our file for 'ours', drop it for 'theirs'
            if side == "ours":
                git(target, "add", "--", rel, check=False)
            else:
                git(target, "rm", "-q", "--", rel, check=False)

    for rel in plan["union"]:
        base = rel.rsplit("/", 1)[-1]
        ours_text = git(target, "show", f":2:{rel}", check=False).stdout
        theirs_text = git(target, "show", f":3:{rel}", check=False).stdout
        try:
            (target / rel).write_text(union_json(ours_text, theirs_text, DEP_SECTIONS[base]), encoding="utf-8")
        except (json.JSONDecodeError, ValueError):
            git(target, "checkout", "--theirs", "--", rel, check=False)  # one side absent -> upstream
        git(target, "add", "--", rel)

    remaining = git(target, "diff", "--name-only", "--diff-filter=U").stdout.split()
    print(f"\nStaged. Unresolved remaining: {len(remaining)} (junction files held for manual merge).")
    if remaining:
        print("Resolve each by KEEPING BOTH SIDES (your routes/nav/locales + pendulum's), then `git add`.")
    print("Then: `make test-all` (green = safe), commit the merge and advance .cortex-version.")
    return 0 if not remaining else 1


# ---- Post-merge test gate: advance .cortex-version ONLY when the suite is green ----

VENDOR_SHA_RE = re.compile(r"pendulum@([0-9a-f]{7,40})")


def parse_vendor_sha(commit_message: str) -> str | None:
    """The pendulum SHA recorded in a vendor-branch commit message. Pure → unit-tested."""
    match = VENDOR_SHA_RE.search(commit_message)
    return match.group(1) if match else None


def synced_sha_from_vendor(target: Path) -> str | None:
    """Read the SHA this project last synced, from the `pendulum-upstream` tip commit — the
    source of truth for what was merged (robust to pendulum HEAD moving after the sync)."""
    if not branch_exists(target, UPSTREAM_BRANCH):
        return None
    message = git(target, "log", "-1", "--format=%B", UPSTREAM_BRANCH, check=False).stdout
    return parse_vendor_sha(message)


def advance(target: Path, dry_run: bool, force: bool) -> int:
    """Run the project's suite and advance .cortex-version ONLY on green. Tests are the real
    backstop against a silent revert (a reverted route/locale shows up as a red test) — this
    makes that gate part of the engine instead of a manual step a tired operator can skip."""
    sha = synced_sha_from_vendor(target)
    if not sha:
        sys.exit(f"error: no '{UPSTREAM_BRANCH}' branch in {target.name} — run a sync first.")

    if (target / ".git" / "MERGE_HEAD").exists():
        sys.exit(f"error: a merge is still in progress in {target.name} — finish it first.")
    unresolved = git(target, "diff", "--name-only", "--diff-filter=U", check=False).stdout.split()
    if unresolved:
        sys.exit(f"error: {len(unresolved)} unresolved conflict(s) in {target.name} — resolve before advancing.")
    merged = git(target, "merge-base", "--is-ancestor", UPSTREAM_BRANCH, "HEAD",
                 check=False).returncode == 0
    if not merged:
        sys.exit(f"error: '{UPSTREAM_BRANCH}' is not merged into {target.name}@HEAD yet — "
                 f"run `git merge {UPSTREAM_BRANCH}`, resolve, commit, then re-run --advance.")

    stamp = target / ".cortex-version"
    if dry_run:
        print(f"[dry-run] would run `make test-all` in {target.name}; on green write {sha} to .cortex-version.")
        return 0

    if force:
        print(f"--force: skipping the test gate; advancing .cortex-version to {sha[:12]}.")
    else:
        print(f"Test gate — running `make test-all` in {target.name} …\n")
        result = subprocess.run(["make", "test-all"], cwd=target)
        if result.returncode != 0:
            sys.exit(f"\n‼️  TEST GATE RED (make test-all exited {result.returncode}) — "
                     ".cortex-version NOT advanced.\n"
                     "   A silent revert from the merge usually surfaces here. Fix the failures "
                     "(check .cortex-loss-report.txt), then re-run --advance.")
        print("\n✅ test gate green.")

    stamp.write_text(sha + "\n", encoding="utf-8")
    print(f"Advanced {target.name}/.cortex-version → {sha}")
    print(f"Commit it:  git -C {target.name} add .cortex-version && "
          f"git commit -m \"cortex: sync to {sha[:12]}\"")
    return 0


# ---- First-run in-place transform (Phase B3): turn a fresh clone into the project ----

# The engine must NOT rewrite ITSELF. cortex/sync/ holds the executable sync engine and its
# docs, authored in the upstream celer/arcus/pendulum vocabulary that every sync depends on:
# specialize()-ing the .py files corrupts the engine (a self-modifying-code footgun — the
# rewrite table eats its own ("celer", app) entries), and rewriting the docs would mislabel
# the source repo (RUNBOOK: "run from pendulum -> your project"). manifest.tsv is the one
# exception — it maps project PATHS, so it must carry the renamed app.X/api.X folders for the
# downstream --audit to classify them.
ENGINE_DIR = "cortex/sync/"
ENGINE_TRANSFORM_ALLOW = {"cortex/sync/manifest.tsv"}


def is_engine_internal(rel: str) -> bool:
    """True for sync-engine files that init must leave pristine (everything under
    cortex/sync/ except manifest.tsv)."""
    return rel.startswith(ENGINE_DIR) and rel not in ENGINE_TRANSFORM_ALLOW


def init_in_place(root: Path, conf: dict[str, str], dry_run: bool) -> int:
    """One-time setup: transform a fresh pendulum clone INTO the project, in place.

    `specialize()`s every tracked text file's content AND every path, which is what a sync
    does — celer/ -> app.X, arcus/ -> api.X, docs.pendulum/ -> docs.X, config/pendulum.php ->
    config/X.php. No vendor branch — the clone *is* pendulum at fork time, there is nothing to
    sync from yet (that starts at T0, after this). Refuses to run on pendulum itself. The
    /battoni-setup-project command wraps this (conf creation, unused-module pruning).
    """
    if conf.get("PROJECT", "").lower() == "pendulum":
        sys.exit("error: --init refuses to transform pendulum itself (PROJECT=pendulum).")

    tracked = tracked_files(root)

    content_changes = 0
    engine_skipped = 0
    for rel in tracked:
        if is_engine_internal(rel):
            engine_skipped += 1
            continue  # never rewrite the engine into the project's vocabulary
        path = root / rel
        raw = path.read_bytes()
        try:
            text = raw.decode("utf-8")
        except UnicodeDecodeError:
            continue  # binary — left as-is
        new = specialize_file(rel, text, conf)
        if new != text:
            content_changes += 1
            if not dry_run:
                path.write_text(new, encoding="utf-8")

    # WHAT GETS RENAMED: every path a sync would name-map, not only the two folders.
    #
    # A sync runs `specialize()` on the PATH as well as on the content (`build_merge_plan`, and
    # the OVERWRITE writer), so init has to agree with it. It did not: it renamed `celer/` and
    # `arcus/` and left every other path alone. `arcus/config/pendulum.php` therefore kept its
    # name while its content became `config('zion.…')` — so every lookup answered null, with no
    # error anywhere to say why — and the next sync would have WRITTEN `config/zion.php` beside
    # it. Found on zion's first run, where the empty config took a Pest test down with it.
    #
    # A directory moves as a directory: `git mv` carries its untracked and ignored content along
    # (node_modules/, vendor/, .env), which a file-by-file walk would leave behind. Everything
    # else — `config/pendulum.php`, `battoni-explain-pendulum.md` and its `.mdc` mirror — moves
    # as a file, after the folders, so its new parent already exists.
    dir_renames = [
        (entry.name, specialize(entry.name, conf))
        for entry in sorted(root.iterdir(), key=lambda path: path.name)
        if entry.is_dir() and entry.name != ".git" and specialize(entry.name, conf) != entry.name
    ]

    def after_dirs(rel: str) -> str:
        """Where a path sits once the folder renames above have run."""
        head, _, tail = rel.partition("/")
        for src, dst in dir_renames:
            if head == src:
                return f"{dst}/{tail}" if tail else dst
        return rel

    file_renames: list[tuple[str, str]] = []
    for rel in tracked:
        if is_engine_internal(rel):
            continue  # the engine keeps the upstream vocabulary, in its names as in its text
        target = specialize(rel, conf)
        source = after_dirs(rel)
        if source != target:
            file_renames.append((source, target))

    collisions: dict[str, str] = {}
    for source, target in file_renames:
        if target in collisions:
            sys.exit(f"error: --init would map two paths onto {target} "
                     f"({collisions[target]} and {source}). Rename one upstream first.")
        collisions[target] = source

    print(f"Init transform ({'dry-run' if dry_run else 'applied'}) for project '{conf['PROJECT']}':")
    print(f"  content rewritten in {content_changes} file(s)")
    print(f"  engine left pristine: {engine_skipped} file(s) under {ENGINE_DIR} (manifest.tsv excepted)")
    for src, dst in dir_renames:
        print(f"  rename  {src}/ -> {dst}/")
    for source, target in file_renames:
        print(f"  rename  {source} -> {target}")
    if dry_run:
        print("\n[dry-run] no files written, no renames.")
        return 0

    for src, dst in dir_renames:
        git(root, "mv", src, dst)
    for source, target in file_renames:
        (root / target).parent.mkdir(parents=True, exist_ok=True)
        git(root, "mv", source, target)
    print(f"\nDone. Next: delete unused modules, then `git commit` the initial project state.")
    return 0


# ---- Triage (Phase B2c): what changed per unit, and how hard to look ----

def parse_semver(version: str) -> tuple[int, int, int]:
    """'1.4.2' -> (1, 4, 2). Missing or non-numeric parts become 0."""
    parts = (version or "").strip().split(".")
    nums: list[int] = []
    for index in range(3):
        try:
            nums.append(int(parts[index]))
        except (IndexError, ValueError):
            nums.append(0)
    return nums[0], nums[1], nums[2]


def read_frontmatter(rules_path: Path) -> dict[str, str]:
    """Parse the leading `--- ... ---` block of a RULES.md into a dict of simple
    `key: value` lines (version, based-on, origin). Empty dict if absent/malformed."""
    fm: dict[str, str] = {}
    if not rules_path.exists():
        return fm
    text = rules_path.read_text(encoding="utf-8")
    if not text.startswith("---"):
        return fm
    end = text.find("\n---", 3)
    if end == -1:
        return fm
    for line in text[3:end].splitlines():
        if ":" in line:
            key, _, value = line.partition(":")
            fm[key.strip()] = value.strip()
    return fm


def changelog_excerpt_since(changelog_text: str, based_on: str) -> str:
    """Return the CHANGELOG `## x.y.z` entries newer than `based_on`, as text.
    Entries are delimited by `## ` headers; the version is the first token after it."""
    based = parse_semver(based_on)
    out: list[str] = []
    keeping = False
    for line in changelog_text.splitlines():
        if line.startswith("## "):
            header = line[3:].split()
            keeping = bool(header) and parse_semver(header[0]) > based
        if keeping:
            out.append(line)
    return "\n".join(out).strip()


def triage_state(pendulum_version: str, based_on: str, project_version: str) -> str:
    """Classify a unit by comparing pendulum's current version against the project's
    `based-on` (last synced) and the project's own current `version`."""
    pendulum_changed = parse_semver(pendulum_version) > parse_semver(based_on)
    project_edited = parse_semver(project_version) > parse_semver(based_on)
    if not pendulum_changed and not project_edited:
        return "unchanged"
    if pendulum_changed and not project_edited:
        return "clean-update"   # git fast-forwards; safe
    if pendulum_changed and project_edited:
        return "diverged"       # both sides moved; review the merge
    return "project-ahead"      # project has local edits; pendulum has nothing new


def severity(based_on: str, pendulum_version: str) -> str:
    """How big is pendulum's delta over the project's based-on — how hard to look."""
    based = parse_semver(based_on)
    pend = parse_semver(pendulum_version)
    if pend <= based:
        return "none"
    if pend[0] != based[0]:
        return "major"
    if pend[1] != based[1]:
        return "minor"
    return "patch"


def find_units(root: Path, rules: list[tuple[str, str]], mode: str = "MERGE") -> list[str]:
    """Relative dirs (under root) holding both RULES.md and CHANGELOG.md whose RULES.md
    classifies as `mode`. Uses tracked files only (no node_modules etc.)."""
    units: list[str] = []
    for rel in tracked_files(root):
        if not rel.endswith("/RULES.md") or classify(rel, rules) != mode:
            continue
        unit_dir = rel[: -len("/RULES.md")]
        if (root / unit_dir / "CHANGELOG.md").exists():
            units.append(unit_dir)
    return units


def triage(pendulum: Path, project: Path, conf: dict[str, str],
           rules: list[tuple[str, str]]) -> list[dict[str, str]]:
    """Build per-unit triage rows comparing pendulum (source) to the project (based-on)."""
    rows: list[dict[str, str]] = []
    for unit in find_units(pendulum, rules):
        pend_version = read_frontmatter(pendulum / unit / "RULES.md").get("version", "0.0.0")
        proj_unit = specialize(unit, conf)  # name-map to the project's path
        proj_rules = project / proj_unit / "RULES.md"

        if not proj_rules.exists():
            rows.append({"unit": proj_unit, "state": "new", "severity": severity("0.0.0", pend_version),
                         "pendulum": pend_version, "based_on": "—", "project": "—", "excerpt": ""})
            continue

        proj_fm = read_frontmatter(proj_rules)
        based = proj_fm.get("based-on", "0.0.0")
        proj_version = proj_fm.get("version", based)
        state = triage_state(pend_version, based, proj_version)

        excerpt = ""
        changelog = pendulum / unit / "CHANGELOG.md"
        if state in ("clean-update", "diverged") and changelog.exists():
            excerpt = changelog_excerpt_since(changelog.read_text(encoding="utf-8"), based)

        rows.append({"unit": proj_unit, "state": state, "severity": severity(based, pend_version),
                     "pendulum": pend_version, "based_on": based, "project": proj_version, "excerpt": excerpt})
    return rows


def render_triage(rows: list[dict[str, str]]) -> int:
    """Print the triage dashboard: a summary then per-unit detail for actionable states."""
    states = ("diverged", "clean-update", "new", "project-ahead", "unchanged")
    flags = {"major": "‼ ", "minor": "+ ", "patch": "· ", "none": "  "}

    counts = {state: 0 for state in states}
    for row in rows:
        counts[row["state"]] = counts.get(row["state"], 0) + 1

    print("Triage — pendulum (source) vs project (based-on):\n")
    for state in states:
        print(f"  {state:13} {counts[state]:>4}")
    print()

    ordered = sorted(rows, key=lambda r: (states.index(r["state"]), r["unit"]))
    for row in ordered:
        if row["state"] in ("unchanged", "project-ahead"):
            continue
        flag = flags.get(row["severity"], "  ")
        print(f"  [{row['state']:11}] {flag}{row['unit']}  "
              f"({row['based_on']} → {row['pendulum']}; project {row['project']})")
        for line in row["excerpt"].splitlines()[:6]:
            print(f"        {line}")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--to", help="path to target project root (required for sync)")
    parser.add_argument("--audit", action="store_true",
                        help="classify every tracked file, report coverage, exit 1 on gaps")
    parser.add_argument("--brand-audit", action="store_true", dest="brand_audit",
                        help="report pendulum brand literals in the shipped (MERGE) surface; exit 1 if any")
    parser.add_argument("--triage", action="store_true",
                        help="report per-unit version state vs the project's based-on (needs --to); no sync")
    parser.add_argument("--init", action="store_true",
                        help="first-run: transform THIS fresh clone into the project in place (needs .cortex-sync.conf)")
    parser.add_argument("--resolve-t0", action="store_true", dest="resolve_t0",
                        help="auto-resolve a T0 baseline merge by mode (needs --to; run after `git merge`)")
    parser.add_argument("--advance", action="store_true",
                        help="post-merge gate: run `make test-all` in the target, advance .cortex-version on green (needs --to)")
    parser.add_argument("--prune-retired", action="store_true", dest="prune_retired",
                        help="delete the target's files that match a RETIRED manifest entry (review the report first)")
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--yes", action="store_true")
    parser.add_argument("--force", action="store_true",
                        help="skip the source-green CI gate (e.g. an unpushed local commit you trust)")
    args = parser.parse_args()

    pendulum = Path(__file__).resolve().parents[2]
    rules = load_manifest(pendulum)

    if args.init:
        return init_in_place(pendulum, load_conf(pendulum), args.dry_run)

    if args.audit:
        return audit(pendulum, rules)

    if args.brand_audit:
        return brand_audit(pendulum, rules)

    if not args.to:
        sys.exit("error: --to is required for sync/triage (or use --audit)")

    target = Path(args.to).resolve()
    if args.triage:
        if not target.is_dir():
            sys.exit(f"error: target {target} is not a directory")
        return render_triage(triage(pendulum, target, load_conf(target), rules))

    if args.resolve_t0:
        if not target.is_dir():
            sys.exit(f"error: target {target} is not a directory")
        return resolve_t0(pendulum, target, load_conf(target), rules, args.dry_run)

    if args.advance:
        if not target.is_dir():
            sys.exit(f"error: target {target} is not a directory")
        return advance(target, args.dry_run, args.force)

    if not target.is_dir():
        sys.exit(f"error: target {target} is not a directory")

    conf = load_conf(target)
    src_version = run(["git", "rev-parse", "HEAD"], pendulum) or "unknown"

    stamp = target / ".cortex-version"
    prev_version = stamp.read_text(encoding="utf-8").strip() if stamp.exists() else "(none)"

    buckets = files_by_mode(pendulum, rules)

    unclassified = buckets.get("UNCLASSIFIED", [])
    if unclassified:
        print(f"⚠️  {len(unclassified)} UNCLASSIFIED file(s) — manifest gap, run --audit:")
        for rel in unclassified:
            print(f"    {rel}")
        sys.exit("error: refusing to sync with unclassified files; fix the manifest first")

    # ---- OVERWRITE: transform -> write straight to the target worktree ----
    #
    # The PATH is name-mapped too, by the same `specialize()` the MERGE plan and `--init` use.
    # This block used to write the raw path, on the assumption that the AI layer carries no
    # project token in its names. `battoni-explain-pendulum.md` and its `.cursor` mirror are the
    # counter-example: they landed in every project named after pendulum while their text spoke
    # of the project. Worse, once `--init` started naming them correctly, writing the raw path
    # here would have put the pendulum-named copy back BESIDE the right one — two commands, the
    # stale one carrying the old text.
    #
    # `stale_names` is that old copy: an OVERWRITE file is pendulum's to own, so when its name
    # maps to something else the previous name is removed rather than left as a second answer.
    overwrite_planned: list[tuple[Path, bytes, bool]] = []  # (dst, new_bytes, is_text)
    stale_names: list[str] = []
    for rel in buckets.get("OVERWRITE", []):
        raw = (pendulum / rel).read_bytes()
        try:
            text = raw.decode("utf-8")
            is_text = True
        except UnicodeDecodeError:
            is_text = False
        # The sync engine itself is OVERWRITE, but must land VERBATIM, not specialized:
        # the project runs `make sync-test` against this copy, and specialize() would mangle
        # the engine's own rewrite table + identifiers. (manifest.tsv is excepted by
        # is_engine_internal — it maps project paths and must carry app.X/api.X.)
        if is_engine_internal(rel):
            new_bytes = raw
        elif is_text:
            new_bytes = specialize_file(rel, text, conf).encode("utf-8")
        else:
            new_bytes = raw
        # The engine keeps the upstream vocabulary in its names as in its text (it is read by
        # `make sync-test` in the project); everything else follows the content.
        target_rel = rel if is_engine_internal(rel) else specialize(rel, conf)
        if target_rel != rel and (target / rel).exists():
            stale_names.append(rel)
        dst = target / target_rel
        if dst.exists() and dst.read_bytes() == new_bytes:
            continue
        overwrite_planned.append((dst, new_bytes, is_text))

    # ---- MERGE: build the vendor-branch plan (executed by generate_upstream) ----
    merge_plan = build_merge_plan(pendulum, buckets.get("MERGE", []), conf)

    # ---- scope: drop optional sub-projects this target opted out of at bootstrap ----
    opted_out = {root for root in OPTIONAL_SUBPROJECTS if not (target / root).is_dir()}
    skipped_optional = 0
    if opted_out:
        before = len(merge_plan)
        merge_plan = [entry for entry in merge_plan if entry[0].split("/", 1)[0] not in opted_out]
        skipped_optional = before - len(merge_plan)

    # ---- report ----
    print(f"Source (pendulum):  {pendulum}   @ {src_version[:12]}")
    print(f"Target:             {target}")
    print(f"Project mapping:    celer->{conf['APP']}  arcus->{conf['API']}  pendulum->{conf['PROJECT']}")
    print(f"Target version:     {prev_version[:12]} -> {src_version[:12]}")
    print()
    print(f"  OVERWRITE (direct write):  {len(overwrite_planned)} changed / {len(buckets.get('OVERWRITE', []))} total")
    print(f"  MERGE (vendor branch):     {len(merge_plan)}")
    print(f"  LOCAL (untouched):         {len(buckets.get('LOCAL', []))}")
    print(f"  SKIP:                      {len(buckets.get('SKIP', []))}")
    if opted_out:
        print(f"  opted-out sub-projects:    {skipped_optional} file(s) skipped "
              f"({', '.join(sorted(opted_out))} — not in this project)")

    retired_present = find_retired_present(target, rules)
    if retired_present:
        print(f"\n  RETIRED (pendulum renamed/removed): {len(retired_present)} file(s) still in {target.name}:")
        for rel in retired_present:
            print(f"    {rel}")
        if not args.prune_retired:
            print("  → move any project edits they hold into the current file, then re-run with --prune-retired.")
    report_claude_md_drift(pendulum, target, rules)

    # ---- B2b: source-green gate — verify pendulum's own CI before propagating ----
    enforce_source_green(pendulum, src_version, args.force, args.dry_run)

    # ---- loss report: local content a conflict-to-upstream could drop (review BEFORE merge) ----
    loss_rows = loss_report(target, merge_plan)

    # T0 on an already-diverged project is the dangerous case the runbook warns about ("run T0
    # while divergence is still brand-only"). A first sync (no .cortex-version) with substantial
    # at-risk content means the project forked features into shared files BEFORE baselining — the
    # empty merge base will make every one a conflict, and a blanket resolution would revert them.
    is_first_sync = prev_version == "(none)"
    junction_at_risk = [rel for rel, _ in loss_rows if is_junction(rel)]
    if is_first_sync and junction_at_risk:
        print("\n" + "=" * 78)
        print("‼️  T0 ON A DIVERGED PROJECT — this project never baselined and already extends")
        print(f"   {len(junction_at_risk)} shared junction file(s) (routes/nav/locales/seeders/config).")
        print("   The T0 merge will conflict on these; resolve them KEEPING BOTH SIDES (the loss")
        print("   report lists exactly what to preserve). Do NOT blanket --resolve-t0 to upstream.")
        print("   Consider the core/local split first so these never conflict again.")
        print("=" * 78)

    if args.dry_run:
        for dst, _bytes, is_text in overwrite_planned[:20]:
            print(f"  OW   {dst.relative_to(target)}{'' if is_text else '  [binary]'}")
        if len(overwrite_planned) > 20:
            print(f"  … and {len(overwrite_planned) - 20} more OVERWRITE")
        for rel in stale_names:
            print(f"  OW   {rel}  [removed — renamed to {specialize(rel, conf)}]")
        generate_upstream(target, merge_plan, src_version, dry_run=True)
        scaffolded = scaffold_local_stubs(pendulum, target, buckets.get("LOCAL", []), conf, dry_run=True)
        if scaffolded:
            print(f"\n[dry-run] would scaffold {len(scaffolded)} missing LOCAL stub(s) so the "
                  f"core's imports resolve: {', '.join(scaffolded[:5])}"
                  f"{' …' if len(scaffolded) > 5 else ''}")
        if loss_rows:
            render_loss_report(loss_rows, report_path=None)
        print("\n[dry-run] no files written, no commits.")
        return 0

    if loss_rows:
        render_loss_report(loss_rows, report_path=target / ".cortex-loss-report.txt")

    if not args.yes:
        warn = f" — ⚠️ {len(loss_rows)} file(s) carry at-risk local content" if loss_rows else ""
        reply = input(
            f"\nApply OVERWRITE ({len(overwrite_planned)}) + regenerate "
            f"'{UPSTREAM_BRANCH}' ({len(merge_plan)}) in {target.name}{warn}? [y/N] "
        ).strip().lower()
        if reply != "y":
            print("aborted.")
            return 1

    backup = backup_tag(target, src_version)
    if backup:
        print(f"\nRollback point: tag '{backup}' on {target.name}@HEAD "
              f"(undo with `git reset --hard {backup}`).")

    for dst, new_bytes, _is_text in overwrite_planned:
        dst.parent.mkdir(parents=True, exist_ok=True)
        dst.write_bytes(new_bytes)
        if dst.suffix == ".sh":  # preserve exec bit on hook scripts
            dst.chmod(0o755)
    print(f"\nOVERWRITE: wrote {len(overwrite_planned)} file(s) to {target.name}.")

    for rel in stale_names:
        (target / rel).unlink()
    if stale_names:
        print(f"OVERWRITE: removed {len(stale_names)} file(s) whose name the project word moved: "
              f"{', '.join(stale_names)}")

    if args.prune_retired and retired_present:
        for rel in retired_present:
            (target / rel).unlink()
        print(f"RETIRED: deleted {len(retired_present)} file(s) pendulum renamed or removed.")

    scaffolded = scaffold_local_stubs(pendulum, target, buckets.get("LOCAL", []), conf, dry_run=False)
    if scaffolded:
        print(f"Scaffolded {len(scaffolded)} missing LOCAL extension-point stub(s) so the core's "
              f"imports resolve (create-once, never overwritten): "
              f"{', '.join(scaffolded[:6])}{' …' if len(scaffolded) > 6 else ''}")

    generate_upstream(target, merge_plan, src_version, dry_run=False)

    # The engine stops here — it does NOT advance .cortex-version. That happens after
    # the human merges and the suite is green (DESIGN lifecycle step 5).
    print("\nNext steps (vendor branch committed; finish the sync by hand):")
    print(f"  1. cd {target}")
    print(f"  2. git merge {UPSTREAM_BRANCH}            # resolve any conflicts")
    if loss_rows:
        print("     ↳ keep the local content in .cortex-loss-report.txt; do NOT take upstream blindly")
    print(f"  3. python3 cortex/sync/sync.py --advance --to {target.name}")
    print("       ↳ runs make test-all and advances .cortex-version ONLY if green")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
