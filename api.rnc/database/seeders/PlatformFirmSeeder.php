<?php

namespace Database\Seeders;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\PlatformSettings;
use App\Modules\Lead\Services\ConsentTextService;
use Illuminate\Database\Seeder;

/**
 * The platform firm (rnc-4 D3): the row the root host and `www` serve and the operator, who has no
 * firm, works in. Every environment — without it a signed-in operator is refused with
 * `firms.required` (plan AM1). Named after the platform (`PLATFORM_NAME`); its look is the
 * platform's own until the console edits it.
 *
 * rnc-4 F2a (R13, D12): the platform's default consent (`platform_settings.consent_default`) is
 * written here, every environment, and the platform firm's version 1 copies it.
 */
class PlatformFirmSeeder extends Seeder
{
    public const PLATFORM_SLUG = 'hub';

    private const PRIMARY = '#1f4e79';

    private const ACCENT = '#f2a900';

    private const SHORT_NAME_LENGTH = 40;

    private const BUSINESS_HOURS = [
        ['weekdays' => [1, 2, 3, 4, 5], 'from' => '09:00', 'to' => '18:00'],
    ];

    public function run(PlatformSettings $platformSettings, ConsentTextService $consentTextService): void
    {
        $name = (string) config('platform.name');

        $platformSettings->startConsentDefault();

        $firm = Firm::updateOrCreate(
            ['slug' => self::PLATFORM_SLUG],
            [
                'name' => $name,
                'logo_text' => $name,
                'short_name' => mb_substr($name, 0, self::SHORT_NAME_LENGTH),
                'brand_primary' => self::PRIMARY,
                'brand_accent' => self::ACCENT,
                'business_hours' => self::BUSINESS_HOURS,
                'is_platform' => true,
            ]
        );

        $consentTextService->startFirm($firm->id);
    }
}
