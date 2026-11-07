<?php

namespace App\Modules\Firm\Repositories;

use App\Modules\Firm\Models\Firm;

class FirmRepository
{
    public function findFirmById(int $id): ?Firm
    {
        return Firm::query()->find($id);
    }

    public function findFirmBySlug(string $slug): ?Firm
    {
        return Firm::query()->where('slug', $slug)->first();
    }
}
