# <Product> — Build Estimate

> **Status:** order of magnitude, `<YYYY-MM-DD>`. **Ranges, not commitments.** Re-estimate per phase
> once the first phase is planned. Scope = the decisions in `PRODUCT-MEMORY.md` up to `D<n>`.

## Assumptions

- A senior developer building on this bootstrap — auth, module scaffolding, components, the test
  harness and the conventions already in place. That head start is baked into the LOW end.
- An AI-assisted build loop: built unattended, reviewed and QA'd by people.
- **The hours are engineering effort — the size of the work — not calendar.** The loop compresses
  calendar time; it does not compress review and QA. See "Reality of the loop".
- <Provider, platform or integration choices the numbers depend on, each pointing at its decision.>

## Effort by phase (engineering hours)

### Phase 1 — <the wedge: the smallest loop someone would pay for> — **~<low>–<high> h**

| Capability | Hours | Why that size |
| --- | ---: | --- |

### Phase 2 — <depth> — **~<low>–<high> h**

### Cross-cutting (spread across phases, ~15–25% overhead)

<Design system work, docs portal, deploy, QA rounds, migrations of legacy data.>

## Totals

## The owner's own time (planning and review, not building)

## Reality of the loop

- The loop crushes scaffolding, typing and test-writing hours, and shortens calendar a lot.
- It does **not** crush integrations with external money or identity providers, realtime
  infrastructure, or the **review and QA that gate every merge**. Those stay human-paced.
- **Throughput is bounded by how fast a person can review**, not by how fast code is produced.

## Biggest risks (what pushes toward or past the high end)

## Recommendation
