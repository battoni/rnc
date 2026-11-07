# Mail Best Practices

## Implement `ShouldQueue` on the Mailable Class

Makes queueing the default regardless of how the mailable is dispatched. No need to remember `Mail::queue()` at every call site — `Mail::send()` also queues it.

## Use `afterCommit()` on Mailables Inside Transactions

A queued mailable dispatched inside a transaction may process before the commit. Use `$this->afterCommit()` in the constructor.

## Use `assertQueued()` Not `assertSent()` for Queued Mailables

`Mail::assertSent()` only catches synchronous mail. Queued mailables silently pass `assertSent`, giving false confidence.

Incorrect: `Mail::assertSent(OrderShipped::class);` when mailable implements `ShouldQueue`.

Correct: `Mail::assertQueued(OrderShipped::class);`

## Use Markdown Mailables for Transactional Emails

Markdown mailables auto-generate both HTML and plain-text versions, use responsive components, and allow global style customization. Generate with `--markdown` flag.

## Separate Content Tests from Sending Tests

Content tests: instantiate the mailable directly, call `assertSeeInHtml()`.
Sending tests: use `Mail::fake()` and `assertSent()`/`assertQueued()`.
Don't mix them — it conflates concerns and makes tests brittle.

## A Mailable That Leaves the Request Carries Its Locale

Sent inline, a mailable renders in the request's locale. Queued, it renders in the worker's default.
Call `$this->locale($locale)` in the constructor — Laravel then wraps the envelope and the views in that
locale — and pass it to any `__()` the class calls itself, so the subject is right even when it is read
outside a send.
