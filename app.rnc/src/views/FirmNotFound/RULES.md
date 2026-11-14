---
version: 1.0.0
origin: rnc
---

# RULES — FirmNotFoundView

> For AI agents. rnc-4 F1b, 13/11/2026.

## Purpose

What a subdomain the API does not know renders (rnc-4 R3): the product mark, "Escritório não
encontrado", what to check, and a link to the root. Shown by `TheRouteGuard` in place of any path when
`usePublicFirm().isFirmMissing` is true (the boot `GET public/firm` answered 404 `firms.notFound`).

## Intentional Decisions

- **Not a route.** The guard swaps it in, so the address stays what was typed and every path — site,
  login, back office — answers the same. It has no `routes.ts`.
- **The way out is a link (`Button as="a"`), not a router push**: the root is another host
  (`rootSiteUrl(window.location, ROOT_DOMAIN)`, same scheme and port).
- **`TheCenteredLayout`, one root node**, the comment inside it — the same shell and the same rule as
  `NotFoundView`.

## Do Not

- **Do not push a route to reach the root** — the router cannot leave the host.
- **Do not decide here which slugs exist** — the API's 404 is the only answer.
