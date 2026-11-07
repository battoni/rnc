# <Product> — Product Memory (working source of truth)

> **Status:** living document, built from `<sources>` on `<YYYY-MM-DD>`.
> **Purpose:** hold everything known about the product so the open decisions can be closed with the
> client, and scope can be planned against decisions rather than impressions.
> **Most of this is not validated yet.** Items marked **⚠️ OPEN #n** are unresolved. Items in the
> Decisions Log are decided — by whom is written on each one.

Why this document exists: the material a client hands over is usually several restart attempts that
disagree with each other. Building from any one of them builds the wrong product; building from
memory of a meeting builds the one you imagined. A single document where every claim carries its
source and every decision carries its owner is what lets a phase spec cite a decision instead of
re-arguing it.

---

## 0. Decisions Log

One entry per decision, newest last, never rewritten — a reversed decision is a NEW entry that names
the one it supersedes.

**D1 — <the decision in one line> (resolves OPEN #n).**
- Decided by <person>, <YYYY-MM-DD>, in <grill round / meeting / message>.
- What it means for the build: <consequences, as bullets>.
- Still open inside it: <sub-question, or "nothing">.

> Decisions are fed by `/battoni-grill-me`: each round ends with the decisions it closed, and each
> becomes the next `Dn` here, closing its `⚠️ OPEN` item below.

## 1. What the product is

<Two paragraphs. Who uses it, what it replaces, what it is NOT.>

## 2. Sources ingested — and how much to trust each

| Source | What it is | Trust / completeness |
| --- | --- | --- |
| `<file>` | <what it describes> | <most complete / dated / contradicts X> |

Where sources conflict, say which one wins and why — usually the most recent, never silently.

## 3. Entities and user types

<The union across sources first, marked for pruning; the canonical set once decided.>

## 4. The core object

<The one thing the product is about: fields, lifecycle, who acts on it at each state.>

## 5. Flows

<Registration, the main loop, the admin side — each with its source and its open questions.>

## 6. Money

<Who pays whom, when money moves, what the platform holds. The section most likely to be fog.>

## 7. Consolidated ⚠️ OPEN checklist — the grilling agenda

**A. <area>**
- ⚠️ #1 <question> — V1 or later?
- ✅ #2 <question> — DECIDED (D3).

## 8. How this maps to the bootstrap

<What the bootstrap already covers, what is greenfield — for planning, not for the client.>

## 9. Legacy findings

<What an earlier build attempt got right and can be reused; what it got wrong.>
