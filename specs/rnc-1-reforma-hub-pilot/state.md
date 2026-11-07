# State: Franco Reforma Hub — the real pilot
Stage: build F1/8
Updated: 07/11/2026
## Done
Intent approved 07/11 · spec closed 07/11 (26 R · 15 A · 9 D) · plan approved 07/11 (F1–F8). Bootstrap configured as rnc (9069414).
## Next
F1 Foundation.
## Local environment
(F1 fills this: URLs, database, seeded users and passwords, how to serve.)
## Pitfalls
- Node 24 (`.nvmrc`); the shell's nvm default is 20 — run `source ~/.nvm/nvm.sh && nvm use` first.
- Valet is broken in non-interactive shells (`_valet_run` not found): serve the API with `php artisan serve`.
- Pest runs on in-memory SQLite; the app on MySQL 8 — migrations must work on both.
## Away run
Stop at: PR after verify · Reviewer: fable-reviewer (#fable session) · Chrome QA: on
Started: 2026-11-07
## Decided while you were away
