<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Repositories\FirmRepository;
use App\Modules\Firm\Services\FirmModulesService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
 * A19 (rnc-4 R16, R18; D15, D16): a firm's opt-in modules are written through
 * `FirmModulesService::set` only. Core modules are code — never stored, always on. `radar`,
 * `contracts` and `fiscal` need `portfolio` (422 `modules.missingDependency`); an unknown key is
 * 422 `modules.unknown`; turning `channel` on or off is the platform operator's alone (403).
 */
uses(TestCase::class);

function modulesActor(string $roleSlug): User
{
    $user = new User;
    $user->setRelation('role', new Role(['slug' => $roleSlug]));

    return $user;
}

/** @param list<string> $modules */
function modulesFirm(array $modules = []): Firm
{
    return new Firm(['modules' => $modules]);
}

/** The service over a repository that hands back what it was asked to store. */
function modulesService(?array &$stored = null): FirmModulesService
{
    $repository = Mockery::mock(FirmRepository::class);
    $repository->shouldReceive('updateFirmModules')->andReturnUsing(function (Firm $firm, array $modules) use (&$stored) {
        $stored = $modules;
        $firm->modules = $modules;

        return $firm;
    });

    return new FirmModulesService($repository);
}

function modulesErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

describe('dependencies (R18)', function () {
    it('refuses radar, contracts or fiscal without portfolio with modules.missingDependency', function (string $module) {
        $service = modulesService();

        expect(modulesErrors(fn () => $service->set(modulesFirm(), [$module], modulesActor('operador'))))
            ->toBe(['modules' => ['modules.missingDependency']]);
    })->with(['radar', 'contracts', 'fiscal']);

    it('stores radar beside portfolio, in the catalogue order, once each', function () {
        $service = modulesService($stored);

        $firm = $service->set(modulesFirm(), ['radar', 'briefing', 'portfolio', 'radar'], modulesActor('gestao'));

        expect($stored)->toBe(['portfolio', 'radar', 'briefing'])
            ->and($firm->modules)->toBe(['portfolio', 'radar', 'briefing']);
    });

    it('refuses an unknown key with modules.unknown', function () {
        expect(modulesErrors(fn () => modulesService()->set(modulesFirm(), ['crm'], modulesActor('operador'))))
            ->toBe(['modules' => ['modules.unknown']]);
    });

    it('never stores a core module — core is always on', function () {
        $service = modulesService($stored);

        $service->set(modulesFirm(), ['pipeline', 'diagnostic', 'briefing'], modulesActor('operador'));

        expect($stored)->toBe(['briefing']);
    });
});

describe('channel is the operator’s (R18)', function () {
    it('refuses a manager turning channel on with 403', function () {
        expect(fn () => modulesService()->set(modulesFirm(), ['channel'], modulesActor('gestao')))
            ->toThrow(AuthorizationException::class);
    });

    it('refuses a manager turning channel off with 403', function () {
        expect(fn () => modulesService()->set(modulesFirm(['channel', 'briefing']), ['briefing'], modulesActor('gestao')))
            ->toThrow(AuthorizationException::class);
    });

    it('lets a manager change other modules while channel stays as it was', function () {
        $service = modulesService($stored);

        $service->set(modulesFirm(['channel']), ['channel', 'briefing'], modulesActor('gestao'));

        expect($stored)->toBe(['briefing', 'channel']);
    });

    it('lets the operator turn channel on', function () {
        $service = modulesService($stored);

        $service->set(modulesFirm(), ['channel'], modulesActor('operador'));

        expect($stored)->toBe(['channel']);
    });
});

describe('reading a firm’s modules (R16)', function () {
    it('has every core module on whatever the firm stored', function () {
        expect(FirmModules::isEnabled(modulesFirm(), 'pipeline'))->toBeTrue()
            ->and(FirmModules::isEnabled(modulesFirm(), 'diagnostic'))->toBeTrue();
    });

    it('has an opt-in module on only when stored, and an unknown one never', function () {
        $firm = modulesFirm(['partners']);

        expect(FirmModules::isEnabled($firm, 'partners'))->toBeTrue()
            ->and(FirmModules::isEnabled($firm, 'radar'))->toBeFalse()
            ->and(FirmModules::isEnabled($firm, 'crm'))->toBeFalse();
    });

    it('answers the stored opt-in keys that still exist, in the catalogue order', function () {
        expect(FirmModules::enabled(modulesFirm(['briefing', 'gone', 'partners'])))->toBe(['partners', 'briefing']);
    });
});
