<?php

namespace App\Modules\Booking\Enums;

use App\Modules\Diagnostic\Enums\Regime;
use App\Modules\Diagnostic\Enums\Sector;

/**
 * What a specialist is offered first for (rnc-1 R15): the demo's `suggestedSpecialist()` — Simples
 * first, then indústria, then Lucro Real, anything else the Reforma generalist. Stored as a string
 * on `specialists.focus` (AM11).
 */
enum SpecialistFocus: string
{
    case Simples = 'simples';
    case Industria = 'industria';
    case Real = 'real';
    case Geral = 'geral';

    /** @param  array<string, mixed>  $profile */
    public static function forProfile(array $profile): self
    {
        $regime = $profile['regime'] ?? null;

        return match (true) {
            $regime === Regime::Simples->value => self::Simples,
            ($profile['sector'] ?? null) === Sector::Industria->value => self::Industria,
            $regime === Regime::Real->value => self::Real,
            default => self::Geral,
        };
    }
}
