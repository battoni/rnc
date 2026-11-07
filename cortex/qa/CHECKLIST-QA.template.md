# Manual QA checklist — template

> Copy to `cortex/qa/CHECKLIST-QA.md` in the project and fill it in. It is run by a person in a
> browser, without reading code. Distilled from lifeshifter's checklist (920 lines, 50 intentional
> behaviours, 18 verified fixes); the structure travels, its content does not.

The checklist is not a list of screens. It is a contract between the people who built the product
and the person testing it: **what is in scope and why, what looks like a bug and is not, what was
already fixed and must stay fixed, and what a report has to contain.** Each of those is a section.

---

## 0. Scope — read before starting

**Cut the scope at the fork point, not by instinct.** The project was bootstrapped from rnc,
which has its own QA. Name the commit where the project begins (the bootstrap commit, or the
transform right after it) and scope from it:

```bash
git diff <fork-commit>..HEAD --stat
```

- **Inherited and unchanged** — out of scope. It was tested upstream.
- **Inherited and modified** — only the modification is in scope. Test that the page header now
  stacks under 768px, not that it shows a title.
- **Written here** — in scope, whole.

### What was cut, and why

A table the tester can disagree with, row by row:

| Area | Status |
| --- | --- |
| _e.g. Login flows_ | **Cut.** Inherited whole from rnc. Only the screen's appearance is ours — section 2. |
| _e.g. Users CRUD_ | **Cut.** rnc's. The N changes that are ours are in section 4.1. |

**In scope, whole:** _list the modules, the brand, the theme, the navigation changes._

---

## 1. Prerequisites

- **How to reach the app** — the exact URL, and the address NOT to use (a CORS-blocked `localhost`
  looks like a screen that never loads).
- **Accounts** — a table: account, how to get it, what it is for. Say which account must never be
  deleted or deactivated.
- **Data** — what the fresh seed contains, and what to create before starting.
- **Browsers and widths** — including real devices where a section needs them, and the widths at
  which the layout changes shape.
- **Scheme, language, reduced motion** — how to switch each one.

## 2…9. The product, section by section

One section per surface in scope. Each item is a checkbox with a concrete action and an observable
result — "drag 'Sleep' to Wednesday 23:00 and release: the block is drawn in two halves", never
"create a block".

---

## 10. Intentional behaviours — DO NOT report as a bug

**Numbered.** Everything here looks like a defect and is a decision. A tester who thinks one is
really wrong cites its number in the report. Mine them from the `RULES.md` files — the "Intentional
Decisions" of every unit in scope are most of this list already.

1. _e.g. The collapsed rail stays in the page, hidden and inert, instead of being removed — it is
   what stops the page jumping sideways._
2. _e.g. A modal closes on its own buttons and its X, never on a click outside — it holds a
   half-filled form._

## 11. Fixes to verify

**Each already-fixed problem becomes a reproduction item, with the commit that fixed it.** The
tester does not investigate: reproduce the step, confirm the old behaviour is gone, tick. If it
still happens it is a regression — report it with the hash, which is where the fix lives.

- [ ] **11.1 · _What was wrong, stated as what must now be true._** `<commit>`. _The exact steps._
- [ ] **11.2 · _An absence counts too_** (the switch that was removed, the destructive button that
      left the row) — mark it as an absence.

Where a fix touches authentication or authorisation, the item includes **the abuse case**, not only
the happy path: the redirect must REFUSE a destination on another host; the rate limit must refuse
the sixth attempt even when the password is finally right.

## 12. How to report

A bug without steps is not a bug, it is a feeling. Every report carries:

1. **Title** — one sentence.
2. **Steps to reproduce** — numbered, from sign-in on, with concrete values.
3. **Expected**
4. **Actual**
5. **Browser and version**
6. **Viewport** — width in pixels, or device model and orientation.
7. **Colour scheme**
8. **Language**
9. **Account used**
10. **Evidence** — a screenshot; for anything with dragging, swiping or animation, a short video.
11. **Console** — the red error text, if there was one at the moment of failure.

Also mark: **reproducible?** always / sometimes / once · **blocks use?** yes / no. If the item falls
under a line of section 10, say which and why it is still wrong. If it is in an area section 0 cut,
report it anyway and say so — a screen that broke after adaptation is exactly what the cut can hide.
