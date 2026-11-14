<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Firm\DTOs\CreateFirmDTO;
use App\Modules\Firm\DTOs\FirmManagerDTO;
use App\Modules\Firm\DTOs\ManagerLogin;
use App\Modules\Firm\DTOs\ProvisionedFirm;
use App\Modules\Firm\DTOs\UpdateFirmLookDTO;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\FirmLookService;
use App\Modules\Firm\Services\FirmModulesService;
use App\Modules\Firm\Services\FirmProvisioner;
use App\Modules\Platform\Repositories\PlatformFirmRepository;

/**
 * The operator's console over the firms (rnc-4 R25). It writes nothing itself: a firm is made by
 * `FirmProvisioner` (the same path as `firm:create`), its look by `FirmLookService`, its modules by
 * `FirmModulesService` and its manager login by `FirmProvisioner::provideManager` — each the Firm
 * module's only writer of what it writes. The firm is the one the route names, by id; null when there
 * is none, for the controller's 404.
 */
class PlatformFirmService
{
    public function __construct(
        private PlatformFirmRepository $platformFirmRepository,
        private FirmProvisioner $firmProvisioner,
        private FirmLookService $firmLookService,
        private FirmModulesService $firmModulesService,
    ) {}

    /** @return list<array{firm: Firm, lead_count: int}> */
    public function list(): array
    {
        $counts = $this->platformFirmRepository->countLeadsByFirm();

        return array_map(
            fn (Firm $firm): array => ['firm' => $firm, 'lead_count' => $counts[$firm->id] ?? 0],
            $this->platformFirmRepository->findAllFirms(),
        );
    }

    public function create(CreateFirmDTO $dto, User $operator): ProvisionedFirm
    {
        return $this->firmProvisioner->provision($dto, $operator);
    }

    /** @return array{firm: Firm, lead_count: int}|null */
    public function updateLook(int $id, UpdateFirmLookDTO $changes): ?array
    {
        $firm = $this->platformFirmRepository->findFirmById($id);

        return ! $firm ? null : $this->withCount($this->firmLookService->update($firm, $changes));
    }

    /**
     * @param  list<string>  $modules  the whole opt-in list (R18)
     * @return array{firm: Firm, lead_count: int}|null
     */
    public function setModules(int $id, array $modules, User $operator): ?array
    {
        $firm = $this->platformFirmRepository->findFirmById($id);

        return ! $firm ? null : $this->withCount($this->firmModulesService->set($firm, $modules, $operator));
    }

    public function provideManager(int $id, FirmManagerDTO $dto): ?ManagerLogin
    {
        $firm = $this->platformFirmRepository->findFirmById($id);

        return ! $firm ? null : $this->firmProvisioner->provideManager($firm, $dto);
    }

    /** @return array{firm: Firm, lead_count: int} */
    public function withCount(Firm $firm): array
    {
        return ['firm' => $firm, 'lead_count' => $this->platformFirmRepository->countLeadsOfFirm($firm->id)];
    }
}
