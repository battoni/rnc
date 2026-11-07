<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\DTOs\UpdateBrandDTO;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Firm\Repositories\BrandRepository;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Repositories\LeadRepository;

/**
 * The sub-brands console (rnc-3 R5, D3): the firm's main brand and its sub-brands, each with its
 * lead count; the edit and the switch. A brand is never deleted, only deactivated, so its leads keep
 * their chip and their filter. The lead counts come from the Lead module's one grouped query.
 */
class BrandService
{
    /** The board filter's word for the main brand (`GET leads?brand=main`). */
    public const MAIN_SLUG = 'main';

    public function __construct(
        private CurrentFirm $currentFirm,
        private BrandRepository $brandRepository,
        private LeadRepository $leadRepository,
    ) {}

    /**
     * @return array{main: array{firm: Firm|null, lead_count: int}, brands: list<array{brand: FirmBrand, lead_count: int}>}
     */
    public function list(): array
    {
        $counts = $this->leadRepository->countLeadsByBrand();

        return [
            'main' => ['firm' => $this->currentFirm->get(), 'lead_count' => $counts[Lead::MAIN_BRAND] ?? 0],
            'brands' => $this->brandRepository->findAllBrands()
                ->map(fn (FirmBrand $brand) => ['brand' => $brand, 'lead_count' => $counts[$brand->id] ?? 0])
                ->values()
                ->all(),
        ];
    }

    /** @return array{brand: FirmBrand, lead_count: int}|null null for an unknown or another firm's brand */
    public function update(int $id, UpdateBrandDTO $changes): ?array
    {
        $brand = $this->brandRepository->findBrandById($id);

        return ! $brand ? null : $this->withCount($this->brandRepository->updateBrand($brand, $changes->toArray()));
    }

    /** @return array{brand: FirmBrand, lead_count: int}|null null for an unknown or another firm's brand */
    public function setActive(int $id, bool $active): ?array
    {
        $brand = $this->brandRepository->findBrandById($id);

        return ! $brand ? null : $this->withCount($this->brandRepository->updateBrand($brand, ['active' => $active]));
    }

    /**
     * The board's brand filter (rnc-3 R4, AM13): `main` is the main brand, a slug of this firm is
     * that brand's id, inactive too. The request already refused any other slug.
     */
    public function brandIdForFilter(string $slug): ?int
    {
        return $slug === self::MAIN_SLUG ? Lead::MAIN_BRAND : $this->brandRepository->findBrandBySlug($slug)?->id;
    }

    /** @return array{brand: FirmBrand, lead_count: int} */
    private function withCount(FirmBrand $brand): array
    {
        return ['brand' => $brand, 'lead_count' => $this->leadRepository->countLeadsByBrand()[$brand->id] ?? 0];
    }
}
