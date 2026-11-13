<?php

namespace App\Modules\Diagnostic\Enums;

/**
 * The faturamento bands (rnc-1 R10, demo `BANDS`). `revenue()` is the band's representative annual
 * revenue, the base every R$ range of the diagnosis scales from.
 */
enum RevenueBand: string
{
    case UpTo360Thousand = 'b1';
    case UpTo4Point8Million = 'b2';
    case UpTo30Million = 'b3';
    case UpTo300Million = 'b4';
    case Above300Million = 'b5';

    public function revenue(): float
    {
        return match ($this) {
            self::UpTo360Thousand => 240_000.0,
            self::UpTo4Point8Million => 1_800_000.0,
            self::UpTo30Million => 12_000_000.0,
            self::UpTo300Million => 90_000_000.0,
            self::Above300Million => 450_000_000.0,
        };
    }
}
