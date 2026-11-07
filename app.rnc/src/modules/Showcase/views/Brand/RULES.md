---
version: 1.1.2
origin: pendulum
based-on: 1.1.2
---

# RULES — BrandView

> For AI agents.

## Purpose

Living reference for the battoni-dev brand identity: the theme's atmosphere, the monogram
and signature atoms (`ABrandMark`, `ABrandSignature`, `ABrandPattern`), the empty-state
treatment (`AEmptyState`), and the showcase chrome (corner brackets, glow, monospaced voice)
that the active theme layers onto app.rnc.

## Intentional Decisions

- **Only visible with `design.read`**: the route declares `meta.permission` and the global auth guard enforces it. Not a role check — a role cannot be granted from the permissions tab.
- **No API calls**: all data is static. This is a visual test bed, not a feature view.
- **Every word a person reads is an i18n key** under `showcase.brand.*`, in `en` and `pt-BR`,
  `title` included. This replaces "`title` passed as a raw string — internal tooling, not
  user-facing copy": `ThePageHeader` runs `$t()` on it, so the English only showed because
  vue-i18n echoes a missing key, and a gallery that documents a bilingual design system has to
  render in the language it documents. What stays literal is what is not language: token names,
  hex values, font names, the code glyphs and `>_ app.rnc × battoni.dev`.
- **A sentence with an element inside it uses `<i18n-t>`** with a named slot (the glossary
  line), never a sentence split into before/after keys — word order differs between languages.
- **Each section is a `<section>` with a heading**: one section per brand facet. Add a section
  when a new brand atom or theme effect is introduced.

## Do Not

- **Do not remove this view** — it is the reference for the brand layer.
- **Do not add business logic** — purely a visual test bed.
- **Do not import the brand atoms** — they are auto-imported.
