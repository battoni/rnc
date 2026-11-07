import { next } from '@vercel/edge';
import { SignJWT, jwtVerify } from 'jose';

// Gate the whole site behind GitHub OAuth + an allowlist of usernames.
// Runs as Vercel Edge Middleware on every request (assets included, so the
// VitePress content chunks under /assets are protected too).

export const config = {
  matcher: '/:path*',
};

const SESSION_COOKIE = 'cl_session';
const STATE_COOKIE = 'cl_oauth_state';
const SESSION_MAX_AGE = 7 * 24 * 60 * 60; // 7 days
const STATE_MAX_AGE = 10 * 60; // 10 minutes

const GITHUB_AUTHORIZE = 'https://github.com/login/oauth/authorize';
const GITHUB_TOKEN = 'https://github.com/login/oauth/access_token';
const GITHUB_USER = 'https://api.github.com/user';

interface SessionPayload {
  login: string;
  name: string;
}

interface StatePayload {
  state: string;
  dest: string;
}

export default async function middleware(request: Request): Promise<Response> {
  const url = new URL(request.url);
  const { pathname } = url;

  if (pathname === '/_auth/login') return startLogin(url, '/');

  if (pathname === '/_auth/callback') return handleCallback(request, url);

  if (pathname === '/_auth/logout') return handleLogout(url);

  const token = readCookie(request, SESSION_COOKIE);
  const session = token ? await verifyToken<SessionPayload>(token) : null;

  if (session) return next();

  return startLogin(url, pathname + url.search);
}

// HELPERS

function requireEnv(name: string): string {
  const value = process.env[name];

  if (!value) throw new Error(`${name} is not set`);

  return value;
}

function secret(): Uint8Array {
  return new TextEncoder().encode(requireEnv('SESSION_SECRET'));
}

function isAllowed(login: string): boolean {
  const allowlist = (process.env.ALLOWED_GITHUB_USERS ?? '')
    .split(',')
    .map((entry) => entry.trim().toLowerCase())
    .filter(Boolean);

  return allowlist.includes(login.toLowerCase());
}

function readCookie(request: Request, name: string): string | null {
  const header = request.headers.get('cookie');

  if (!header) return null;

  for (const part of header.split(';')) {
    const [key, ...rest] = part.trim().split('=');

    if (key === name) return decodeURIComponent(rest.join('='));
  }

  return null;
}

function buildCookie(name: string, value: string, maxAge: number): string {
  return `${name}=${value}; HttpOnly; Secure; SameSite=Lax; Path=/; Max-Age=${maxAge}`;
}

function clearCookie(name: string): string {
  return `${name}=; HttpOnly; Secure; SameSite=Lax; Path=/; Max-Age=0`;
}

async function signToken(payload: SessionPayload | StatePayload, expiresIn: string): Promise<string> {
  return new SignJWT({ ...payload })
    .setProtectedHeader({ alg: 'HS256' })
    .setIssuedAt()
    .setExpirationTime(expiresIn)
    .sign(secret());
}

async function verifyToken<T>(token: string): Promise<T | null> {
  try {
    const { payload } = await jwtVerify(token, secret());

    return payload as T;
  } catch {
    return null;
  }
}

// Only allow same-site path redirects (no protocol-relative or absolute URLs).
function safeDest(dest: string | undefined): string {
  if (!dest || !dest.startsWith('/') || dest.startsWith('//')) return '/';

  return dest;
}

function redirect(location: string, cookies: string[] = []): Response {
  const response = new Response(null, {
    status: 302,
    headers: { Location: location },
  });

  for (const cookie of cookies) {
    response.headers.append('Set-Cookie', cookie);
  }

  return response;
}

function deny(message: string, status = 400): Response {
  const body = `<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Access denied · Code Lumen</title>
    <style>
      body { font-family: system-ui, sans-serif; background: #0d0d0d; color: #e6e6e6; display: grid; place-items: center; height: 100vh; margin: 0; }
      main { max-width: 28rem; text-align: center; padding: 2rem; }
      h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
      p { color: #a3a3a3; line-height: 1.5; }
      a { display: inline-block; margin-top: 1.5rem; color: #0d0d0d; background: #c0e021; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; }
    </style>
  </head>
  <body>
    <main>
      <h1>Access denied</h1>
      <p>${message}</p>
      <a href="/_auth/login">Sign in with a different account</a>
    </main>
  </body>
</html>`;

  return new Response(body, {
    status,
    headers: { 'Content-Type': 'text/html; charset=utf-8' },
  });
}

// EVENTS

async function startLogin(url: URL, dest: string): Promise<Response> {
  const state = crypto.randomUUID();
  const stateToken = await signToken({ state, dest: safeDest(dest) }, '10m');

  const authorize = new URL(GITHUB_AUTHORIZE);
  authorize.searchParams.set('client_id', requireEnv('GITHUB_CLIENT_ID'));
  authorize.searchParams.set('redirect_uri', `${url.origin}/_auth/callback`);
  authorize.searchParams.set('scope', 'read:user');
  authorize.searchParams.set('state', state);

  return redirect(authorize.toString(), [buildCookie(STATE_COOKIE, stateToken, STATE_MAX_AGE)]);
}

async function handleCallback(request: Request, url: URL): Promise<Response> {
  const code = url.searchParams.get('code');
  const returnedState = url.searchParams.get('state');
  const stateToken = readCookie(request, STATE_COOKIE);

  if (!code || !returnedState || !stateToken) return deny('Invalid authentication request.');

  const statePayload = await verifyToken<StatePayload>(stateToken);
  const stateMatches = statePayload && statePayload.state === returnedState;

  if (!stateMatches) return deny('Invalid or expired authentication state. Please try again.');

  const tokenResponse = await fetch(GITHUB_TOKEN, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      client_id: requireEnv('GITHUB_CLIENT_ID'),
      client_secret: requireEnv('GITHUB_CLIENT_SECRET'),
      code,
      redirect_uri: `${url.origin}/_auth/callback`,
    }),
  });

  const tokenData = (await tokenResponse.json()) as { access_token?: string };

  if (!tokenData.access_token) return deny('Could not complete GitHub sign-in.');

  const userResponse = await fetch(GITHUB_USER, {
    headers: {
      Authorization: `Bearer ${tokenData.access_token}`,
      Accept: 'application/vnd.github+json',
      'User-Agent': 'codelumen-auth',
    },
  });

  const user = (await userResponse.json()) as { login?: string; name?: string };

  if (!user.login) return deny('Could not read your GitHub account.');

  if (!isAllowed(user.login)) return deny(`Account "${user.login}" is not on the access list.`, 403);

  const session = await signToken({ login: user.login, name: user.name ?? user.login }, '7d');

  return redirect(safeDest(statePayload.dest), [
    buildCookie(SESSION_COOKIE, session, SESSION_MAX_AGE),
    clearCookie(STATE_COOKIE),
  ]);
}

function handleLogout(url: URL): Response {
  return redirect(`${url.origin}/`, [clearCookie(SESSION_COOKIE)]);
}
