<?php

namespace App\Modules\Lead\DTOs;

use App\Modules\Firm\Services\MailBrand;

/**
 * What the platform team's "inscrição no beta" e-mail prints (rnc-4 R29): the signup as plain
 * values, under the platform firm's brand (`MailBrand`), and the lead it became — a queued Mailable
 * never queries.
 */
final class BetaSignupMailDTO
{
    /** The lead detail on the pipeline, pt-BR path (the e-mail speaks pt-BR). */
    private const LEAD_SCREEN = '/equipe/leads/';

    public function __construct(
        public readonly int $leadId,
        public readonly string $company,
        public readonly string $name,
        public readonly string $role,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $city,
        public readonly string $uf,
        public readonly string $clientsBand,
        public readonly ?string $message,
        public readonly MailBrand $brand,
    ) {}

    public static function fromSignup(int $leadId, BetaSignupDTO $signup, MailBrand $brand): self
    {
        return new self(
            leadId: $leadId,
            company: $signup->company,
            name: $signup->name,
            role: $signup->role,
            email: $signup->email,
            phone: $signup->phone,
            city: $signup->city,
            uf: $signup->uf,
            clientsBand: $signup->clients_band,
            message: $signup->message,
            brand: $brand,
        );
    }

    /** The button: the new lead on the platform firm's own pipeline. */
    public function leadUrl(): string
    {
        return rtrim($this->brand->siteUrl, '/').self::LEAD_SCREEN.$this->leadId;
    }
}
