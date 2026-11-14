<?php

namespace App\Modules\Firm\Repositories;

use App\Modules\Firm\Models\PlatformSetting;

class PlatformSettingRepository
{
    public function findPlatformSettingValue(string $key): ?string
    {
        return PlatformSetting::query()->where('key', $key)->value('value');
    }

    /** Writes the value only when the key has none — a seed never overwrites an edit. */
    public function firstOrCreatePlatformSetting(string $key, string $value): PlatformSetting
    {
        return PlatformSetting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
    }
}
