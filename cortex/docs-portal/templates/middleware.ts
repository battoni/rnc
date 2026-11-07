// Vercel Edge Middleware — the hard gate for the staff-only docs portal.
//
// VitePress ships static HTML/JS, so a client-side check would leave the files
// publicly fetchable. This middleware runs on every document request BEFORE the
// static asset is served: it reuses the app's Sanctum session by forwarding the
// `{{SLUG}}-session` cookie to GET {API_URL}/auth/me. The page is served
// only when /auth/me returns 200 AND the user is staff; everything else bounces
// to the app login. Nothing is served without a valid staff session.
//
// Env (set in Vercel — see .env.example):
//   API_URL                 base URL of the API INCLUDING the /api prefix
//                           (e.g. {{API_HOST}}).
//   APP_LOGIN_URL           the app login page to bounce unauthenticated users to.
//   SESSION_COOKIE_NAME     Laravel session cookie name ('{{SLUG}}-session').
//   DOCS_STAFF_PERMISSION   permission that marks staff ('{{STAFF_PERMISSION}}').
//   DOCS_STAFF_ROLE_SLUG    staff role slug fallback ('{{STAFF_ROLE_SLUG}}').
//   DOCS_STAFF_ROLE_NAME    optional role name that also counts as staff (no default).
import { next } from '@vercel/edge';
import { hasSessionCookie, gateDecision, buildLoginRedirect } from './middleware/auth-gate.mjs';

export const config = {
  // Run on document requests only; skip VitePress static assets so we don't pay
  // an auth round-trip per file (and to avoid redirect loops on the login bounce).
  matcher: ['/((?!assets/|.*\\.(?:js|css|map|json|txt|xml|png|jpe?g|svg|gif|webp|ico|woff2?|ttf|eot)$).*)'],
};

// Driven by env vars (set in Vercel). The middleware only runs on Vercel, so the
// fallbacks are the production hosts — never the local *.test domains.
const API_URL = (process.env.API_URL ?? '{{API_HOST}}').replace(/\/$/, '');
const APP_LOGIN_URL = process.env.APP_LOGIN_URL ?? '{{APP_LOGIN_URL}}';

export default async function middleware(request: Request): Promise<Response> {
  const cookieHeader = request.headers.get('cookie');
  const toLogin = () => Response.redirect(buildLoginRedirect(request.url, APP_LOGIN_URL), 302);

  // No session cookie at all → don't even ask the API.
  if (!hasSessionCookie(cookieHeader)) return toLogin();

  let status: number | null = null;
  let body: unknown = null;
  try {
    const res = await fetch(`${API_URL}/auth/me`, {
      headers: { cookie: cookieHeader ?? '', accept: 'application/json' },
      redirect: 'manual',
    });
    status = res.status;
    if (status === 200) {
      body = await res.json().catch(() => null);
    }
  } catch {
    // API unreachable → fail closed (treat as unauthenticated).
    status = null;
  }

  return gateDecision(status, body) === 'serve' ? next() : toLogin();
}
