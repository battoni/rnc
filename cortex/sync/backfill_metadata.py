#!/usr/bin/env python3
"""
Backfill the per-unit metadata layer (Phase A.2 / A.3-changelog).

For every RULES.md under a root:
  - prepend YAML frontmatter (version / origin / based-on) if not already present,
  - create a sibling CHANGELOG.md seeded with a 1.0.0 baseline entry if missing.

Idempotent: re-running skips files that already have frontmatter / changelog.
The unit name is derived from the RULES.md H1 title (after "RULES — " / "RULES - "),
falling back to the parent folder name.

Usage:
    python3 cortex/sync/backfill_metadata.py <root> [--version X.Y.Z] [--date YYYY-MM-DD] [--dry-run]

Example:
    python3 cortex/sync/backfill_metadata.py celer/src --dry-run
    python3 cortex/sync/backfill_metadata.py celer/src
"""
from __future__ import annotations

import argparse
import datetime as _dt
import re
from pathlib import Path

FRONTMATTER_TEMPLATE = (
    "---\n"
    "version: {version}\n"
    "origin: pendulum\n"
    "based-on: {version}\n"
    "---\n"
)


def strip_frontmatter(text: str) -> str:
    """Drop a leading `---` YAML block, so the fence is never read as the title."""
    stripped = text.lstrip()
    if not stripped.startswith("---"):
        return stripped
    lines = stripped.splitlines()
    for index, line in enumerate(lines[1:], start=1):
        if line.strip() == "---":
            return "\n".join(lines[index + 1:]).lstrip()
    return stripped


def derive_unit_name(rules_text: str, folder: Path) -> str:
    body = strip_frontmatter(rules_text)
    first = body.splitlines()[0] if body.strip() else ""
    if not first.startswith("#"):
        return folder.name
    heading = first.lstrip("#").strip()
    # "RULES — MMainDialog" / "RULES - X" -> the part after the dash
    match = re.match(r"RULES\s*[—-]\s*(.+)", heading)
    if match:
        return match.group(1).strip()
    if heading:
        return heading
    return folder.name


def changelog_body(unit: str, version: str, date: str) -> str:
    return (
        f"# Changelog — {unit}\n\n"
        "All notable changes to this unit are documented here. Versioned with SemVer.\n"
        "This file is the canonical source for the unit's version "
        "(RULES.md frontmatter mirrors it).\n\n"
        f"## {version} — {date}\n\n"
        "### Added\n"
        "- Initial documented baseline.\n"
    )


def has_frontmatter(text: str) -> bool:
    return text.lstrip().startswith("---")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("root", help="root dir to scan for RULES.md (e.g. celer/src)")
    parser.add_argument("--version", default="1.0.0")
    parser.add_argument("--date", default=_dt.date.today().isoformat())
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    root = Path(args.root)
    if not root.is_dir():
        raise SystemExit(f"error: {root} is not a directory")

    rules_files = sorted(root.rglob("RULES.md"))
    fm_added = fm_skipped = cl_added = cl_skipped = 0

    for rules in rules_files:
        text = rules.read_text(encoding="utf-8")
        unit = derive_unit_name(text, rules.parent)

        # 1. frontmatter
        if has_frontmatter(text):
            fm_skipped += 1
        else:
            new_text = FRONTMATTER_TEMPLATE.format(version=args.version) + text
            if args.dry_run:
                print(f"[FM ] would stamp  {rules}  (unit: {unit})")
            else:
                rules.write_text(new_text, encoding="utf-8")
                print(f"[FM ] stamped     {rules}")
            fm_added += 1

        # 2. changelog sibling
        changelog = rules.parent / "CHANGELOG.md"
        if changelog.exists():
            cl_skipped += 1
        else:
            body = changelog_body(unit, args.version, args.date)
            if args.dry_run:
                print(f"[CL ] would create {changelog}  (unit: {unit})")
            else:
                changelog.write_text(body, encoding="utf-8")
                print(f"[CL ] created     {changelog}")
            cl_added += 1

    print()
    print(f"RULES.md found:      {len(rules_files)}")
    print(f"frontmatter added:   {fm_added}   (already had: {fm_skipped})")
    print(f"CHANGELOG created:   {cl_added}   (already had: {cl_skipped})")
    if args.dry_run:
        print("\n[dry-run] nothing written.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
