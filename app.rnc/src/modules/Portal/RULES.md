---
version: 1.0.0
origin: rnc
---

# RULES — Portal Module

> For AI agents. rnc-2 F1, 07/11/2026.

## Domain

The partner's portal (rnc-2 R3–R10): `/parceiro` (route `portal`, `/partner` its `en` twin), inside
Franco's own shell. F1 ships the landing that names the partner (`GET portal/me`); the inbox, the
SLA and the actions arrive in F3, the statement in F4. API: the `portal/*` routes of api `Partner`
(`permission:portal.access` + `portal`).

## Public Barrel

`routes` (`PortalModuleRoutes`), `constants` (`PORTAL_ROUTE_NAMES`, `PORTAL_PERMISSIONS`,
`PORTAL_ROLE_SLUG`, `STAFF_HOME_PERMISSION`), `composables` (`usePortalLanding`), `services`
(`GetPortalMeService`), types.

## Intentional Decisions

- **One SPA, one shell** (D2): the portal renders in `TheLayout`, the same shell as the back
  office. What makes it the partner's is the rail — `navItems.local.ts` gates the portal entry on
  `portal.access` AND the role `parceiro`, and a partner holds no key of the staff groups — not a
  third layout. The core Home entry stays in the rail and leads a partner here (Home redirects).
- **Who belongs here is `usePortalLanding`**: `portal.access` and no `home.read`. The operator holds
  every key and stays in the back office; Home reads it to bounce a partner, which covers every
  refused back-office route because the guard falls them back to Home.
- **The API decides who the partner is** (`GET portal/me`, from the session's `partner_id`), never a
  route param: there is no partner id in any portal URL.
- **Willy/APW never appear** (`src/test/noPartnerNetworkNames.spec.ts` names this module).

## Known Gaps

- **The landing is a placeholder** (F1): it names the partner and the firm; the inbox is F3.
