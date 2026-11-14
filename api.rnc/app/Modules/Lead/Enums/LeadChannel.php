<?php

namespace App\Modules\Lead\Enums;

use App\Modules\Lead\Helpers\ConsentTerms;

/**
 * How a lead came in (rnc-2 R14): the diagnostic's form, the chat receptionist, or a portfolio
 * client Franco referred itself (`carteira`, F10). Set once, when the lead is created, and never
 * changed after — a visitor who switches between form and chat keeps the channel it started in.
 * `beta` (rnc-4 R29) is a firm signing up for the platform's beta on the root site — a lead of the
 * platform firm only, never of a tenant.
 */
enum LeadChannel: string
{
    case Formulario = 'formulario';
    case Chat = 'chat';
    case Carteira = 'carteira';
    case Beta = 'beta';

    /**
     * The channels a visitor can start the public diagnostic from.
     *
     * @return list<self>
     */
    public static function public(): array
    {
        return [self::Formulario, self::Chat];
    }

    /** The `via` a consent given in this channel is stored with (AM12). */
    public function consentVia(): string
    {
        return $this === self::Chat ? ConsentTerms::VIA_CHAT : ConsentTerms::VIA_FORM;
    }
}
