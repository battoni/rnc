<?php

use App\Models\User;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoFirmSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialistSeeder;

/*
 * rnc-4 F1a — the platform firm (D3) and the minimal demo firm (D18; plan AM7).
 *
 * `hub` is a row in every environment: the root and `www` serve it, and the operator works in it.
 * Alvorada is invented demo data, so it is seeded only on a developer's machine — never in the
 * Pest suite and never in production.
 */
describe('PlatformFirmSeeder', function () {
    it('seeds the platform firm, named after the platform, once', function () {
        config(['platform.name' => 'Outro Hub']);

        $this->seed(PlatformFirmSeeder::class);
        $this->seed(PlatformFirmSeeder::class);

        $hub = Firm::where('slug', 'hub')->sole();
        expect($hub->is_platform)->toBeTrue()
            ->and($hub->name)->toBe('Outro Hub')
            ->and(Firm::where('is_platform', true)->count())->toBe(1);
    });

    it('runs in production too', function () {
        app()->detectEnvironment(fn () => 'production');

        app(PlatformFirmSeeder::class)->run();

        expect(Firm::where('slug', 'hub')->exists())->toBeTrue();
    });

    it('is part of the default seed, and Franco is not the platform', function () {
        $this->seed(DatabaseSeeder::class);

        expect(Firm::where('slug', 'hub')->value('is_platform'))->toBeTrue()
            ->and(Firm::where('slug', 'franco')->value('is_platform'))->toBeFalse();
    });
});

describe('DemoFirmSeeder', function () {
    beforeEach(fn () => $this->seed([FirmSeeder::class, RoleSeeder::class]));

    it('seeds alvorada with its manager and a specialist with hours on a local machine', function () {
        app()->detectEnvironment(fn () => 'local');

        $this->seed([DemoFirmSeeder::class, SpecialistSeeder::class]);

        $alvorada = Firm::where('slug', 'alvorada')->sole();
        $helena = User::where('username', 'helena.alvorada')->sole();
        $bruno = User::where('username', 'bruno.alvorada')->sole();

        expect($alvorada->name)->toBe('Alvorada Contabilidade')
            ->and($alvorada->brand_primary)->toBe('#0f5c4d')
            ->and($alvorada->brand_accent)->toBe('#f4b63f')
            ->and($alvorada->is_platform)->toBeFalse()
            ->and($helena->firm_id)->toBe($alvorada->id)
            ->and($helena->role->slug)->toBe('ceo')
            ->and($bruno->firm_id)->toBe($alvorada->id)
            ->and($bruno->is_specialist)->toBeTrue()
            ->and(Specialist::withoutGlobalScopes()->where('user_id', $bruno->id)->first()?->hours()->count())->toBeGreaterThan(0);
    });

    it('seeds nothing outside a local machine', function (string $environment) {
        app()->detectEnvironment(fn () => $environment);

        app(DemoFirmSeeder::class)->run();

        expect(Firm::where('slug', 'alvorada')->exists())->toBeFalse()
            ->and(User::where('username', 'helena.alvorada')->exists())->toBeFalse();
    })->with(['testing', 'production', 'staging']);
});
