---
version: 1.4.0
origin: pendulum
based-on: 1.0.0
---

# RULES — mail views

> For AI agents.

## Purpose

Every e-mail the API sends. `layout.blade.php` is the chrome; each message extends it and
fills `@section('body')`.

## Intentional Decisions

- **Tables and inline styles, and this is not a style choice.** An e-mail client is not a
  browser: Outlook renders through Word, Gmail strips `<style>` from the `<head>`, and flex
  and grid are unavailable in enough clients that using them means the message arrives as an
  unstyled column. This is the one layout that survives everywhere.
- **Every message has a text alternative** (`<name>-text.blade.php`, declared as `text:` on
  the `Content`). A message with no text part scores worse with spam filters, and it is the
  only version a screen reader never has to fight.
- **A preheader per message.** It is the line the inbox shows beside the subject; without one
  the client picks the first words it finds, which is usually the greeting.
- **The brand is the firm's the message concerns (rnc-4 R12)**: the Mailable hands its view a
  `mailBrand` (`App\Modules\Firm\Services\MailBrand`, carried in its queued DTO) and the composer
  in `AppServiceProvider` turns it into `$brandName`, `$ink` (the firm's primary), `$accent`,
  `$brandLogo` / `$brandLogoUrl` and `$frontendUrl` (the firm's own host). A view with no
  `mailBrand` wears the request's firm, else the platform's own look. An e-mail has no CSS
  variables, so the colours are literals — and no view reads a config for them.
  *Retired 14/11/2026 (rnc-4 F2a):* "the brand arrives from `config('rnc.brand')`" — one brand,
  Franco's, on every firm's e-mail.
- **From the platform's address, under the firm's name; replies to the firm (D11).** Every
  Mailable's envelope takes `MailBrand::sender()` (`mail.from.address`, the firm's — or the
  sub-brand's — name) and `replyTo()` (the firm's mailbox). One verified sending domain for every
  firm; a per-firm domain is out of scope.
- **The logo is EMBEDDED, and the name stays as text beside it.** `$message->embed()`
  attaches the file and returns a `cid:` reference, so the mark travels with the message
  instead of depending on a public URL that has to still answer in five years. The name is
  text because most clients block images by default — a header that is only an image is a
  blank band until the reader clicks "show images".
- **The band takes the firm's primary.** *Retired 14/11/2026 (rnc-4 F2a):* "the band takes the
  LOGO FILE's colour, not the theme's" and "the asset is cropped to the glyph" — they described
  one product's bundled logo; a firm's logo is its upload (F4c) and the band is its primary.
- **No logo file → the initial in a tile** (`firms.logo_path` empty, or a sub-brand's message):
  the band draws the name's first letter on the accent, as text.
- **The footer says why the message arrived** — `@section('footer_reason')` when the reason is not
  an account (the booking e-mails: "porque agendou uma reunião"). An e-mail that does not explain itself reads
  as spam even when the person asked for it.

- **The daily briefing without the network** (rnc-4 F3a, R19): on a null `referrals` the preheader
  takes `mail.briefing.preheader_no_referrals`, and the referral figure, heading and list are not
  drawn, in both the HTML and the text view. Tested by `tests/Feature/Briefing/BriefingDegradeTest`.

## Preview

`/mail-preview` lists them, `/mail-preview/<name>` renders one, `?format=text` shows the
text part. **Local only** — the routes are registered behind `app()->isLocal()` because they
render with sample data and no session. A new Mailable adds a line to
`MailPreviewController::messages()`, with the sample arguments that make it realistic.

## Do Not

- **Do not use `<div>` for layout**, and do not add a `<style>` block. Both are ignored or
  mangled by the clients this has to reach.
- **Do not ship a message without its `-text` twin.**
- **Do not read the app's theme here.** The API cannot see `app.rnc`'s CSS; the three brand
  values live in config and are kept in step by hand.
