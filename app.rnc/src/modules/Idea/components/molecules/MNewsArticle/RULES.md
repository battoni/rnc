---
version: 1.3.4
origin: pendulum
based-on: 1.3.4
---

# RULES — MNewsArticle

> For AI agents. Last updated: 2026-09-17.

One entry, read in full, inside a dialog opened from its card.

|       |                               |
| ----- | ----------------------------- |
| Props | `entry`, `reactionsDisabled?` |
| Emits | —                             |

## `v-html` is correct here, and only here

The body is HTML and is rendered with `v-html`. That is safe because it was sanitised **on the way
in** by `NewsBodySanitizer`, server-side, against an allow-list — not because anything on this side
checked it.

Two things follow. **Do not add a client-side sanitiser** to feel better about it: it would be a
second, weaker allow-list that disagrees with the first. And **do not reuse this pattern** for any
field that is not run through that sanitiser on write.

**Links open in a new tab, through `withExternalLinks` (`@Helpers`).** The sanitiser strips
`target` and `rel`, and the helper puts them back as a pair at render time — the same one `ATip`
uses. It replaced a `querySelectorAll` pass run after mount: same result, but a second mechanism
for one rule, and one that only held while the watcher and the template ref stayed in step.
Covered by `MNewsArticle.spec.ts`.

## One `h1` per page

The entry's title is an `h2`. `ThePageHeader` already renders the page's only `h1`, and this
article opens inside a dialog on top of it — a second `h1` gives a screen reader two page titles.

## A dead cover costs the image box and nothing else

`alt` is EMPTY and the box is dropped on `error`, for the same reason as `MNewsCard`: a 404'd cover
used to render the entry's title as bare text above the article, a second headline in the wrong
typeface. The headline follows two elements later.

## Typography — this is prose, not interface

The body is set at `text-base leading-relaxed`, and that is a decision, not a leftover. It shipped
at `text-sm`: the size of a form label, on the one surface in the app that holds running text
somebody is expected to read to the end.

**The body's own `h1`–`h3` are sized as a RATIO of the body**, in the scoped block below the
template. They were `1.05rem` against a `0.875rem` body; when the body grew they had to grow with
it, or a heading would have been told apart from a paragraph by its weight alone. Change one and
check the other.

The rest of the body's typography — lists, links, blockquotes, `pre` — also lives in that scoped
block, keyed on `.news-article-body`, and it is deliberately NOT `src/styles/components/editor.css`.
That file pulls Quill's own chrome onto the project's tokens for the WRITING side (`.p-editor`);
nothing in it targets `.news-article-body`.

**Links are `--color-heading` with an underline**, the entry link and the body's alike. They were
`primary-700`, which measured 1.93:1 on the light canvas: the primary ramp does not flip, and a
link is text somebody has to read.
