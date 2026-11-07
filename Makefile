.PHONY: help install app build preview docs-install docs docs-gen docs-build docs-preview portal-install portal portal-extract portal-build portal-preview unit unit-watch integration e2e pest test test-all pre-commit check-app check-api check-portal check-cortex sync-mirror sync-mirror-check handoff fresh clear sanitize pint-api.rnc phpstan-api.rnc sanitize-api.rnc sync-audit sync-brand-audit sync-test sync-test-backport sync-test-engine sync-test-init

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

e2e: ## app.rnc — e2e (Playwright; starts its own vite --mode test, API mocked — no backend)
	cd app.rnc && npm run test:e2e

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
pre-commit: sync-mirror-check sanitize test-all ## mechanical pre-commit gate (static checks + mirror parity + sanitize + tests)

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
check-cortex: ## cortex — CI's sync checks: manifest audit, classification, back-port, handoff, mirror + hook tests, engine + init proofs
	@if [ -f cortex/sync/sync.py ] && [ ! -f .cortex-sync.conf ]; then \
		python3 cortex/sync/sync.py --audit && \
		python3 cortex/sync/test_sync.py && \
		python3 cortex/sync/test_backport.py && \
		python3 cortex/sync/test_handoff.py && \
		python3 cortex/sync/test_cursor_mirror.py && \
		python3 cortex/hooks/test_claude_hooks.py && \
		bash cortex/sync/test_engine.sh && \
		bash cortex/sync/test_init.sh; \
	else \
		echo "check-cortex: not the rnc upstream — skipped."; \
	fi

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
