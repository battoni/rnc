---
outline: deep
title: Access Control
---

# Access Control

This handbook is public on the internet but **gated** — only allowlisted GitHub accounts can read it. The same pattern protects any app we host for a client. Access control is enforced at the edge by `codelumen/middleware.ts` (Vercel Edge Middleware) — there is no application code to touch.

## How the gate works

1. A request arrives. The middleware checks for a signed session cookie.
2. **Valid cookie** → the page is served.
3. **No/invalid cookie** → the browser is redirected to GitHub's OAuth screen (`read:user` scope only).
4. GitHub redirects back to `/_auth/callback`; the middleware exchanges the code, reads the GitHub username, and checks it against `ALLOWED_GITHUB_USERS`.
5. **On the allowlist** → a signed, HttpOnly, 7-day session cookie is set and the user lands on the page. **Not on the list** → a 403 page.

The matcher covers **every path including assets**, so the content can't be read by skipping the login.

## Local vs production

The gate is **Vercel Edge Middleware — it only runs on Vercel.** It does **not** run under `vitepress dev` or when a static build is served by Valet/nginx. So:

- **Local** (`make docs`, or a Valet static build): **ungated by design** — fast, no login.
- **Production** (`codelumen.battoni.dev`): **gated**.

This is intentional — you gate the deployed site, not your own machine.

## Why there is no `robots.txt` here

Every other site in the family has one — `app.rnc`, `api.rnc`, `vitrum`, `docs.rnc`. This
one deliberately does not, and the reason is the matcher above: `'/:path*'` covers **every**
path, `/robots.txt` included. A crawler that asks for it is answered with a 302 to GitHub's
OAuth screen. The file would be unreachable, so it would state nothing to anyone.

Compare `docs.rnc`, whose matcher excludes `.txt`, `.xml`, `.json` and assets: there the
file **is** served while the pages stay gated, which is what makes it worth writing.

What this project does carry is `X-Robots-Tag: noindex` in `vercel.json`, and that is a
backstop rather than the protection — it covers only the case where the gate itself fails
open, because a missing env var or a middleware misconfiguration turns this into a public
site in one deploy, with no error anywhere to say so.

**If you ever narrow the matcher** so static files bypass the gate, add
`public/robots.txt` in the same commit — copy `docs.rnc/public/robots.txt`. The
moment the file becomes reachable is the moment its absence starts to matter.

## Granting or revoking access

Access is a single env var — no code change, no redeploy of logic:

1. Edit **`ALLOWED_GITHUB_USERS`** on the Vercel project (Production) — a comma-separated list of GitHub usernames, e.g. `battoni,alice,bob`.
2. Redeploy (or it applies on the next deploy). Removing a username locks that person out on their next request.

## Environment variables

Set on the Vercel project (Production); never commit them. See `codelumen/.env.example`.

| Variable | What it is |
| --- | --- |
| `GITHUB_CLIENT_ID` | From the GitHub OAuth App |
| `GITHUB_CLIENT_SECRET` | From the GitHub OAuth App |
| `SESSION_SECRET` | Random 32+ byte string that signs the session cookie (`openssl rand -base64 32`) |
| `ALLOWED_GITHUB_USERS` | Comma-separated GitHub usernames allowed in |

## One-time setup (per hosted app)

1. Register a **GitHub OAuth App** — callback URL `https://<domain>/_auth/callback` (e.g. `https://codelumen.battoni.dev/_auth/callback`). Leave Device Flow off.
2. Copy the Client ID, generate a Client Secret.
3. Set the four env vars above on the Vercel project.

> **Note:** a classic OAuth App allows only one callback URL, so preview deployments aren't gated by the production app. Test on production (or a dedicated dev OAuth App pointed at a local `vercel dev` URL).

## Reusing it for a client app

The gate is self-contained in `middleware.ts` + `vercel.json` (the `/((?!_auth/).*)` rewrite). Copy both into another Vercel project, register that app's OAuth App, set its env vars, and it's gated the same way.
