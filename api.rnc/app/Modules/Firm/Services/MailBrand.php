<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Storage;

/**
 * The brand an e-mail wears (rnc-4 R12, D11): the firm it concerns, as plain values. A queued
 * Mailable carries it inside its DTO (plan AM6 — a worker has no current firm), and the `mail.*`
 * view composer paints the band from it: the name, `ink` (the primary: band and text), `accent`,
 * the logo file to embed, and the firm's own site for every link.
 *
 * From the platform's verified address (`mail.from.address`) under the firm's name, replies to the
 * firm's mailbox (D11): one sending domain for every firm. Under a sub-brand (rnc-3 R3) the name and
 * colours are the brand's, the mailbox stays the firm's and no logo is shown — the brand has none.
 *
 * *Retired 14/11/2026 (rnc-4 F2a):* `config('rnc.brand')` — one brand for every e-mail, Franco's.
 */
final readonly class MailBrand
{
    private const LOGO_DISK = 'public';

    /** The platform's own look when no firm is at hand (the new-firm defaults, plan Defaults). */
    private const PLATFORM_INK = '#1f4e79';

    private const PLATFORM_ACCENT = '#f2a900';

    public function __construct(
        public string $name,
        public string $ink,
        public string $accent,
        public ?string $logoPath,
        public ?string $logoUrl,
        public ?string $mailbox,
        public string $siteUrl,
    ) {}

    public static function fromFirm(Firm $firm, ?FirmBrand $brand = null): self
    {
        $logo = ! $brand && $firm->logo_path ? $firm->logo_path : null;

        return new self(
            name: $brand->name ?? $firm->name,
            ink: $brand->brand_primary ?? $firm->brand_primary,
            accent: $brand->brand_accent ?? $firm->brand_accent,
            logoPath: $logo ? Storage::disk(self::LOGO_DISK)->path($logo) : null,
            logoUrl: $logo ? Storage::disk(self::LOGO_DISK)->url($logo) : null,
            mailbox: $firm->mailbox,
            siteUrl: self::siteUrlOf($firm),
        );
    }

    /** The product's own brand — a message sent where no firm is current. */
    public static function platform(): self
    {
        return new self(
            name: (string) config('platform.name'),
            ink: self::PLATFORM_INK,
            accent: self::PLATFORM_ACCENT,
            logoPath: null,
            logoUrl: null,
            mailbox: null,
            siteUrl: (string) config('rnc.frontend_url'),
        );
    }

    /** The platform's verified address under the firm's name (D11). */
    public function sender(): Address
    {
        return new Address((string) config('mail.from.address'), $this->name);
    }

    /** @return list<Address> the firm's mailbox; none when it has no mailbox */
    public function replyTo(): array
    {
        return $this->mailbox === null ? [] : [new Address($this->mailbox, $this->name)];
    }

    /**
     * The firm's own site: `<slug>.<host>` of `rnc.frontend_url` (D2), the root itself for the
     * platform firm. A link to the root would open the platform's firm, where this firm's lead or
     * report is absent.
     */
    private static function siteUrlOf(Firm $firm): string
    {
        $root = (string) config('rnc.frontend_url');
        $host = parse_url($root, PHP_URL_HOST);

        if ($firm->is_platform || ! is_string($host)) {
            return $root;
        }

        return preg_replace('/'.preg_quote($host, '/').'/', $firm->slug.'.'.$host, $root, 1) ?? $root;
    }
}
