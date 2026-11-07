---
version: 1.2.0
origin: pendulum
based-on: 1.2.0
---

# DEPLOY — app.rnc on Vercel

> For AI agents. Last updated: 2026-09-14.

`vercel.json` is strict JSON and cannot hold a comment, so every decision in it is argued here.
Four projects built this file independently before the bootstrap had one; this is their answer
merged, not a fresh guess.

## The config lives in the repository, never only in the dashboard

A setting that exists solely in Vercel's UI is invisible to a clone, survives no rebuild of the
project, and cannot be reviewed. `lifeshifter` moved it into the repo after a fresh clone
published differently from the original for reasons nobody could see.

**One thing genuinely cannot live here: the Root Directory.** It is set in the dashboard, and it
must point at this directory (`app.rnc/`, or `app.<project>/` after the sync transform). Record the
confirmed value in the project's README — the coupling is invisible, and when it breaks it
surfaces as a random 404 rather than as a build error.

## Hosting it yourself: `.nginx`

A product delivered on its own server serves the same build with `app.rnc/.nginx`, whose header is the
runbook (build on the server, enable the vhost, DNS before Certbot). Three things in it are not
optional, and each fails without an error:

- **`try_files $uri $uri/ /index.html`** — the nginx spelling of the rewrite `vercel.json` carries
  (next section). Without it F5 on a deep route is nginx's 404.
- **`X-Robots-Tag` repeated in every `location` that adds a header.** An `add_header` inside a
  location discards the ones inherited from `server`, so the location that sets `Cache-Control`
  would ship its responses indexable.
- **The app's host in the API's `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS` and
  `SESSION_DOMAIN`.** Otherwise login fails with a 419 or a CORS error that names neither.

Staging goes behind basic auth on this host and behind an IP allowlist on the API's — a browser does
not send basic-auth credentials on a cross-origin XHR.

## The rewrite is the whole reason this file is mandatory

```json
"rewrites": [{ "source": "/(.*)", "destination": "/index.html" }]
```

Without it, `/users` typed straight into the address bar — or an F5 on any screen that is not the
root — returns the host's 404. The route only ever existed in the client. **Four of four derived
projects hit this and fixed it separately**, which is the strongest signal in the whole back-port
inventory that it belongs in the bootstrap.

`trailingSlash: false` goes with it: without it `/users/` and `/users` are two URLs, and only one
of them is the one the router knows.

## Cache rules are deliberately NOT overlapping

Vercel applies every matching `headers` block, so two rules that both match one path both apply
and the result is decided by order rather than by intent. Each source here owns a disjoint set of
paths:

| source | policy | why |
| --- | --- | --- |
| `/assets/(.*)` | one year, `immutable` | the filename carries a content hash, so the file at a given URL never changes |
| `/fonts/(.*)` | a week, then revalidate in the background | changes rarely, and a stale face for one load is invisible |
| `/favicon/(.*)` | a day, then revalidate | same shape, shorter horizon |
| `/` and `/index.html` | `max-age=0, must-revalidate` | the entry document names the hashed bundles; cached, it pins yesterday's app |

**The asymmetry is the answer, not an oversight.** Everything cached means a deploy is invisible;
nothing cached means every visit pays for files that by construction never change.

A wrong cache header on a file with no hash in its name pins the old version for as long as the
header says — a year, in the case of `immutable`. That is why `/assets/` is the only path that
gets it.

## Security headers, and the one that is deliberately narrow

`X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Strict-Transport-Security` and
`Permissions-Policy` are the uncontroversial set; a published site with none of them was the
state `lifeshifter` found itself in.

**The CSP is `frame-ancestors 'self'` and nothing more, on purpose.** A full Content-Security-Policy
turned on without measuring breaks an app silently — the page renders, some script or style never
runs, and nothing reports it. `frame-ancestors` is the half that cannot break a first-party app
and covers the clickjacking case that `X-Frame-Options` addresses in older browsers. Widening it
is a measured exercise per project, not a bootstrap default.

## `X-Robots-Tag: noindex` is the default because app.rnc is the authenticated app

The product's public surface is `vitrum`, not this. An authenticated SPA has nothing a search
engine should hold, and the failure it prevents — a staging deploy indexed with production-shaped
data — cannot be undone by removing the header later.

`chapaja` ships this; `lifeshifter` does not. The bootstrap takes the safer default and asks a
project that genuinely wants its app indexed to remove the line deliberately.

**`robots.txt` is a request; this header is an instruction.** They are two layers and the second
is the one that binds — see `api.rnc`'s `PreventIndexing` middleware and `.nginx` for the same
argument on the API side.

app.rnc serves both: `public/robots.txt` answers `Disallow: /` to every crawler and to each AI
operator by name, and carries `Content-Signal: search=no, ai-input=no, ai-train=no`. That last
line is not redundant with `Disallow`, and the reason is specific: `Claude-User`,
`ChatGPT-User` and `Perplexity-User` are USER-TRIGGERED fetches, which their operators
document as not bound by robots.txt exclusion — somebody pasted a link and the agent goes to
read it. For those three the content signal is the only statement that reaches them, and it is
the only one of the three layers that says anything at all about TRAINING; `noindex` speaks
about indexing and nothing else.

## `engines: node` in `package.json`, because Vercel does not read `.nvmrc`

`.nvmrc` governs the local shell and nothing else. Without `engines`, a remote build can run on a
different major than every developer, with no warning — and the symptom is an ESM error in the
middle of a suite rather than anything naming the version. The two must be kept in step; they are
`22` today.

## Do Not

- **Do not move any of this into the Vercel dashboard.** A clone must publish identically.
- **Do not add a second `headers` block whose `source` overlaps an existing one** — read the table
  above and extend the right row instead.
- **Do not turn on a full CSP here.** Measure it in the project that needs it.
- **Do not delete the rewrite** to "fix" a routing problem; it is what makes client routing work
  at all.
