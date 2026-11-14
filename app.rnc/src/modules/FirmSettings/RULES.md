---
version: 1.0.0
origin: rnc
---

# RULES — FirmSettings Module

> For AI agents. rnc-4 F5b, 14/11/2026.

The firm's own settings for its manager (rnc-4 R30–R33, D25) over the API of F5a, under "Gestão":
`/gestao/escritorio` (`/management/firm`, contact data, credentials line and business hours),
`/gestao/equipe` (`/management/team`, team logins), `/gestao/especialistas` (`/management/specialists`,
weekly hours) and `/gestao/consentimento` (`/management/consent`, consent versions). Every call works on
the signed-in person's firm (`CurrentFirm` on the API) — no route takes a firm, and no screen sends one.
A core surface: no `meta.module`. The look and the modules are the platform operator's (`@PlatformModule`).

## Intentional Decisions

- **Keys**: the rail shows the four entries behind `firm.settings`; the team route asks `users.read` like
  its API, the other three `firm.settings`. Inside the team screen, `users.create|update|archive|reset_password`
  each hide their own button (W77: the manager has no `users.delete`, so there is no delete here).
- **The team reuses the User module where the shapes match** — `GetUsersService` (paginated, scoped to the firm
  by the API since F1a) and `ArchiveUserService` (the deactivate/reactivate toggle) — and has its own create and
  update services, because its payloads are not the bootstrap form's: `is_specialist`, no permission overrides,
  and the role only ever `equipe` or `gestao` (`FIRM_TEAM_ROLE_SLUGS`, read from `auth/roles` by slug, W76). The
  API refuses any other role (`users.form.roleNotAssignable`), shown on the role field.
- **A password is drawn in the browser and shown once** (`useOneTimePassword`, W81): `auth/users` takes the
  password from the caller and has no generate route, so a new login and a reset each get 16 letters and digits
  from `crypto.getRandomValues`, sent once and shown with `MOneTimePassword` (`@PlatformModule`). Nothing keeps it
  after the dialog closes. The reset sends the password alone.
- **The specialist flag is the team screen's; the hours are the specialists screen's.** A newly flagged person
  starts on the firm's hours the first time (C17); a person switched off leaves the hours list and keeps its week.
- **Hours are validated before sending** (a window needs a weekday and closes after it opens) — the API's 422 on
  `business_hours.N.*` / `hours.N.*` lands on the same window.
- **Consent**: the newest version is the current one; the preview fills `{escritorio}` with the firm's full name
  (as W14 fills version 1). A text without the placeholder, or identical to the current one, is refused before
  sending; the API's `leads.consentPlaceholderMissing` is the backstop. A save prepends version n+1 — no refetch.
- **Refusals by key** (`useApiRefusal`): a 422 field's first code goes through `translateError`; the codes these
  screens meet have their own locale keys.

## Known Gaps

- **No live browser check in this phase** (W82): the Chrome rounds are verify's (plan.md → QA round 3).
