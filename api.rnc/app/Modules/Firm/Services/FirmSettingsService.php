<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\Enums\PartnerMoneyModel;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Repositories\FirmRepository;

/**
 * The only writer of a firm's own settings (rnc-2 F4): the network's money model (R11, D10). It
 * writes the CURRENT firm — the signed-in person's, never a header's — so another firm's model
 * cannot be reached from here.
 */
class FirmSettingsService
{
    public function __construct(
        private CurrentFirm $currentFirm,
        private FirmRepository $firmRepository,
    ) {}

    /** `null` when no firm is set (never on an authenticated route). */
    public function updatePartnerMoneyModel(PartnerMoneyModel $model): ?Firm
    {
        $firm = $this->currentFirm->get();

        return ! $firm ? null : $this->firmRepository->updateFirm($firm, ['partner_money_model' => $model->value]);
    }
}
