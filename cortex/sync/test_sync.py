#!/usr/bin/env python3
"""Unit tests for cortex sync classification (Phase B2a.1).

Pure tests over the manifest + classify() — no git writes, no target repo.
Run:  python3 cortex/sync/test_sync.py   (exit 0 = pass, 1 = fail)
"""
from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from backfill_metadata import derive_unit_name  # noqa: E402
from sync import (  # noqa: E402
    based_on_drift_in_text,
    build_merge_plan,
    CODE_SUFFIXES,
    IDENT_COLLISION_RE,
    YAML_KEY_COLLISION_RE,
    changelog_excerpt_since,
    classify,
    claude_md_drift,
    find_retired_present,
    evaluate_source_checks,
    brand_audit_allows,
    find_brand_leaks_in_text,
    find_based_on_drift,
    find_token_collisions,
    files_by_mode,
    is_junction,
    is_local_stub,
    load_manifest,
    parse_semver,
    parse_vendor_sha,
    project_only_lines,
    severity,
    specialize,
    specialize_file,
    tracked_files,
    triage_state,
    union_json,
)

ROOT = Path(__file__).resolve().parents[2]
RULES = load_manifest(ROOT)

failures: list[str] = []


def check(name: str, cond: bool) -> None:
    print(f"  {'ok ' if cond else 'XX '} {name}")
    if not cond:
        failures.append(name)


# ---- coverage invariants (no hardcoded counts — those drift as files are added) ----
buckets = files_by_mode(ROOT, RULES)
check("no UNCLASSIFIED files", "UNCLASSIFIED" not in buckets)
check("no tracked file is RETIRED (a retired path must not exist upstream)", "RETIRED" not in buckets)
check(
    "buckets cover every tracked file",
    sum(len(v) for v in buckets.values()) == len(tracked_files(ROOT)),
)

# ---- specific classifications (pure string matching; existence irrelevant) ----
cases = {
    "OVERWRITE": [
        "cortex/sync/DESIGN.md",
        ".claude/commands/battoni-review-vue-conventions.md",
        ".cursor/rules/celer-01-vue-checklist.mdc",
        # the session command reads the sub-projects from CLAUDE.md, so it travels (2026-11-04)
        ".claude/commands/battoni-start-session.md",
        ".cursor/rules/battoni-start-session.mdc",
        ".claude/feature-flow.md",
        ".claude/delegation-policy.md",
    ],
    "MERGE": [
        ".github/workflows/ci.yml",
        "celer/e2e/auth/login.spec.ts",
        "celer/src/modules/User/views/Users/UsersView.view.vue",
        "arcus/tests/Feature/Auth/RoleTest.php",
        "codelumen/package.json",
        "Makefile",
    ],
    "LOCAL": [
        "CLAUDE.md",
        "README.md",
        ".claude/settings.json",
        ".cursor/rules/celer-folder-theme.mdc",
        "arcus/composer.lock",
        "celer/package-lock.json",
        # brand / visual identity — must never propagate (overwrites a project's look)
        "celer/src/styles/theme/colors.css",
        "celer/src/components/atoms/ALogo/ALogo.vue",
        "celer/src/components/atoms/ALogo/logo-full-light.png",
        "celer/src/assets/logo/logo-raw.svg",
        "celer/public/favicon/favicon.ico",
        "celer/public/favicon/manifest.json",
        "celer/index.html",
        # a project's own feature folders
        "specs/README.md",
        "specs/pendulum-1-example/spec.md",
        # the brand chain written by the battoni-new-brand skill
        "brand/SYSTEM.md",
        # extension points: the project-owned half of a synced core file
        "arcus/app/Modules/Auth/Helpers/permission-groups.local.php",
        "arcus/app/Modules/Auth/Helpers/manager-exclusions.local.php",
        "arcus/app/Modules/Auth/Helpers/user-exclusions.local.php",
    ],
}
cases["RETIRED"] = [
    ".claude/commands/reviewVueConventions.md",
    ".claude/commands/start-session.md",
    ".cursor/rules/start-session.mdc",
]
for want, paths in cases.items():
    for path in paths:
        check(f"{path} -> {want}", classify(path, RULES) == want)

# ---- mode exclusion invariants (what the vendor-branch generator relies on) ----
merge = buckets.get("MERGE", [])
check("MERGE excludes the AI layer", not any(
    m.startswith((".claude/", ".cursor/", "cortex/")) for m in merge))
check("MERGE excludes lock files", not any(
    m.endswith(("package-lock.json", "composer.lock", "pnpm-lock.yaml", "yarn.lock")) for m in merge))

overwrite = buckets.get("OVERWRITE", [])
check("OVERWRITE lives only in the AI layer", all(
    o.startswith((".claude/", ".cursor/", "cortex/")) for o in overwrite))

# ---- B2a.2: build_merge_plan — path name-mapping + content transform ----
FIXTURE_CONF = {"PROJECT": "zion", "APP": "app.zion", "API": "api.zion"}

# text file: path mapped celer/ -> app.zion/, content transformed
text_rel, text_bytes, text_bin = build_merge_plan(ROOT, ["celer/README.md"], FIXTURE_CONF)[0]
text_body = text_bytes.decode("utf-8")
check("text MERGE path mapped celer/ -> app.zion/", text_rel == "app.zion/README.md")
check("text MERGE not flagged binary", text_bin is False)
check("text MERGE content: 'Celer' rewritten away", "Celer" not in text_body)
check("text MERGE content: project name applied", "app.zion" in text_body)

# arcus path mapping arcus/ -> api.zion/
arcus_rel = build_merge_plan(ROOT, ["arcus/tests/Feature/Auth/RoleTest.php"], FIXTURE_CONF)[0][0]
check("arcus MERGE path mapped arcus/ -> api.zion/", arcus_rel == "api.zion/tests/Feature/Auth/RoleTest.php")

# binary file: path mapped, bytes copied verbatim (no transform)
BIN = "celer/public/favicon/android-icon-144x144.png"
bin_rel, bin_bytes, bin_is_binary = build_merge_plan(ROOT, [BIN], FIXTURE_CONF)[0]
check("binary MERGE path mapped", bin_rel == "app.zion/public/favicon/android-icon-144x144.png")
check("binary MERGE flagged is_binary", bin_is_binary is True)
check("binary MERGE bytes verbatim", bin_bytes == (ROOT / BIN).read_bytes())

# ---- specialize: the frontmatter says who AUTHORED the unit ----
_FRONTMATTER = (
    "---\nversion: 1.2.0\norigin: pendulum\nbased-on: 1.2.0\n---\n"
    "O celer usa o pendulum. Ver arcus/app e o token celer-01.\n"
)
_SPECIALIZED = specialize(_FRONTMATTER, FIXTURE_CONF)

# The bug this guards against: `origin: pendulum` holds the same word the rewrite replaces,
# so every synced unit used to arrive downstream claiming the project as its own origin.
check("specialize keeps origin: pendulum", "origin: pendulum" in _SPECIALIZED)
check("specialize does not invent origin: zion", "origin: zion" not in _SPECIALIZED)
# Everything around it must still be rewritten — a guard that froze the whole file would
# pass the assertion above and break the sync.
check("specialize still rewrites prose", "O app.zion usa o zion." in _SPECIALIZED)
check("specialize still rewrites arcus path", "api.zion/app" in _SPECIALIZED)
check("specialize still protects filename tokens", "celer-01" in _SPECIALIZED)

# Outside the opening frontmatter, `origin: pendulum` is prose or a template and follows the
# project. Guarded everywhere, the review commands arrived saying "(zion-origin units) … has
# `origin: pendulum`" and the generate-rules template stamped pendulum on the child's own units.
_PROSE = specialize(
    "# Review\n\n3. **(pendulum-origin units).** A unit whose frontmatter has `origin: pendulum`.\n\n"
    "```markdown\n---\nversion: 1.0.0\norigin: pendulum\nbased-on: 1.0.0\n---\n```\n",
    FIXTURE_CONF,
)
check("specialize rewrites origin in prose", "(zion-origin units)" in _PROSE and "`origin: zion`" in _PROSE)
check("specialize rewrites origin in a template", "\norigin: zion\n" in _PROSE and "pendulum" not in _PROSE)

# A project word is rewritten only as a word: `accelerometer` arrived as `acapp.zionometer` in
# every Permissions-Policy header, and `celer.pendulum.test` as `app.zion.zion.test`.
_WORDS = specialize(
    "accelerometer=(), https://celer.pendulum.test:5173, arcus.pendulum.test, Marcus, "
    "celer/src, CelerPreset, Pendulum's, (Celer) done\n",
    FIXTURE_CONF,
)
check("specialize leaves a word that contains celer", "accelerometer=()" in _WORDS)
check("specialize leaves a word that contains arcus", "Marcus" in _WORDS)
check("specialize maps the app host once", "https://app.zion.test:5173" in _WORDS)
check("specialize maps the api host once", " api.zion.test" in _WORDS)
# Inside a regex the host is written with escaped dots, and the plain mapping missed it:
# `#^https?://celer\.pendulum\.test(:\d+)?$#` arrived as `app.zion\.zion\.test`.
_PATTERN = specialize("CORS_ALLOWED_ORIGINS_PATTERNS=#^https?://celer\\.pendulum\\.test(:\\d+)?$#\n", FIXTURE_CONF)
check("specialize maps an escaped host once", "#^https?://app\\.zion\\.test(:\\d+)?$#" in _PATTERN)
check("specialize still rewrites a path and an identifier", "app.zion/src" in _WORDS and "app.zionPreset" in _WORDS)
check("specialize still rewrites the capitalised name", "zion's" in _WORDS and "done" in _WORDS and "(Celer)" not in _WORDS)

# ---- the manifest names the project's own public site, not pendulum's ----
# `SKIP public.battoni.dev/` is pendulum's landing site; no word in it is a project word, so
# specialize() left it verbatim and every child's `public.<project>/` fell into `MERGE *`.
_MANIFEST = specialize_file("cortex/sync/manifest.tsv", (ROOT / "cortex/sync/manifest.tsv").read_text(encoding="utf-8"), FIXTURE_CONF)
check("manifest: SKIP public.battoni.dev/ becomes SKIP public.zion/", "\nSKIP\tpublic.zion/\n" in _MANIFEST)
check("manifest: no SKIP public.battoni.dev/ left downstream", "\nSKIP\tpublic.battoni.dev/\n" not in _MANIFEST)
_OTHER = specialize_file("CLAUDE.md", "template: public.battoni.dev/\nSKIP\tpublic.battoni.dev/\n", FIXTURE_CONF)
check("manifest rewrite stays in the manifest", _OTHER.count("public.battoni.dev/") == 2)

# ---- B2c: triage pure logic ----
check("parse_semver normal", parse_semver("1.4.2") == (1, 4, 2))
check("parse_semver short", parse_semver("2") == (2, 0, 0))
check("parse_semver garbage", parse_semver("x.y") == (0, 0, 0))
check("parse_semver ordering", parse_semver("1.10.0") > parse_semver("1.9.9"))

# triage_state: (pendulum_version, based_on, project_version)
check("state unchanged", triage_state("1.2.0", "1.2.0", "1.2.0") == "unchanged")
check("state clean-update", triage_state("1.3.0", "1.2.0", "1.2.0") == "clean-update")
check("state diverged", triage_state("1.3.0", "1.2.0", "1.4.0") == "diverged")
check("state project-ahead", triage_state("1.2.0", "1.2.0", "1.5.0") == "project-ahead")

# severity of pendulum's delta over based-on
check("severity major", severity("1.2.0", "2.0.0") == "major")
check("severity minor", severity("1.2.0", "1.3.0") == "minor")
check("severity patch", severity("1.2.0", "1.2.1") == "patch")
check("severity none", severity("1.2.0", "1.2.0") == "none")

# changelog excerpt: only entries newer than based-on
_CHANGELOG = """# Changelog — X

## 1.3.0 — 2026-06-01
### Added
- new thing in 1.3

## 1.2.0 — 2026-05-30
### Added
- old thing in 1.2
"""
_excerpt = changelog_excerpt_since(_CHANGELOG, "1.2.0")
check("excerpt includes newer entry (1.3.0)", "1.3.0" in _excerpt and "new thing in 1.3" in _excerpt)
check("excerpt excludes based-on entry (1.2.0)", "1.2.0" not in _excerpt and "old thing in 1.2" not in _excerpt)
check("excerpt empty when up to date", changelog_excerpt_since(_CHANGELOG, "1.3.0") == "")

# ---- B2b: source-green gate decision logic (pure; no gh/network) ----
_completed = lambda concl: {"name": "ci", "status": "completed", "conclusion": concl}  # noqa: E731

check("gate GREEN when every run succeeded",
      evaluate_source_checks([_completed("success")], [])[0] == "GREEN")
check("gate GREEN counts neutral/skipped as passing",
      evaluate_source_checks(
          [_completed("skipped"), _completed("neutral")], [])[0] == "GREEN")
check("gate RED on a failed run",
      evaluate_source_checks([_completed("failure")], [])[0] == "RED")
check("gate RED on a legacy status error",
      evaluate_source_checks([], [{"context": "cov", "state": "error"}])[0] == "RED")
check("gate PENDING while a run is in progress",
      evaluate_source_checks(
          [{"name": "ci", "status": "in_progress", "conclusion": None}], [])[0] == "PENDING")
check("gate RED outranks PENDING",
      evaluate_source_checks(
          [{"name": "a", "status": "in_progress", "conclusion": None},
           _completed("failure")], [])[0] == "RED")
check("gate NO_CHECKS when nothing is reported",
      evaluate_source_checks([], [])[0] == "NO_CHECKS")
check("gate detail names the failing check",
      "ci" in evaluate_source_checks([_completed("failure")], [])[1])

# ---- identifier-collision guard: project token starting a code identifier ----
hit = lambda s: bool(IDENT_COLLISION_RE.search(s))  # noqa: E731
check("collision: CelerPreset flagged", hit("import CelerPreset from './theme';"))
check("collision: lowercase celerConfig flagged", hit("const celerConfig = {}"))
check("collision: ArcusClient flagged", hit("class ArcusClient {}"))
check("collision: filename token celer-01 NOT flagged", not hit("@import './celer-01-rules'"))
check("collision: path 'celer/src' NOT flagged", not hit("from 'celer/src/foo'"))
check("collision: substring 'accelerate' NOT flagged", not hit("const accelerate = true"))
check("collision: bare word celer NOT flagged", not hit("// see the celer project"))
# Mid- and end-of-identifier: specialize() rewrites the token after a `_` or a digit too, so
# boanerges got `def test_..._edited_app.boanerges_file_only(self):` and its hook suite never ran.
check("collision: mid-identifier _celer_ flagged", hit("    def test_runs_eslint_on_the_edited_celer_file_only(self):"))
check("collision: end-of-identifier _celer flagged", hit("const use_celer = 1"))
check("collision: digit-prefixed 2arcus flagged", hit("v2arcus_client = None"))
check("collision: _celer before lowercase NOT flagged (not rewritten)", not hit("my_celerity = 1"))
check("collision: .py is a code suffix", ".py" in CODE_SUFFIXES)

# ---- the same collision one level up: a project token as a YAML mapping key ----
# `celer:` as a job id became `app.impressao:` downstream, which a GitHub job id may not be
# (no dots). GitHub refused the whole workflow: zero jobs, no logs, 20 dead runs in
# new.impressao before anyone noticed. The identifier rule above cannot see it, because a key
# is followed by `:` rather than by an identifier character.
ykey = lambda s: bool(YAML_KEY_COLLISION_RE.match(s))  # noqa: E731
check("yaml: bare `celer:` key flagged", ykey("  celer:"))
check("yaml: bare `arcus:` key flagged", ykey("  arcus:"))
check("yaml: quoted key flagged", ykey('  "celer":'))
check("yaml: top-level key flagged", ykey("celer:"))
check("yaml: the identifier rule alone would MISS it", not hit("  celer:"))
check("yaml: `name: celer — ...` NOT flagged (value, not key)",
      not ykey("    name: celer — type-check") and not hit("    name: celer — type-check"))
check("yaml: `working-directory: celer` NOT flagged", not ykey("        working-directory: celer"))
check("yaml: unrelated key NOT flagged", not ykey("  mirror-sync:"))

# live source must be clean (CelerPreset -> themePreset, and the ci.yml job ids -> app/api)
check("source has zero token collisions", find_token_collisions(ROOT, RULES) == [])

# The general property, which outlives the word `celer`: every job id this repo ships must
# still be a legal job id AFTER the transform dots the project name.
import re as _re  # noqa: E402
_JOB_ID = _re.compile(r"^[A-Za-z_][A-Za-z0-9_-]*$")
_ci = (ROOT / ".github/workflows/ci.yml").read_text(encoding="utf-8")
_jobs_block = _ci.split("\njobs:\n", 1)[1]
_ids = _re.findall(r"^  ([^\s:#]+):", _jobs_block, _re.M)
check("ci.yml declares job ids", len(_ids) >= 2)
check("every ci.yml job id is legal upstream", all(_JOB_ID.match(i) for i in _ids))
_specialized = specialize(_ci, {"APP": "app.impressao", "API": "api.impressao",
                                "PROJECT": "new.impressao"})
_ids_down = _re.findall(r"^  ([^\s:#]+):", _specialized.split("\njobs:\n", 1)[1], _re.M)
check("every ci.yml job id survives the transform",
      all(_JOB_ID.match(i) for i in _ids_down))

# ---- based-on drift: pendulum is its own origin, so based-on must equal version ----
# Realigned once in 2026-06 with the assertion added to a REVIEW command; six units had drifted
# again by 2026-09, three of them invisible to the triage. The check lives where make runs now.
FM = "---\nversion: {v}\norigin: {o}\nbased-on: {b}\n---\n"
check("drift: pendulum unit with based-on < version flagged",
      based_on_drift_in_text(FM.format(v="1.3.0", o="pendulum", b="1.2.0")) == ("1.3.0", "1.2.0"))
check("drift: pendulum unit with based-on > version flagged",
      based_on_drift_in_text(FM.format(v="1.3.0", o="pendulum", b="1.8.1")) == ("1.3.0", "1.8.1"))
check("drift: aligned pendulum unit NOT flagged",
      based_on_drift_in_text(FM.format(v="1.3.0", o="pendulum", b="1.3.0")) is None)
# In a PROJECT the gap is the signal, not a defect — it is how local divergence is reported.
check("drift: non-pendulum origin NOT flagged",
      based_on_drift_in_text(FM.format(v="1.5.0", o="lifeshifter", b="1.3.0")) is None)
check("drift: header missing based-on NOT flagged",
      based_on_drift_in_text("---\nversion: 1.0.0\norigin: pendulum\n---\n") is None)
check("drift: no frontmatter at all NOT flagged", based_on_drift_in_text("# RULES\n") is None)
check("source has zero based-on drift", find_based_on_drift(ROOT) == [])

# ---- T0 union_json: upstream base + project-only deps (pure) ----
import json as _json  # noqa: E402
_ours = _json.dumps({"name": "proj", "dependencies": {"axios": "^1.0.0", "@vueuse/core": "^11"}})
_theirs = _json.dumps({"name": "app.x", "dependencies": {"axios": "^1.2.0"},
                       "devDependencies": {"vitest": "^3", "msw": "^2"}})
_merged = _json.loads(union_json(_ours, _theirs, ("dependencies", "devDependencies")))
check("union: upstream wins on shared dep (axios)", _merged["dependencies"]["axios"] == "^1.2.0")
check("union: project-only dep preserved (@vueuse/core)", _merged["dependencies"]["@vueuse/core"] == "^11")
check("union: upstream test toolchain kept", _merged["devDependencies"] == {"msw": "^2", "vitest": "^3"})
check("union: upstream top-level keys win (name)", _merged["name"] == "app.x")
check("union: deps sorted", list(_merged["dependencies"]) == sorted(_merged["dependencies"]))

# ---- loss report: project-only meaningful lines a sync could drop (pure) ----
# A project routes file that extended pendulum's with its own module routes.
_proj_routes = (
    "import { AuthRoutes } from '@AuthModule';\n"
    "import { CatalogRoutes } from '@CatalogModule';\n"
    "export default [ ...AuthRoutes, ...CatalogRoutes ];\n"
)
_pend_routes = (
    "import { AuthRoutes } from '@AuthModule';\n"
    "export default [ ...AuthRoutes ];\n"
)
_lost = project_only_lines(_proj_routes, _pend_routes)
check("loss: project-only import flagged", "import { CatalogRoutes } from '@CatalogModule';" in _lost)
check("loss: shared line not flagged", "import { AuthRoutes } from '@AuthModule';" not in _lost)
check("loss: trivial/punctuation line not flagged", not any(line in {"];", "}", ")"} for line in _lost))
check("loss: identical files yield nothing", project_only_lines(_pend_routes, _pend_routes) == [])
check(
    "loss: reindentation alone not flagged",
    project_only_lines("    const a = 1;\n", "const a = 1;\n") == [],
)
# De-dup: a project-only line repeated reports once.
check(
    "loss: duplicate project-only line reported once",
    project_only_lines("permission: 'catalog.read'\npermission: 'catalog.read'\n", "") == ["permission: 'catalog.read'"],
)

# Metadata lines differ between any two copies by construction — never loss.
_rules_project = "version: 1.5.0\norigin: chapaja\nbased-on: 1.4.0\n> For AI agents. Last updated: 2026-08-04.\n- Accepted divergence: the demo item was removed.\n"
_rules_pendulum = "version: 1.2.0\norigin: pendulum\nbased-on: 1.2.0\n> For AI agents. Last updated: 2026-09-14.\n"
check(
    "loss: unit metadata lines are not loss, a real rule line is",
    project_only_lines(_rules_project, _rules_pendulum) == ["- Accepted divergence: the demo item was removed."],
)

# ---- retired paths: renamed upstream, reported downstream ----
import tempfile  # noqa: E402

with tempfile.TemporaryDirectory() as _tmp:
    _target = Path(_tmp)
    (_target / ".claude" / "commands").mkdir(parents=True)
    (_target / ".cursor" / "rules").mkdir(parents=True)
    for _name in (".claude/commands/commit.md", ".claude/commands/battoni-commit.md", ".cursor/rules/start-session.mdc"):
        (_target / _name).write_text("x", encoding="utf-8")
    _present = find_retired_present(_target, RULES)
    check("retired: the old generation is found", _present == [".claude/commands/commit.md", ".cursor/rules/start-session.mdc"])
    check("retired: the live command is never reported", ".claude/commands/battoni-commit.md" not in _present)

# ---- CLAUDE.md drift: the always-loaded context is LOCAL and rots silently (pure) ----
_claude = "| Project | Type |\n| --- | --- |\n| `api.x/` | Laravel |\n| `app.x/` | Vue |\n| `cortex/` | AI |\n| `gone/` | old |\n\nRun `/start-session` first, then `/battoni-commit`.\n"
_drift = claude_md_drift(
    _claude,
    {"battoni-commit", "battoni-start-session"},
    {"api.x", "app.x", "docs.x"},
    {"api.x", "app.x", "docs.x", "cortex"},
)
check("claude-md: a command that does not exist is named", any("`/start-session`" in line for line in _drift))
check("claude-md: an existing command is not flagged", not any("battoni-commit" in line for line in _drift))
check("claude-md: an unlisted sub-project is flagged", any("`docs.x/`" in line and "does not list" in line for line in _drift))
check("claude-md: a listed folder that no longer exists is flagged", any("`gone/`" in line for line in _drift))
check("claude-md: a listed knowledge folder that exists is quiet", not any("`cortex/`" in line for line in _drift))
check("claude-md: a listed sub-project that exists is quiet", not any("`api.x/`" in line for line in _drift))

# ---- junction detection: extension-point files the T0 resolver must not take-theirs on ----
check("junction: frontend routes", is_junction("app.impressao/src/libraries/router/routes.ts"))
check("junction: API routes", is_junction("api.impressao/routes/api.php"))
check("junction: navbar", is_junction("app.impressao/src/components/unique/TheNavbar/TheNavbar.vue"))
check("junction: locales", is_junction("app.impressao/src/locales/pt-BR.json"))
check("junction: seeder registry", is_junction("api.impressao/database/seeders/DatabaseSeeder.php"))
check("junction: permission groups (api)", is_junction("api.impressao/app/Modules/Auth/Helpers/PermissionGroupsHelper.php"))
check("junction: the deleted front permission-groups.json is NOT junction (the groups come from the API)", not is_junction("app.impressao/src/modules/Auth/data/permission-groups.json"))
check("junction: Makefile", is_junction("Makefile"))
check("junction: a regular view is NOT junction", not is_junction("app.impressao/src/modules/Catalog/views/Inputs/InputsView.view.vue"))
check("junction: a regular seeder is NOT junction", not is_junction("api.impressao/database/seeders/ReferenceSeeder.php"))

# ---- brand-leak detection: keep pendulum brand out of the shipped surface (pure) ----
check("brand: domain literal flagged", find_brand_leaks_in_text('<div>battoni.dev</div>') == [(1, '<div>battoni.dev</div>')])
check("brand: author name flagged", bool(find_brand_leaks_in_text("title: 'Guilherme Battoni',")))
check("brand: theme token battoni-dev NOT flagged", find_brand_leaks_in_text("VITE_ACTIVE_THEME=battoni-dev") == [])
check("brand: unrelated text not flagged", find_brand_leaks_in_text("const x = 1;") == [])
check("brand: reference theme allowed", brand_audit_allows("celer/src/styles/theme/themes/battoni-dev.css"))
check("brand: attribution pin allowed", brand_audit_allows("celer/src/styles/components/attribution.css"))
check("brand: attribution pin allowed downstream", brand_audit_allows("app.x/src/styles/components/attribution.css"))
check("brand: other stylesheet NOT allowed", not brand_audit_allows("celer/src/styles/components/touch-target.css"))
check("brand: a view is NOT allowed", not brand_audit_allows("celer/src/modules/Auth/views/Login/LoginView.view.vue"))

# ---- the AI layer is text: a NUL byte makes git treat a rule as binary, and grep never finds it ----
_nul_files = [
    rel for rel in tracked_files(ROOT)
    if rel.startswith((".claude/", ".cursor/", "cortex/")) and rel.endswith((".md", ".mdc"))
    and (ROOT / rel).is_file() and b"\x00" in (ROOT / rel).read_bytes()
]
check(f"AI-layer markdown has no NUL byte {_nul_files}", _nul_files == [])

# ---- backfill: the CHANGELOG title comes from the H1, never from the frontmatter fence (pure) ----
_fm_rules = "---\nversion: 1.0.0\norigin: pendulum\nbased-on: 1.0.0\n---\n\n# RULES — Auth Module\n\nBody.\n"
check("backfill: frontmatter is skipped for the unit name", derive_unit_name(_fm_rules, Path("Auth")) == "Auth Module")
check("backfill: plain H1 still works", derive_unit_name("# RULES - MMainDialog\n", Path("x")) == "MMainDialog")
check("backfill: frontmatter with no heading falls back to the folder", derive_unit_name("---\nversion: 1.0.0\n---\n\nno heading\n", Path("Tip")) == "Tip")
check("backfill: empty file falls back to the folder", derive_unit_name("", Path("Tip")) == "Tip")

# ---- post-merge gate: the synced SHA is recovered from the vendor commit (pure) ----
check("advance: parses vendor sha", parse_vendor_sha("sync: pendulum@60ec0859cc70 -> pendulum-upstream (641 files)") == "60ec0859cc70")
check("advance: no sha -> None", parse_vendor_sha("chore: unrelated commit") is None)

# ---- local-stub scaffolding: the core's static imports must resolve downstream (pure) ----
check("stub: routes.local.ts", is_local_stub("app.x/src/libraries/router/routes.local.ts"))
check("stub: navItems.local.ts", is_local_stub("app.x/src/components/unique/TheNavbar/navItems.local.ts"))
check("stub: locales/local/en.json", is_local_stub("app.x/src/locales/local/en.json"))
check("stub: api.local.php", is_local_stub("api.x/routes/api.local.php"))
check("stub: TheLocalOverlays.local.vue", is_local_stub("app.x/src/layouts/TheLayout/TheLocalOverlays.local.vue"))
check("stub: Makefile.local", is_local_stub("Makefile.local"))
check("stub: manager-exclusions.local.php", is_local_stub("api.x/app/Modules/Auth/Helpers/manager-exclusions.local.php"))
check("stub: user-exclusions.local.php", is_local_stub("api.x/app/Modules/Auth/Helpers/user-exclusions.local.php"))
check("stub: a normal LOCAL file (colors.css) is NOT a stub", not is_local_stub("app.x/src/styles/theme/colors.css"))
check("stub: a core file is NOT a stub", not is_local_stub("app.x/src/libraries/router/routes.ts"))

# ---- every cortex test suite is wired into `make check-cortex` AND the CI cortex job ----
# test_handoff, test_cursor_mirror and test_claude_hooks once existed with neither running them,
# so a regression in the handoff or a hook would only show when someone remembered to run it.
_suites = sorted(
    str(path.relative_to(ROOT))
    for pattern in ("cortex/**/test_*.py", "cortex/**/test_*.sh")
    for path in ROOT.glob(pattern)
    if "node_modules" not in path.parts and "templates" not in path.parts
)
_makefile = (ROOT / "Makefile").read_text(encoding="utf-8") if (ROOT / "Makefile").is_file() else ""
_check_cortex = _makefile.split("\ncheck-cortex:", 1)[1].split("\n\n", 1)[0] if "\ncheck-cortex:" in _makefile else ""
_ci = (ROOT / ".github/workflows/ci.yml").read_text(encoding="utf-8")
_ci_cortex = _ci.split("\n  mirror-sync:", 1)[1].split("\n  app:", 1)[0] if "\n  mirror-sync:" in _ci else ""
check(f"suites: cortex test suites found {_suites}", len(_suites) >= 6)
if _makefile:
    check(f"suites: check-cortex runs every suite, missing {[s for s in _suites if s not in _check_cortex]}",
          all(s in _check_cortex for s in _suites))
check(f"suites: CI mirror-sync job runs every suite, missing {[s for s in _suites if s not in _ci_cortex]}",
      all(s in _ci_cortex for s in _suites))

print(f"\n{'PASS' if not failures else 'FAIL'} — {len(failures)} failure(s)")
sys.exit(1 if failures else 0)
