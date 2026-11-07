<?php

namespace App\Modules\Partner\Services;

use App\Modules\Partner\DTOs\PartnerDTO;
use App\Modules\Partner\DTOs\UpdatePartnerDTO;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Repositories\PartnerRepository;
use Illuminate\Database\Eloquent\Collection;

/** The registry (rnc-1 R21), managed by `ceo` and `operador`. A partner is deactivated, never deleted. */
class PartnerService
{
    public function __construct(private PartnerRepository $partnerRepository) {}

    /** @return Collection<int, Partner> */
    public function list(): Collection
    {
        return $this->partnerRepository->findAllPartners();
    }

    /** @return list<array{value: int, label: string, active: bool}> every partner, inactive too: the trail filters its history */
    public function options(): array
    {
        return $this->partnerRepository->findPartnersByName()
            ->map(fn (Partner $partner) => ['value' => $partner->id, 'label' => $partner->name, 'active' => $partner->active])
            ->values()
            ->all();
    }

    public function create(PartnerDTO $partner): Partner
    {
        return $this->partnerRepository->createPartner([...$partner->toArray(), 'active' => true]);
    }

    /** Null for an unknown or another firm's partner — the controller answers 404. */
    public function update(int $id, UpdatePartnerDTO $changes): ?Partner
    {
        $partner = $this->partnerRepository->findPartnerById($id);

        return ! $partner ? null : $this->partnerRepository->updatePartner($partner, $changes->toArray());
    }
}
