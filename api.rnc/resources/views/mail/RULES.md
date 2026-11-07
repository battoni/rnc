---
version: 1.0.0
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
- **The brand arrives from `config('rnc.brand')`**, injected into every `mail.*` view by
  a composer in `AppServiceProvider`. An e-mail has no CSS variables, so the colours have to
  be literals — and no Mailable should have to remember to pass them.
- **The logo is EMBEDDED, and the name stays as text beside it.** `$message->embed()`
  attaches the file and returns a `cid:` reference, so the mark travels with the message
  instead of depending on a public URL that has to still answer in five years. The name is
  text because most clients block images by default — a header that is only an image is a
  blank band until the reader clicks "show images".
- **The band takes the LOGO FILE's colour, not the theme's.** They differ by one hex unit
  (`#141313` against `#141413`), and the logo is a square tile with its background baked in,
  so a band painted from the theme leaves a faint seam around it.
- **The asset is cropped to the glyph.** In the app's tile the mark occupies 27% of the
  width; at a header's 40px that is a 10px speck beside 19px text.
- **The footer says why the message arrived.** An e-mail that does not explain itself reads
  as spam even when the person asked for it.

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
