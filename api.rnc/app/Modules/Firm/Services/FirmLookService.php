<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\DTOs\UpdateFirmLookDTO;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Repositories\FirmRepository;

/**
 * The only writer of a firm's look (rnc-4 R25, D19): name, logo text, short name, tagline, proof and
 * colours. Called by the platform console for the firm it names; the request already checked the
 * colours (white on the primary ≥ 4,5:1, R11).
 */
class FirmLookService
{
    public function __construct(private FirmRepository $firmRepository) {}

    public function update(Firm $firm, UpdateFirmLookDTO $changes): Firm
    {
        return $this->firmRepository->updateFirm($firm, $changes->toArray());
    }
}
