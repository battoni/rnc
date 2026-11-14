---
version: 2.18.1
origin: pendulum
based-on: 2.9.0
---
# RULES — Auth Module

> For AI agents. Captures Auth-specific decisions not covered by `arcus-api-architecture` or the laravel skill.

## Local extension points

- `Helpers/permission-groups.local.php` — groups a project adds (appended to the core ones). LOCAL:
  the sync never touches it. In rnc it holds the hub's keys (leads, agenda, notifications,
  partners, referrals, metrics, repricing), Phase 2's (portal, ledger, fiscal, radar) and Phase 3's
  seven (rnc-3 F1: brands, channel, assistant, contracts, briefing) — declared before the screens
  that use them, held by the operator alone until their phase grants them.

*Retired 07/11/2026 (rnc-1 F1):* `manager-exclusions.local.php`, `user-exclusions.local.php` and
`ManagerSeeder`. They shaped the bootstrap's `manager` and `user` roles, which this product does not
have: `RoleSeeder` writes the three hub roles (`operador`, `gestao`, `equipe`) key by key instead, so
there is no "everything minus a list" left to subtract from. The protected all-keys role
(`RoleService::ADMINISTRATOR_SLUG`) is `operador`.

## Accounts another module provisions (rnc-2 F1, LOCAL)

- **`Services/LinkedAccountService.php`** opens, re-passwords and closes an account for another
  module's record — a partner's portal login (`users.partner_id`, role `parceiro`). The Partner
  module decides when; the `users` and `sessions` writes happen here, because Auth owns them.
- **A password it generates is returned once** (to be shown once) and stored only as the `hashed`
  cast's hash — so it saves through `UserRepository::saveUser`, never the query-level `updateUser`.
- **Changing the password or closing the account deletes the user's `sessions` rows**: with
  `SESSION_DRIVER=database` a cookie handed out before finds no session and gets a 401 (plan AM5).
  Without that, a revoked partner kept a working cookie until it expired.

## One firm per account (rnc-4 F1a, LOCAL)

- **An account signs in only on its own firm's host** (R4, D5). `AuthService` checks
  `FirmResolver::belongsTo` in the SAME condition as the password, after the hash, so another
  firm's account gets `auth.login.invalidCredentials` with the timing unchanged — and a deactivated
  one of another firm is never told it is deactivated. The phone code looks the phone up inside the
  firm (`findSoleUserByPhone($phone, $firm)`): no code is sent for another firm's phone and spending
  one answers `invalid_code`. The firm-less `operador` belongs only to the platform firm (the root);
  any other firm-less account belongs nowhere. `QaLoginAsController` applies the same rule (AM8).
- **Every user read a screen or a public door makes is scoped by the current firm** (AM2):
  `findAllUsers`, `findAllActiveUsers` (support names), `findSoleUserByPhone` and `findFirmUserById`
  (every `auth/users/{id}` route; another firm's id is a 404). `findUserById` stays global for the
  signed-in person's own row and the outrank check. `UserService::create` stamps the current firm.
  Username and e-mail uniqueness stay global.
- **Self-registration is closed** (R7, D21): `registration.enabled` (`REGISTRATION_ENABLED`,
  default false) and `EnsureRegistrationOpen` answer 404 on the three routes; they stay registered
  so the route matrix keeps them and a test can open the door with a config change.

- **`GET auth/me` boots the shell** (rnc-4 F3a, R20): `UserResource::withPlatform($viewAs)` adds
  `firm` (Firm's `FirmSummaryResource`, null for a firm-less account) and `platform: {name,
  is_operator, view_as}` — `is_operator` is the `operador` role, `view_as` Firm's `ViewAsFirm`
  (`{slug, name, modules}` of the operator's open view-as — `modules` the viewed firm's opt-in list, since `firm` stays null for the operator (F7, W93) — else null; F4c). A user in a list does not carry
  them; the login answers carry them with `view_as` null — a sign-in closes any view-as left open.
- **Logout ends a view-as** (rnc-4 F4c, R26, D20): `AuthController::logout` closes the session's
  open view-as as `logout` (Platform's `ViewAsService::end`) before the session is invalidated.

## The firm's manager runs its team (rnc-4 F5a, R31, AM2, LOCAL)

- `gestao` holds `users.read|create|update|archive|reset_password` and `firm.settings`
  (`RoleSeeder::GESTAO_FIRM_KEYS`); never `users.delete` nor `permissions.*` (the roles screen).
- **A firm member gives team roles only** (`Rules\TeamRole`, on create and update): `operador` and
  `parceiro` are refused (`users.form.roleNotAssignable`) to anyone but the firm-less platform
  operator, even holding every key of the role — beside `AssignableRole`'s key cap. `firm_id` and
  `partner_id` have no rule, so they never reach the service: a new account is stamped with the
  current firm (C3) and partner logins are the partner screen's.
- `is_specialist` is accepted on create and update; newly true makes the person bookable through
  Booking's `SpecialistService::startOnFirmHours` (this module calls it; it never writes Booking's
  tables itself).
- Reaching another firm's account or the firm-less operator is 404 (F1a's scoped reads,
  `findFirmUserById`). Tested by `tests/Feature/Auth/FirmUsersTest`.

## Domain

Authentication, authorization, and the user/role/permission graph. Owns: `User` session
auth (Sanctum), `Role`, `RolePermission`, `UserPermission`, and the effective-permission
resolution that merges role + per-user grants.

## What belongs here

- Login / credential verification (`AuthService`)
- User CRUD (`UserController` → `UserService` → `UserRepository`)
- Role CRUD + permission syncing (`RoleController`, `RoleRepository`)
- Permission resolution (`PermissionService`, `EffectivePermissionsHelper`)

## What does NOT belong here

- The shared `User` Eloquent model lives in `app/Models/User.php` (app-wide), NOT in the
  module — only `Role`, `RolePermission`, `UserPermission` are module-local models. This is
  intentional: `User` is referenced across the whole app.
- The `ApiResponse` envelope is a shared helper (`app/Helpers/`), not module-owned.

## Permission model (non-obvious)

A user's effective permissions = **role permissions ∪ direct user permissions**, deduped.
Resolved by `User::effectivePermissions()` (see `app/Models/RULES.md`). Roles hold
permissions as rows in `role_permissions` (name strings), not a pivot to a permissions
table — there is no standalone `permissions` table. Same for `user_permissions`.

## Account status (non-obvious)

`UserStatus` has two cases and INACTIVE means **deactivated** — by an administrator, or parked
for approval by `registration.new_user_status`. Three rules follow from it:

- **Deactivation takes effect on the next request.** `EnsureAccountActive` (api group) signs the
  account out with a 401. Not a 403: the SPA keeps its session on a 403, so the person would sit
  inside an app that refuses everything.
- **Login with the right password names the deactivation.** `AuthService` answers
  `auth.accountInactive` (speak to an administrator) only after `Hash::check` succeeds. Wrong
  password or unknown username still share `auth.login.invalidCredentials` — revealing inactive
  without a password would enumerate accounts by username alone. The old "one sentence for all
  three" rule was retired for that reason.
- **An administrator cannot deactivate their own account** — they could not sign back in to undo
  it. `UserService::update` and `::archive` refuse with `users.form.cannotDeactivateSelf`.
- **A parked registration is not signed in.** The account is created and returned; only an
  ACTIVE account gets a session.

## Routes: a permission per verb

Every route names the permission for its own verb, never one for the resource. `auth/roles`
reads with `permissions.read` **or** the user form's `users.create` / `users.update`
(`permission:a,b` means any of them), and writes with `permissions.update`. The ordinary member
role never holds `permissions.*` — whoever can edit roles can grant themselves anything.

## Writing permissions (non-obvious)

- **Only declared keys are stored.** A role's matrix and a person's overrides both pass through
  `Rules/DeclaredPermissionKeys`: a key no group in `PermissionGroupsHelper` declares is a 422
  (`rolesAndPermissions.unknownPermission`), never a row granted in advance to a route that adopts
  the name later.
- **A password sent to `auth/users/{id}` needs `users.reset_password`** on top of `users.update`,
  checked in the controller because it depends on the body (403,
  `users.form.passwordResetPermissionRequired`). The screen hiding the field was never the guard.
- **A role write checks the role exists before touching the matrix** (404), and a role's row and
  matrix — like a user's row and overrides — land in one transaction.
- **The administrator role (`RoleService::ADMINISTRATOR_SLUG`) and a role somebody holds are not
  deleted.** The rule lives in `RoleService::delete`; `RoleDeletionException` renders 403 / 409 with
  an i18n key and, for 409, the holders' names.
- **`auth/users/check-username` belongs to the user form's audience** (`users.create` or
  `users.update`). A member asking whether a username exists is enumerating accounts.

## Granting is capped by what the actor holds (CRITICAL)

- **Nobody hands out a key they do not hold**, and nobody assigns a role that grants more than they
  hold (`GrantableByActor`, `AssignableRole`, both on the user requests; the first also on
  `auth/roles/{id}/permissions`). Editing accounts and deciding what accounts may do are different
  jobs, and `users.update` must not be a way around `permissions.update` (ARC-03).
- **The administrator's matrix is fixed: every declared key, always** — the same rule that keeps
  the role from being deleted. A revoke there is refused for everybody, granting to it is exempt
  from the cap, and a save writes the whole catalogue back (`RoleService::updatePermissions`). The
  cap needs a root that holds everything; a key that left the root could never come back.
- **The platform keys are the administrator's alone** (`platform.*`, rnc-4 F4a, R24): granting one
  to any other role is 422 `rolesAndPermissions.operatorOnlyKey` (`RoleService::refuseOperatorOnlyKeys`);
  revoking passes. The console's routes also ask for the firm-less `operador` itself
  (`EnsurePlatformOperator`), so a per-user override of the key opens nothing either.
- **Nobody edits an account that holds more than they do** (`UserService::refuseIfOutranked`, on
  update, archive and delete): a target holding any key the actor lacks is refused with
  `users.cannotManageHigherAccount`. Equal or less is fine; your own row follows the self rules.
- **Only grants are capped.** Setting a key to `false` is not an escalation and stays open.
- The refusals are 422s naming `rolesAndPermissions.cannotGrantUnheldPermission` and
  `rolesAndPermissions.cannotAssignUnheldRole`.

## E-mail verification code (non-obvious)

- **A second registration for an address already waiting does not rewrite it** — it only re-issues
  the code to that address (`RegistrationService::register`). Overwriting let a stranger register
  somebody else's e-mail with a password of their own: the code arrived in the victim's inbox and
  created an account the stranger could sign into. The answer is the same 201 either way, because a
  different one would tell a stranger the address is registering here.

- **The fifth wrong guess burns the code** (`EmailVerificationService::MAX_ATTEMPTS`, counted on
  `pending_registrations.attempts`): the row expires and the answer is
  `auth.flow.signUp.verify.codeLocked`. A new code resets the count. The count is returned from the
  transaction and thrown after it, or the rollback would erase it.
- **`auth/email/verify` is throttled by address and by e-mail** (`registration-verify`), so a guess
  spread over many machines still meets a ceiling.
- **`auth/support-names` is public on purpose** — the forgot-password screen names who can help —
  and throttled per address (`support-names`).

## Sign-in by phone code (non-obvious)

`LoginCodeService` signs in to an **existing** account with a six-digit code sent to its phone.

- **Only the hash is stored**, the code lives ten minutes, a new code consumes every open one for
  the phone, and the fifth wrong guess burns it. An expired code answers `code_expired`, a wrong or
  used one `invalid_code`, and the guess that burns a code — and every attempt after it —
  `code_locked`, so the screen can send the person back instead of leaving them typing into a code
  that can never work (`LoginCodeException`, which renders itself with a stable `code`).
- **A phone with no account hears the same answer and gets the same stored row — nothing is
  sent.** Without the row it stayed at `invalid_code` forever while a real phone moved on to
  `code_expired` and `code_locked`, so waiting or guessing told a stranger which numbers have
  accounts. A phone that two accounts share is treated the same way — `findSoleUserByPhone` never
  picks one of two people.
- **Delivery is a contract, not a provider.** `LoginCodeSender` is bound by name from
  `auth.login_code.sender` in `AppServiceProvider::LOGIN_CODE_SENDERS`. The bootstrap ships `log`,
  which writes that a code was issued (never the code) and **refuses in production**, so a deploy
  without a real driver fails at the first request. An unknown driver name throws.
- **`auth.login_code.expose`** (`LOGIN_CODE_EXPOSE`, off by default) returns the code as
  `dev_code`, and is ignored in production whatever the environment says.
- **A deactivated account is named only after the right code**, exactly like the password login.

## Known Gaps

- **Creating the account on the first verify is not here.** The product this came from made the
  phone the customer and created the user on first sign-in; that needs a role and a profile the
  bootstrap cannot choose. A product that wants it does it in its own service after `spend`.
- **`request` skips the delivery for a phone with no account**, so its timing differs from a real
  request by the provider call (the row and the hash are written either way). The throttle bounds how much can be learned; a product that
  delivers through a slow provider should queue the delivery.
- **`auth/support-names` lists the names of every active account holding `users.reset_password`
  to anyone who asks.** Throttled, not closed: the forgot-password screen needs it before sign-in.
  Whether a product should show real names there is an open decision (review 2026-09-17, F1).
- **`EnsureUserPermission` still answers 401/403 in English prose** — it lives in
  `app/Http/Middleware`, outside this module.
- **`users.phone` is not unique.** Two accounts on one phone cannot sign in by code at all until
  one of them changes it.

## Modules per firm (rnc-4)

- **Signing in answers what `auth/me` answers** (rnc-4 F3c, R20): `AuthController::login` and `LoginCodeController::verify` return `withPlatform()` (no view-as at sign-in), so the back office's rail and router know the firm's modules without a second request.

## Do Not

- Do not add a `permissions` master table or pivot — permissions are free-form name strings
  stored per-role / per-user by design.
- Do not move `User` into the module.
