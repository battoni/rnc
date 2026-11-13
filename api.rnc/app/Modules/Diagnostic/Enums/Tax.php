<?php

namespace App\Modules\Diagnostic\Enums;

/** The taxes of the 2026–2033 simulator (rnc-1 R14, demo `TAXES`), in the order the chart stacks them. */
enum Tax: string
{
    case PisCofins = 'pis-cofins';
    case Icms = 'icms';
    case Iss = 'iss';
    case Cbs = 'cbs';
    case Ibs = 'ibs';

    public function label(): string
    {
        return match ($this) {
            self::PisCofins => 'PIS/Cofins',
            self::Icms => 'ICMS',
            self::Iss => 'ISS',
            self::Cbs => 'CBS',
            self::Ibs => 'IBS',
        };
    }

    /** True for the taxes the Reforma retires; false for the two it brings in. */
    public function isLeaving(): bool
    {
        return ! in_array($this, [self::Cbs, self::Ibs], true);
    }
}
