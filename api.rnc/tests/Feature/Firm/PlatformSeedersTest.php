<?php

use App\Models\User;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\PlatformSettings;
use App\Modules\Lead\Models\FirmConsentVersion;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Services\ConsentTextService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoFirmSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\PipelineSeeder;
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

    it("gives the platform firm the beta signup's consent as its version 2, once", function () {
        $this->seed(PlatformFirmSeeder::class);
        $this->seed(PlatformFirmSeeder::class);

        $hub = Firm::where('slug', 'hub')->sole();
        $versions = FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $hub->id)->orderBy('version')->get();

        expect($versions->pluck('version')->all())->toBe([1, 2])
            ->and($versions[0]->text)->toBe(app(PlatformSettings::class)->consentDefault())
            ->and($versions[1]->text)->toBe(PlatformFirmSeeder::BETA_CONSENT)
            ->and(PlatformFirmSeeder::BETA_CONSENT)->toContain('{escritorio}')
            ->and(PlatformFirmSeeder::BETA_CONSENT)->toContain('beta')
            ->and(app(ConsentTextService::class)->forFirm($hub)['text'])->toContain($hub->name);
    });

    it("never stacks the beta consent over the operator's own words on a re-seed", function () {
        $this->seed(PlatformFirmSeeder::class);
        $hub = Firm::where('slug', 'hub')->sole();
        app(ConsentTextService::class)->createVersion($hub->id, 'Autorizo {escritorio} a falar comigo.', null);

        $this->seed(PlatformFirmSeeder::class);

        expect(FirmConsentVersion::withoutGlobalScopes()->where('firm_id', $hub->id)->orderBy('version')->pluck('version')->all())->toBe([1, 2, 3])
            ->and(app(ConsentTextService::class)->currentVersion($hub->id)->text)->toBe('Autorizo {escritorio} a falar comigo.');
    });

    it("keeps the operator's edits on a re-seed and only enforces is_platform (verify P1-C1)", function () {
        $this->seed(PlatformFirmSeeder::class);
        Firm::where('slug', 'hub')->sole()->forceFill([
            'brand_primary' => '#123456',
            'modules' => ['chat'],
            'phone' => '11 4000-0000',
            'mailbox' => 'contato@hub.exemplo',
            'is_platform' => false,
        ])->save();

        $this->seed(PlatformFirmSeeder::class);

        $hub = Firm::where('slug', 'hub')->sole();
        expect($hub->brand_primary)->toBe('#123456')
            ->and($hub->modules)->toBe(['chat'])
            ->and($hub->phone)->toBe('11 4000-0000')
            ->and($hub->mailbox)->toBe('contato@hub.exemplo')
            ->and($hub->is_platform)->toBeTrue();
    });

    it('runs in production too', function () {
        app()->detectEnvironment(fn () => 'production');

        app()->call([app(PlatformFirmSeeder::class), 'run']);

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
            ->and($helena->role->slug)->toBe('gestao')
            ->and($bruno->firm_id)->toBe($alvorada->id)
            ->and($bruno->is_specialist)->toBeTrue()
            ->and(Specialist::withoutGlobalScopes()->where('user_id', $bruno->id)->first()?->hours()->count())->toBeGreaterThan(0);
    });

    it('seeds nothing outside a local machine', function (string $environment) {
        app()->detectEnvironment(fn () => $environment);

        app()->call([app(DemoFirmSeeder::class), 'run']);

        expect(Firm::where('slug', 'alvorada')->exists())->toBeFalse()
            ->and(User::where('username', 'helena.alvorada')->exists())->toBeFalse();
    })->with(['testing', 'production', 'staging']);
});

describe('the demo seeders outside a developer machine (verify P2-C2)', function () {
    beforeEach(fn () => $this->seed([FirmSeeder::class, RoleSeeder::class]));

    it('write nothing on staging or production: no team, no bookable hours, no invented leads', function (string $environment) {
        $franco = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->sole();
        $real = User::factory()->create(['firm_id' => $franco->id, 'is_specialist' => true]);
        app()->detectEnvironment(fn () => $environment);

        foreach ([LocalUsersSeeder::class, SpecialistSeeder::class, PipelineSeeder::class] as $seeder) {
            app()->call([app($seeder), 'run']);
        }

        expect(User::where('username', 'patricia')->exists())->toBeFalse()
            ->and(Specialist::withoutGlobalScopes()->where('user_id', $real->id)->exists())->toBeFalse()
            ->and(Lead::withoutGlobalScopes()->count())->toBe(0);
    })->with(['staging', 'production']);
});
