<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\Repositories\PlatformSettingRepository;

/**
 * The platform's own settings (rnc-4 D12), the only writer of `platform_settings`. Today one: the
 * consent sentence a new firm's version 1 copies (`consent_default`, R13). An unset key reads the
 * built-in default, so a database seeded before the row existed still answers. The operator's edit
 * of it arrives with the console (F3a).
 */
class PlatformSettings
{
    public const CONSENT_DEFAULT = 'consent_default';

    /** The platform's own words: no firm and no network named, the firm comes in through `{escritorio}`. */
    public const DEFAULT_CONSENT_TEXT = 'Autorizo {escritorio} a me apresentar a especialistas parceiros quando o diagnóstico indicar.';

    public function __construct(private PlatformSettingRepository $platformSettingRepository) {}

    public function consentDefault(): string
    {
        return $this->platformSettingRepository->findPlatformSettingValue(self::CONSENT_DEFAULT) ?? self::DEFAULT_CONSENT_TEXT;
    }

    /** The built-in default written once; an operator's edit (F3a) survives every later seed. */
    public function startConsentDefault(): void
    {
        $this->platformSettingRepository->firstOrCreatePlatformSetting(self::CONSENT_DEFAULT, self::DEFAULT_CONSENT_TEXT);
    }
}
