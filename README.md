# rnc

A monorepo containing design systems, code conventions, client bootstrap projects, and AI tooling for battoni.dev.

<div align="center">
  <img src="./cortex/logo/png/logo-full-light.png" alt="Cortex Logo" width="150" height="150" style="margin: 0 10px;" />
  <img src="./codelumen/public/logo/logo-light-full.png" alt="CodeLumen Logo" width="150" height="150" style="margin: 0 10px;" />
  <img src="./liquen/logo/liquen-logo-full-light.png" alt="Liquen Logo" width="150" height="150" style="margin: 0 10px;" />
</div>

<div align="center" style="margin-top: 10px">
  <img src="./app.rnc/src/assets/logo/logo-full-light.png" alt="app.rnc Logo" width="150" height="150" style="margin: 0 10px;" />
  <img src="./api.rnc/logo/png/logo-arcus-full-light.png" alt="Arcus Logo" width="150" height="150" style="margin: 0 10px;" />
  <img src="./vitrum/src/assets/logo/png/logo-vitrum-full-light.png" alt="Vitrum Logo" width="150" height="150" style="margin: 0 10px;" />
</div>

## Projects

### Arcus

**Laravel API bootstrap** — starting point for backend APIs.

- Laravel 13, PHP 8.4, DDD-style module structure
- RESTful API with Spatie Data DTOs, Eloquent Resources, role-based permissions
- AI-ready: rules, skills, and commands configured at monorepo root

[View Project](./api.rnc/)

---

### app.rnc

**Vue 3 client bootstrap** — starting point for web applications.

- Vue 3.5 + TypeScript, PrimeVue, Tailwind CSS, Pinia, Vue Router, Vue I18n
- ESLint + Prettier auto-fix on save
- Single-file theme system: change `src/styles/theme/colors.css` to retheme the entire project — semantic palette names (`primary`, `surface`, `ink`) propagate to PrimeVue tokens and Tailwind utilities automatically
- AI-ready: rules and commands configured at monorepo root

[View Project](./app.rnc/)

---

### Vitrum

**Astro bootstrap** — starting point for public, institutional and marketing websites.

- Astro, TypeScript, Tailwind CSS
- Static-first, SEO-ready, lightweight

[View Project](./vitrum/)

---

### Cortex

**AI knowledge layer** — history, documentation, and prompts for rnc's AI infrastructure.

- All rules live at the monorepo root (`.claude/rules/`, `.cursor/rules/`) and activate automatically by file path
- Commands: `/battoni-setup-project`, `/battoni-start-session`, `/battoni-review-vue-conventions`, `/battoni-review-arcus-code`, `/battoni-review-design-conventions`, `/battoni-generate-component-rules`
- Skills, agents, and hooks for Claude Code; full rule parity for Cursor
- `docs-portal` skill — generates a dual-audience (User/Developer) VitePress documentation portal; method and templates live in `cortex/docs-portal/`
- `landing-page` skill — builds or reviews high-performance Astro landing pages against the seven-pillar recipe (`codelumen/landing-page-recipe.md`); template and deterministic audit live in `public.battoni.dev/`

[View AI Docs](./cortex/)

---

### CodeLumen

**Code conventions documentation** — VitePress site for battoni.dev standards.

- Comprehensive coding standards, Atomic Design, Domain-Driven Design

[View Documentation](./codelumen/)

---

### Liquen

**Design tokens and Figma integration** — design consistency across projects.

- Design token management with Tokens Studio
- Figma variable sync and theme customization

[View Project](./liquen/)

---

### docs.rnc

**Documentation portal** — dual-audience (User/Developer) VitePress site documenting this monorepo, built from the `docs-portal` skill.

- User/Developer depth switcher, codebase-driven extractors (endpoints, schema, views), fail-closed staff gate
- Run with `make portal` (see the `portal-*` targets in the Makefile)
- A worked example of the `docs-portal` skill applied to rnc itself

[View Portal](./docs.rnc/)

---

## License

Private project — All rights reserved.
