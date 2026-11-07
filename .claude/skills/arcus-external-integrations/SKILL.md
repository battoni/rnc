---
name: arcus-external-integrations
description: "Conventions for talking to an external provider from api.rnc — payment gateways, messaging APIs, anything with a webhook. Load when adding or changing an integration: a sender or gateway class, a webhook endpoint, an idempotency key, a provider status mapping, or an e-mail sent from a listener. Covers what a provider failure may and may not do to a request, what to write before the call, what the audit row must NOT keep, card data and PCI scope, matching events by the provider's id, signature verification, monotonic webhook status, and where the provider's vocabulary is allowed to live."
license: MIT
paths:
  - "api.rnc/**"
---

# External integrations — what a provider is allowed to do to your app

> These are not theory. Every rule here is a defect that was paid for in largatudoevai, against
> Mercado Pago and the WhatsApp Cloud API, and the cost is written next to each one. The bootstrap
> has no integration yet: read this **before** the first one, not after.

## The shape

A provider lives behind a **contract** in `app/Modules/{Module}/Contracts/`, with one implementation
per driver and a `log` driver that writes the row and sends nothing. The contract is what the rest
of the app depends on; the driver is what a `.env` switches.

## A provider failure is a status on a row, never an exception

**The sender does not throw.** Delivery failed is `status = failed` plus `error` on the message row,
and the caller does not wrap anything in `try/catch`.

A reservation is not lost because WhatsApp is down. The moment a sender throws, every caller has to
decide what to do about a failure that has nothing to do with the operation it was performing — and
the decision they reach, under pressure, is a `try/catch` that swallows it.

## Write the audit row BEFORE the call

Create the row with `status = queued`, make the request, then resolve the row to `sent` or `failed`.
A crash in the middle then leaves a trace instead of silence, and the stored `payload` is the exact
body that went out — which is the only thing that settles an argument with a provider's support.

## The audit row keeps the payload — minus what signs a person in

"Exact" has one exception: a secret that authenticates a person. A login code sent in a WhatsApp
template is masked in the stored `payload` (`******`) before the row is written, and masked again in
any listing or export built from those rows. A message log is read by support staff and kept for
months; a code in it is a way into somebody's account for everyone who can open the table.

Mask by the template's known parameter, not by guessing at digits, and lock it with a test that
sends a code and asserts the row does not contain it.

## Card data never touches our DOM or our API

A card number is only ever typed into the provider's hosted field or iframe (Mercado Pago's Card
Payment Brick, a Stripe Element), and what reaches our API is the token it returns. The moment the
number passes through an `<input>` we render or a request we receive, the whole app is in PCI scope
— servers, logs, backups — and no amount of "we don't store it" takes it back out.

## Match a provider event by the id the provider gave you

A delivery receipt, a payment notification, a reply — attach it to our row by the provider's
canonical id, the one its API RETURNED when we made the call (`wamid`, the payment id), stored on the
row at send time. Never by what the person typed: a phone number arrives in the provider's
normalised form (`5511…`, no ninth digit, a `+`), so matching on the typed value misses events or,
worse, attaches them to someone else's row.

## A webhook status only ever moves forward

`sent < delivered < read`, and `failed` is terminal. Providers deliver events out of order — the
Meta API does it routinely — so an event that arrives late must not walk the row backwards.
Monotonicity is what makes the final status true.

## An unsigned webhook is refused outside local and testing

Verify the signature (HMAC over the manifest the provider documents, the id read from the body and
lowercased where they say so), and **refuse when the secret is empty** unless the environment is
`local` or `testing`.

The first version computed the HMAC against an empty string when the secret was missing, and another
accepted the payload with no signature at all. Both looked like working code: the endpoint answered
200 and the rows appeared.

## Re-fetch from the provider; never trust the body

A webhook says *something changed about id X*. Read the current state from the provider's API and
act on that. The body is a notification, not a source of truth — it can be replayed, reordered, or
forged if the signature check ever regresses.

A notification that arrives before the local row exists is an **orphan**: store it, and attach it
when the row shows up. Dropping it loses a payment.

## Idempotency: a key per ATTEMPT, not per reference

`X-Idempotency-Key` defaults to the external reference, and anything that legitimately retries the
same reference — a second Pix after the first expired, a card retry — sends its own key
(`{reference}-{ulid}`). A key fixed to the reference makes the provider answer the second attempt
with the first attempt's result, which is a payment that never happens and a screen that says it did.

**The opposite case: an operation that may happen at most once.** A full refund, a cancellation — a
retry of THESE must be answered with the first result, so their key is derived from the reference
and nothing else (`refund-{orderId}`). A key per attempt there is a customer refunded twice by a
retried cancel. Send no body that turns the call into something else (a refund body that lists
transactions is a PARTIAL refund). And the local status still follows the provider's webhook
(`refunded`), never the response of the call: the response says the request was accepted, the
webhook says it happened (largatudoevai).

The question that picks the key: *should a second call with the same reference do something new?*
Yes → a key per attempt. No → a key per reference.

## The provider's vocabulary stops at the gateway

`processed`, `expired`, `rejected` are the provider's words. The gateway translates them into the
app's own enum, which stays neutral, and the translation lives in **one** method
(`toPaymentStatus()`). When a leak is closed, lock it with a test that asserts the shared enum does
NOT know the provider's mapping any more — otherwise it grows back quietly.

**Close the leak you can see and no more.** The same refactor left three other couplings alone and
said so in the module's RULES: designing an abstraction against a single implementation is guessing,
and a wrong abstraction costs more than a named coupling.

## Secrets: the public key is served, everything else stays home

The publishable key reaches the SPA through an endpoint (`GET payments/config`, and a throttled
public twin when a customer flow needs it) — never baked into the bundle. The access token and the
webhook secret never leave the server. Sandbox triggers live in config and are empty in
`.env.example`.

## E-mail from a listener is synchronous and never fatal

Transactional mail sent from a domain event is wrapped so the provider cannot fail the request that
triggered it: log `mail_failed` and carry on; a missing address logs `mail_skipped`. The webhook
that dispatched the event answers 200 whatever the mail provider does — a retry storm from a
provider that got a 500 is worse than a missing e-mail.

Queue it when the project has a worker; the bootstrap has none, so it sends inline and the reader is
on the confirmation screen.

## Do not

- Do not let a provider's SDK into the app to save a few lines of HTTP — the Orders API here is
  plain REST, and an SDK is a dependency that decides your error shape for you.
- Do not store a login code, a password-reset code or a card number in an audit row, and do not
  render one in a listing.
- Do not look a provider event up by a phone number or an e-mail the user typed.
- Do not put a status literal in a service. The enum is the vocabulary; the gateway is the only
  translator.
- Do not answer a webhook with anything but 200 once the signature is verified and the event is
  stored. Your processing failure is not the provider's business, and it will retry for days.
