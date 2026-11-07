// Pure, runtime-agnostic auth-gate logic for the staff-only docs portal.
// Kept separate from middleware.ts so it can be unit-tested with `node --test`
// (no Vercel/Edge runtime needed). See middleware.ts for the wiring.

// Laravel derives its session cookie name from APP_NAME:
//   Str::slug(APP_NAME) . '-session'  →  APP_NAME={{SLUG}}  →  '{{SLUG}}-session'.
// Driven by env so a different APP_NAME (or an explicit SESSION_COOKIE on the API)
// never silently breaks the gate.
// The cookie the API emits, which Laravel derives as `Str::slug(APP_NAME)-session` — for
// `api.{{SLUG}}` that is `api-{{SLUG}}-session`, NOT `{{SLUG}}-session`. Read it off the API
// (`php artisan config:show session.cookie`); a guess from the project name bounces every
// signed-in reader to the login page with nothing in any log (LP-DOCS-02).
const SESSION_COOKIE = process.env.SESSION_COOKIE_NAME ?? '{{API_SESSION_COOKIE}}';

// Staff signals. The docs are staff-only: a valid session is not enough — the
// user must also be staff. We accept any of these (env-tunable):
//   • a permission every staff/admin carries ({{STAFF_PERMISSION}}), OR
//   • a staff role slug, OR
//   • a role NAME — only when DOCS_STAFF_ROLE_NAME is set. There is no default:
//     a name is free text an administrator types, and a default ('STAFF', from
//     the project this gate came from) would open the docs to whoever first
//     names a role that way, without anyone deciding it.
const STAFF_PERMISSION = process.env.DOCS_STAFF_PERMISSION ?? '{{STAFF_PERMISSION}}';
const STAFF_ROLE_SLUG = process.env.DOCS_STAFF_ROLE_SLUG ?? '{{STAFF_ROLE_SLUG}}';
const STAFF_ROLE_NAME = process.env.DOCS_STAFF_ROLE_NAME || null;

/**
 * Does the Cookie header carry the Laravel session cookie?
 * Matches only a real cookie boundary (start or "; "), never a substring like
 * `not_{{SLUG}}-session=`.
 * @param {string|null|undefined} cookieHeader
 * @param {string} [cookieName]
 * @returns {boolean}
 */
export function hasSessionCookie(cookieHeader, cookieName = SESSION_COOKIE) {
  if (!cookieHeader) return false;
  const escapedName = cookieName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return new RegExp(`(?:^|;\\s*)${escapedName}=`).test(cookieHeader);
}

/**
 * Is this /auth/me user payload a staff member?
 * Reads the UserResource shape: { role, role_slug, permissions: string[] }.
 * @param {{role?: string, role_slug?: string, permissions?: string[]}|null|undefined} user
 * @param {{permission?: string, roleSlug?: string, roleName?: string}} [staff]
 * @returns {boolean}
 */
export function isStaffUser(user, staff = {}) {
  if (!user || typeof user !== 'object') return false;
  const permission = staff.permission ?? STAFF_PERMISSION;
  const roleSlug = staff.roleSlug ?? STAFF_ROLE_SLUG;
  const roleName = staff.roleName ?? STAFF_ROLE_NAME;

  const permissions = Array.isArray(user.permissions) ? user.permissions : [];

  return (
    permissions.includes(permission) ||
    (!!user.role_slug && user.role_slug === roleSlug) ||
    (!!roleName && !!user.role && user.role === roleName)
  );
}

/**
 * Pull the user object out of the ApiResponse envelope ({ data: {...} }).
 * @param {unknown} body
 * @returns {{role?: string, role_slug?: string, permissions?: string[]}|null}
 */
export function userFromMeBody(body) {
  if (!body || typeof body !== 'object') return null;
  const data = 'data' in body ? body.data : body;
  return data && typeof data === 'object' ? data : null;
}

/**
 * Decide whether to serve the page or redirect to login.
 * Serve only when /auth/me returned 200 AND the user is staff. Anything else —
 * non-200, a non-staff user, or a null/unreachable API — fails closed.
 * @param {number|null} meStatus
 * @param {unknown} meBody
 * @param {{permission?: string, roleSlug?: string, roleName?: string}} [staff]
 * @returns {'serve'|'redirect'}
 */
export function gateDecision(meStatus, meBody, staff = {}) {
  if (meStatus !== 200) return 'redirect';
  return isStaffUser(userFromMeBody(meBody), staff) ? 'serve' : 'redirect';
}

/**
 * Build the app login URL carrying a post-login return back to the docs URL.
 * @param {string} currentUrl  the docs URL the user tried to open
 * @param {string} appLoginUrl the app login page
 * @returns {string}
 */
export function buildLoginRedirect(currentUrl, appLoginUrl) {
  const url = new URL(appLoginUrl);
  url.searchParams.set('redirect', currentUrl);
  return url.toString();
}

export { SESSION_COOKIE, STAFF_PERMISSION, STAFF_ROLE_SLUG, STAFF_ROLE_NAME };
