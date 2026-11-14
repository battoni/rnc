.PHONY: help install app build preview docs-install docs docs-gen docs-build docs-preview portal-install portal portal-extract portal-build portal-preview unit unit-watch integration e2e pest test test-all pre-commit check-app check-api check-portal check-cortex sync-mirror sync-mirror-check handoff fresh clear sanitize pint-api.rnc phpstan-api.rnc sanitize-api.rnc sync-audit sync-brand-audit sync-test sync-test-backport sync-test-engine sync-test-init wt-test wt-new wt-up wt-app wt-down wt-discard wt-gc

.DEFAULT_GOAL := help

# A dot is allowed in a target name: downstream the sync renames `pint-api.rnc` to `pint-api.<project>`,
# and a pattern without it drops those targets from the help in silence.
help: ## Show this help
	@grep -hE '^[a-zA-Z0-9_.-]+:.*## ' $(MAKEFILE_LIST) | sort | awk 'BEGIN{FS=":.*## "}{printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## app.rnc — install dependencies
	cd app.rnc && npm install

app: ## app.rnc — run the dev server
	cd app.rnc && npm run dev

build: ## app.rnc — production build
	cd app.rnc && npm run build

preview: ## app.rnc — build then preview
	cd app.rnc && npm run build && npm run preview

docs-install: ## codelumen — install the handbook deps
	cd codelumen && npm install

docs: ## codelumen — run the handbook dev server (regenerates rule pages first)
	cd codelumen && npm run docs:dev

docs-gen: ## codelumen — regenerate rule pages from .claude/rules
	cd codelumen && npm run docs:gen

docs-build: ## codelumen — build the handbook (regenerates rule pages first)
	cd codelumen && npm run docs:build

docs-preview: ## codelumen — build then preview the handbook
	cd codelumen && npm run docs:build && npm run docs:preview

portal-install: ## docs portal — install deps
	cd docs.rnc && npm install

portal: ## docs portal — run the dev server (extracts codebase data first)
	cd docs.rnc && npm run docs:dev

portal-extract: ## docs portal — regenerate endpoints/schema/views data
	cd docs.rnc && npm run docs:extract

portal-build: ## docs portal — build the static site (extracts first)
	cd docs.rnc && npm run docs:build

portal-preview: ## docs portal — build then preview the static site
	cd docs.rnc && npm run docs:build && npm run docs:preview

# `test:unit:run`, not `test:unit`: plain `vitest` enters watch mode in a terminal and never
# exits, which froze `make pre-commit` right after the unit tests. Watch has its own target.
unit: ## app.rnc — unit + integration specs (Vitest, single run)
	cd app.rnc && npm run test:unit:run

unit-watch: ## app.rnc — Vitest in watch mode
	cd app.rnc && npm run test:unit

integration: unit ## app.rnc — alias for unit (includes integration specs)

e2e: ## app.rnc — e2e (Playwright; its own vite --mode test on this checkout's E2E_PORT, API mocked — no backend) [E2E_ARGS=<spec file> …]
	@port="$${E2E_PORT:-$$(python3 scripts/worktree.py port)}" || exit 1; \
	echo "e2e on port $$port"; \
	cd app.rnc && E2E_PORT="$$port" npm run test:e2e -- $(E2E_ARGS)

pest: ## api.rnc — Pest feature + unit tests
	cd api.rnc && vendor/bin/pest

test: pest ## alias for pest (compat)

test-all: unit e2e pest ## run app.rnc unit/integration + app.rnc e2e + api.rnc pest

handoff: ## eject a clean client-handoff copy (strips AI tooling, keeps RULES.md)
	python3 cortex/sync/handoff.py \
		$(if $(OUT),--out $(OUT)) \
		$(if $(PROJECTS),--projects "$(PROJECTS)") \
		$(if $(VERIFY),--verify) \
		$(if $(FORCE),--force)

sync-mirror: ## regenerate .cursor/rules mirrors from their canonical .claude sources
	python3 cortex/sync/cursor_mirror.py

sync-mirror-check: ## fail if any .cursor mirror has drifted from .claude (no writes)
	python3 cortex/sync/cursor_mirror.py --check

# The local mirror of .github/workflows/ci.yml. Static checks first (they fail in seconds),
# then the mirror parity, the fixers and the suites. The two rule lines are one prerequisite
# list, in this order. For a client handoff, cortex/sync/handoff.py drops the upstream-only
# targets (check-cortex, sync-mirror-check) from every prerequisite list, whichever line they
# sit on, and keeps the rest. What CI runs that this does NOT: the portal and handbook BUILDS and the
# landing's `npm test` (slow, and deployed from main by Vercel, which builds them anyway), and
# `composer audit` (informational there too). Unlike CI, `sanitize` FIXES lint and format
# instead of only checking them, so a green run here can still leave files to commit.
pre-commit: check-app check-api check-portal check-cortex
pre-commit: sync-mirror-check sanitize wt-test test-all ## mechanical pre-commit gate (static checks + mirror parity + sanitize + tests)

check-app: ## app.rnc — CI's static checks: module aliases, type-check
	cd app.rnc && npm run check:aliases && npm run type-check

check-api: ## api.rnc — CI's static checks over the whole app: Pint (--test) + PHPStan
	cd api.rnc && vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G --no-progress

check-portal: ## docs portal — CI's checks: extracted data current, gate tests, markdown lint
	@if [ -f docs.rnc/package-lock.json ]; then \
		cd docs.rnc && npm run docs:extract:check && npm test && npm run lint; \
	else \
		echo "check-portal: no docs portal here — skipped."; \
	fi

# Upstream-only, like the matching CI steps: a derived project tracks .cortex-sync.conf and
# rnc never does. A recipe rather than prerequisites, so a handoff copy (no cortex/, and
# the sync-* targets stripped) skips it instead of failing on a target that is gone.
check-cortex: ## cortex — CI's sync checks: manifest audit, classification, back-port, handoff, mirror + hook + flow-status tests, engine + init proofs
	@if [ -f cortex/sync/sync.py ] && [ ! -f .cortex-sync.conf ]; then \
		python3 cortex/sync/sync.py --audit && \
		python3 cortex/sync/test_sync.py && \
		python3 cortex/sync/test_backport.py && \
		python3 cortex/sync/test_handoff.py && \
		python3 cortex/sync/test_cursor_mirror.py && \
		python3 cortex/hooks/test_claude_hooks.py && \
		python3 scripts/test_flow_status.py && \
		bash cortex/sync/test_engine.sh && \
		bash cortex/sync/test_init.sh; \
	else \
		echo "check-cortex: not the rnc upstream — skipped."; \
	fi

wt-test: ## scripts — worktree script tests (labels, ports, env files, new/up/app/down/discard/gc/port; temp repos, stubbed lsof/mysql/valet/sudo/gh/composer/npm)
	python3 scripts/test_worktree.py

wt-new: ## scripts — new worktree .claude/worktrees/<ID> from origin/main with its deps (ID=<id> [SLUG=<slug>] [BRANCH=<branch>])
	@test -n "$(ID)" || { echo "usage: make wt-new ID=<id> [SLUG=<slug>] [BRANCH=<branch>]"; exit 2; }
	python3 scripts/worktree.py new "$(ID)" $(if $(SLUG),--slug "$(SLUG)") $(if $(BRANCH),--branch "$(BRANCH)")

wt-up: ## scripts — serve this worktree (run inside it): Valet api/app.<label> sites, its own DB copy, env files, migrate (refuses on SQLite: rnc)
	python3 scripts/worktree.py up

wt-app: ## scripts — this worktree's Vite dev server on its own HTTPS port, E2E_PORT exported (after wt-up)
	python3 scripts/worktree.py app

wt-down: ## scripts — tear a worktree down once its PR merged and it is clean and quiet: Valet sites, DB copy, folder, branch (ID=<id>)
	@test -n "$(ID)" || { echo "usage: make wt-down ID=<id>"; exit 2; }
	python3 scripts/worktree.py down "$(ID)"

wt-discard: ## scripts — throw an UNMERGED worktree away (same checks as wt-down but merged; open PR refused); a dry run until CONFIRM=<id> repeats ID
	@test -n "$(ID)" || { echo "usage: make wt-discard ID=<id> [CONFIRM=<id>]"; exit 2; }
	python3 scripts/worktree.py discard "$(ID)" $(if $(CONFIRM),--confirm "$(CONFIRM)")

wt-gc: ## scripts — wt-down on every worktree that passes; lists the left-behind ones (closed PR, 7 days idle) with why
	python3 scripts/worktree.py gc

sync-audit: ## cortex — manifest coverage audit + classification unit tests
	python3 cortex/sync/sync.py --audit

sync-brand-audit: ## cortex — flag rnc brand literals in the shipped (MERGE) surface
	python3 cortex/sync/sync.py --brand-audit

sync-test: ## cortex — classification unit tests
	python3 cortex/sync/test_sync.py

sync-test-backport: ## cortex — back-port preflight/audit/diff tests (read api.rnc/vendor: composer install first)
	python3 cortex/sync/test_backport.py

sync-test-engine: ## cortex — end-to-end engine proof against a disposable temp clone
	bash cortex/sync/test_engine.sh

sync-test-init: ## cortex — first-run in-place transform proof (--init)
	bash cortex/sync/test_init.sh

sanitize: ## app.rnc — lint + format + sort locales
	cd app.rnc && npm run lint && npm run format && npm run sort-locales

pint-api.rnc: ## api.rnc — Pint on changed PHP (branch diff + uncommitted)
	bash scripts/pint-changed.sh

phpstan-api.rnc: ## api.rnc — PHPStan on changed PHP (larastan comes with composer install)
	bash scripts/phpstan-changed.sh

sanitize-api.rnc: pint-api.rnc phpstan-api.rnc ## api.rnc — Pint + PHPStan on changed PHP

fresh: ## api.rnc — migrate:fresh --seed
	cd api.rnc && php artisan migrate:fresh --seed

clear: ## api.rnc — optimize:clear
	cd api.rnc && php artisan optimize:clear

# Project-owned make targets (LOCAL) — the cortex sync never touches Makefile.local.
# Add or override targets there; they survive every rnc update. The leading '-'
# makes the include silent when the file is absent.
-include Makefile.local
