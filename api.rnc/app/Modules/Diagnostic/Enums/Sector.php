<?php

namespace App\Modules\Diagnostic\Enums;

/** The six sectors of the quiz and of the site's pages (rnc-1 R6, R10) — the same slugs. */
enum Sector: string
{
    case Comercio = 'comercio';
    case Industria = 'industria';
    case Servicos = 'servicos';
    case Saude = 'saude';
    case Educacao = 'educacao';
    case Agro = 'agro';

    /** Sectors that charge ICMS today; the others charge ISS (the simulator, demo `taxMix()`). */
    public function chargesIcms(): bool
    {
        return in_array($this, [self::Comercio, self::Industria, self::Agro], true);
    }

    /** Saúde and educação pay 40 % of the IBS/CBS rate (the 60 % reduction, facts.md §2). */
    public function hasReducedRate(): bool
    {
        return in_array($this, [self::Saude, self::Educacao], true);
    }

    /**
     * The sector of a main CNAE, by its first two digits (plan AM10): 01–03 agro, 05–33 indústria,
     * 45–47 comércio, 85 educação, 86–88 saúde, anything else serviços.
     */
    public static function fromCnae(string $cnae): self
    {
        $division = (int) substr(str_pad($cnae, 7, '0', STR_PAD_LEFT), 0, 2);

        return match (true) {
            $division >= 1 && $division <= 3 => self::Agro,
            $division >= 5 && $division <= 33 => self::Industria,
            $division >= 45 && $division <= 47 => self::Comercio,
            $division === 85 => self::Educacao,
            $division >= 86 && $division <= 88 => self::Saude,
            default => self::Servicos,
        };
    }
}
