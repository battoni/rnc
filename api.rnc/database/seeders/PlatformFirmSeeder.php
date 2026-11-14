<?php

namespace Database\Seeders;

use App\Modules\Firm\Models\Firm;
use Illuminate\Database\Seeder;

/**
 * The platform firm (rnc-4 D3): the row the root host and `www` serve and the operator, who has no
 * firm, works in. Every environment — without it a signed-in operator is refused with
 * `firms.required` (plan AM1). Named after the platform (`PLATFORM_NAME`); its look is the
 * platform's own until the console edits it.
 */
class PlatformFirmSeeder extends Seeder
{
    public const PLATFORM_SLUG = 'hub';

    private const PRIMARY = '#1f4e79';

    private const ACCENT = '#f2a900';

    private const BUSINESS_HOURS = [
        ['weekdays' => [1, 2, 3, 4, 5], 'from' => '09:00', 'to' => '18:00'],
    ];

    public function run(): void
    {
        $name = (string) config('platform.name');

        Firm::updateOrCreate(
            ['slug' => self::PLATFORM_SLUG],
            [
                'name' => $name,
                'logo_text' => $name,
                'brand_primary' => self::PRIMARY,
                'brand_accent' => self::ACCENT,
                'business_hours' => self::BUSINESS_HOURS,
                'is_platform' => true,
            ]
        );
    }
}
