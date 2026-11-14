---
version: 1.1.2
origin: rnc
---
# RULES — Platform Module

> For AI agents. rnc-4 F4a, F4c, 14/11/2026.

## Domain

The platform operator's console (rnc-4 R24, R25, D19): Battoni Dev runs every firm of the hub from
here. The operator is the `operador` role with **no firm** (`battoni`, `SuperAdminSeeder`); it signs
in at the root only and, outside view-as, every other request of theirs works in the platform
firm (`hub`, plan AM1). The console is how it reaches the other firms: list them, create one, edit
its look and upload its logo, set its modules, create or reset its manager's login, edit the
platform's default consent sentence, and enter one firm through "Acessar como suporte" (view-as,
R26, D20), audited.

## Who reaches it

Every route carries `auth`, its key and `platform.operator` (`EnsurePlatformOperator`), in that
order — a guest 401, anybody else 403:

- `platform.firms` / `platform.consent` are declared in Auth's `permission-groups.local.php`; the
  operator holds them (it holds every key), and `RoleService` refuses them to any other role (422
  `rolesAndPermissions.operatorOnlyKey`).
- `platform.operator` asks for the person, not a key: role `operador` AND `firm_id` null (403
  `platform.operatorOnly`). A key that leaks onto a firm account — a per-user override, a role
  edited in the database, an `operador` placed in a firm — opens nothing.
- `ResolveCurrentFirm` still runs first: the operator sending `X-Firm` is 403 `firms.mismatch`, so the
  console works at the root only.

| Route | Key | Answer |
| --- | --- | --- |
| `GET platform/firms` | `platform.firms` | every firm (`PlatformFirmResource`), the platform firm first, then by name |
| `POST platform/firms` | `platform.firms` | 201 `{firm, manager, password}` — `FirmProvisioner::provision` |
| `PATCH platform/firms/{id}` | `platform.firms` | the firm — `FirmLookService` |
| `PUT platform/firms/{id}/modules` | `platform.firms` | the firm — `FirmModulesService::set` (the whole list) |
| `POST platform/firms/{id}/manager` | `platform.firms` | 201 new / 200 reset, `{manager, password, created}` — `FirmProvisioner::provideManager` |
| `GET platform/consent-default` | `platform.consent` | `{text}` |
| `PUT platform/consent-default` | `platform.consent` | `{text}` — `PlatformSettings::updateConsentDefault` |
| `POST platform/firms/{id}/logo` | `platform.firms` | the firm with `logo_url` — `UploadLogoRequest`, Firm's `FirmLogoService::replace` |
| `DELETE platform/firms/{id}/logo` | `platform.firms` | the firm, `logo_url` null — `FirmLogoService::remove` (200 even with no logo) |
| `POST platform/firms/{id}/view-as` | `platform.firms` | 201 the open session — `ViewAsService::start`; 404 / 409 `platform.viewAsActive` / 422 `platform.viewAsPlatformFirm` |
| `DELETE platform/view-as` | `platform.firms` | 200 the session closed as `sair`, or `null` when none was active |
| `GET platform/view-as-log` | `platform.firms` | paginated (25), latest first — `ViewAsSessionResource` |

## Rules

- **It writes no firm itself.** Firms, their look, logo file, modules and manager logins are the Firm
  module's writers (`Firm/RULES.md` → "The platform console's writers"); users are Auth's, through
  them. The module owns ONE table, its own audit: `platform_view_as_sessions` (F4c).
- **A logo is judged by its bytes** (R27, D22; `UploadLogoRequest`, `Rules/LogoImage`): `finfo` must
  read PNG, JPEG or WebP and `getimagesize` must parse the same type — an SVG, an SVG or HTML page
  renamed `.png`, a GIF, a PNG signature over junk are `platform.logoType`, whatever the name or the
  client's MIME; then Laravel's sniffed `mimetypes`, `max:512` (`platform.logoTooLarge`) and
  `dimensions` ≤ 1024 px (`platform.logoTooWide`); no file `platform.logoRequired`. Stored by
  `FirmLogoService` as `firms/<id>/logo-<40 random>.<sniffed ext>` on `public`; a new upload deletes
  the file it replaced (only a file under that firm's folder is ever deleted). Not re-encoded (D22).
- **View-as** (R26, D20; `ViewAsService`): the session holds only the id of an open
  `platform_view_as_sessions` row (`ViewAsService::SESSION_KEY`); the row (operator, firm, IP,
  started, ended, `end_reason`) is the truth. `current()` honours the key only for the firm-less
  `operador` whose row it is, while the row is open and its firm is not the platform firm — a firm
  account, an operator placed in a firm, another operator or a guest carrying the key rides nothing.
  Firm's `ViewAsFirm` asks it, so `ResolveCurrentFirm` makes every request of that session the
  viewed firm's and `auth/me` answers `platform.view_as`. It never nests (409), never targets the
  platform firm (422). It ends on `DELETE platform/view-as` (`sair`), on `auth/logout` (`logout`,
  Auth's `AuthController`), and at the person's next sign-in, any door (`expirou`,
  `Listeners/CloseViewAsOnSignIn` on Laravel's `Login`, registered in `AppServiceProvider`) — which
  also stops another browser's open view-as. It changes the firm, never the permissions: the
  operator keeps its own keys (read and write, D20) and the viewed firm's modules still gate routes.
  An `X-Firm` header stays 403 `firms.mismatch` (W9): view-as works at the root only.
- **The firm is the one the route names, by id.** `PlatformFirmRepository::findFirmById` — a firm is
  not firm-scoped; an unknown id is 404 `firms.notFound`. The request's own firm (the platform firm)
  plays no part in any of these routes.
- **`FirmScope` is lifted only in `PlatformFirmRepository`**, for the lead counts: one grouped query
  for the list (`countLeadsByFirm`), one count for a single firm. Counts only — no row of another
  firm leaves the class.
- **A password is answered once** (`ProvisionedFirmResource`, `ManagerLoginResource`) and stored
  only as a hash. A reset ends the account's sessions.
- **The look request** (`UpdatePlatformFirmRequest`) uses a sub-brand's limits (`UpdateBrandRequest`
  constants), colours lower-cased, `ReadableUnderWhite` on the primary (`brand.lowContrast`, R11),
  the proof exactly four points or null. The slug, `is_platform` and `modules` are not fields: the DTO
  drops them.
- **A new firm's refusals are the provisioner's** (`firms.slug*`, `firms.nameRequired`,
  `users.form.*`); the request checks presence and maps a missing field to the same keys.
- **The default consent must name the firm** (`{escritorio}`, `ConsentTerms::PLACEHOLDER`), else 422
  `platform.consentPlaceholderMissing`; ≤ 1000 characters. It changes the NEXT firm's version 1 only.

## Tables read from other modules

- `firms` (Firm) — the list, the lookup by id, the viewed firm.
- `leads` (Lead) — counted per firm, across firms.
- `users`, `roles` (Auth) — through `FirmProvisioner` and `UserService`, never queried here.

## Intentional Decisions

- **Two locks, not one** (W53): the permission alone would let a per-user override or a hand-edited
  role into the console; the role check alone would make the key meaningless on the roles screen.
- **Create or reset in one route** (W54): the console asks "the manager's login" once; a username of
  the firm's manager resets, a free one creates, anything else is refused rather than moved or
  promoted.
- **The default consent requires `{escritorio}`** (W55): a consent that names no firm authorises
  nobody, and the firm's name is filled in per firm and sub-brand (C5).
- **The session holds a row id, not a firm id** (F4c): closing the row ends the view-as wherever the
  session lives, and a key that leaks into another person's session points at a row that is not
  theirs.
- **View-as and the logo ride `platform.firms`** (W84): no key of their own — the second lock (the
  firm-less operator) is what keeps them the operator's, as for the rest of the console.
- **"Sair" with nothing active answers 200 `null`** (W85), not 404: a second click, or a view-as a
  sign-in elsewhere already closed, is not an error for the banner.

## Known Gaps

- **No per-action audit inside view-as** (D20, out of scope): only the start and the end are logged.
_Retired 14/11/2026 (F7):_ "The logged IP is the direct peer: the app trusts no proxy yet" — `config/trustedproxy.php`
reads `TRUSTED_PROXIES` (Laravel's global `TrustProxies` consults it), so behind the listed proxies `$request->ip()` is
the client from `X-Forwarded-For`; an unlisted peer's header is ignored (`ViewAsTest`, "the audit address behind a
proxy"). The value per server is in `docs/deploy/runbook.md`. The production vhost serving `/storage` per host
(AM10) is in the same runbook.
_Retired 14/11/2026 (F4d):_ "`/storage` is outside `/api`; F4d decides how the SPA shows it locally" —
the SPA asks `/storage/…` on its own host (app `toStorageAssetPath`) and Vite proxies `/storage` beside
`/api` (local and test mode), so the logo loads on every firm host; `php artisan storage:link` is in
the feature's local environment. `logo_url` stays `APP_URL/storage/…`. The production vhost serving
`/storage` per host is still F7's runbook (plan AM10).
_Retired 14/11/2026 (F4c):_ "Until F4c the operator reaches other firms only through the console" —
view-as now lets the operator enter one firm, audited; a firm's pipeline, partners, settings and
sub-brands (`brands*`) are reached that way.
_Retired 14/11/2026 (F4c):_ "No logo upload yet" — `POST/DELETE platform/firms/{id}/logo`.
_Retired 14/11/2026 (F4b):_ "No SPA yet: the refusal keys have no locale text" — the console's
screens (app `Platform` module) name every refusal by its code (`PLATFORM_ERROR_KEYS` → `platform.errors.*`).
