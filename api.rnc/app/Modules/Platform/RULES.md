---
version: 1.0.1
origin: rnc
---
# RULES — Platform Module

> For AI agents. rnc-4 F4a, 14/11/2026.

## Domain

The platform operator's console (rnc-4 R24, R25, D19): Battoni Dev runs every firm of the hub from
here. The operator is the `operador` role with **no firm** (`battoni`, `SuperAdminSeeder`); it signs
in at the root only and, outside view-as (F4c), every other request of theirs works in the platform
firm (`hub`, plan AM1). The console is how it reaches the other firms: list them, create one, edit
its look, set its modules, create or reset its manager's login, and edit the platform's default
consent sentence.

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

## Rules

- **It writes nothing itself.** Firms, their look, modules and manager logins are the Firm module's
  writers (`Firm/RULES.md` → "The platform console's writers"); users are Auth's, through them. The
  module owns no table.
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

- `firms` (Firm) — the list and the lookup by id.
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

## Known Gaps

- **Until F4c the operator reaches other firms only through the console** (rnc-4 AM1): outside the
  console every request of a firm-less operator works in the platform firm; there is no view-as yet,
  so the operator cannot open a firm's pipeline, partners or settings, and a firm's sub-brands
  (`brands*`) are not editable from here until view-as.
- **No logo upload yet** (R27, F4c): the look is text and colours; `logo_path` is not a field.
_Retired 14/11/2026 (F4b):_ "No SPA yet: the refusal keys have no locale text" — the console's
screens (app `Platform` module) name every refusal by its code (`PLATFORM_ERROR_KEYS` → `platform.errors.*`).
