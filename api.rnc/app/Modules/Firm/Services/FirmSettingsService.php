<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\DTOs\UpdateFirmContactDTO;
use App\Modules\Firm\Enums\PartnerMoneyModel;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Repositories\FirmRepository;

/**
 * The only writer of a firm's own settings (rnc-2 F4): the network's money model (R11, D10) and,
 * since rnc-4 F5a (R30), the contact data its manager edits — address, phone, mailbox, business
 * hours and the public footer's credentials line (W31). It writes the CURRENT firm — the signed-in
 * person's, never a header's — so another firm's settings cannot be reached from here.
 */
class FirmSettingsService
{
    public function __construct(
        private CurrentFirm $currentFirm,
        private FirmRepository $firmRepository,
    ) {}

    /** The current firm, whose settings these are; `null` when no firm is set (never on an authenticated route). */
    public function current(): ?Firm
    {
        return $this->currentFirm->get();
    }

    /** `null` when no firm is set (never on an authenticated route). */
    public function updatePartnerMoneyModel(PartnerMoneyModel $model): ?Firm
    {
        $firm = $this->currentFirm->get();

        return ! $firm ? null : $this->firmRepository->updateFirm($firm, ['partner_money_model' => $model->value]);
    }

    /** Only the fields the request sent; `null` when no firm is set (never on an authenticated route). */
    public function updateContact(UpdateFirmContactDTO $dto): ?Firm
    {
        $firm = $this->currentFirm->get();

        return ! $firm ? null : $this->firmRepository->updateFirm($firm, $dto->toArray());
    }
}
