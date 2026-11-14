<?php

use App\Models\User;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use App\Modules\Fiscal\Models\NfeDocument;
use App\Modules\Fiscal\Repositories\NfeDocumentRepository;
use App\Modules\Lead\Models\Lead;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Models\ClientContract;
use App\Modules\Tracking\Models\Visitor;
use Database\Seeders\ClientSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoFirmSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PartnerSeeder;
use Database\Seeders\PartnerUserSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\PlatformManagerSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * rnc-4 F3b2 (A23; R22, R23; plan AM7) — the local seed: Franco's demo data unchanged, the invented
 * firm Alvorada with a demo of its own on every module it has on, and the platform firm's manager.
 *
 * The suite's own seed runs as `testing` and carries none of it (`SeedShapeTest`): here the
 * environment is switched to `local` explicitly, and the NF-e disk is faked — `NfeSeeder` clears a
 * firm's folder before it imports, and a test must never touch a developer's files.
 */
function seedLocally(): void
{
    Storage::fake(NfeDocumentRepository::DISK);
    app()->detectEnvironment(fn () => 'local');

    test()->seed(DatabaseSeeder::class);
}

/** A model's rows of one firm, past `FirmScope`. */
function rowsOf(string $model, Firm $firm): int
{
    return $model::withoutGlobalScopes()->where('firm_id', $firm->id)->count();
}

describe('the local seed (A23)', function () {
    it('gives Alvorada a demo on every module it has on, and leaves Franco as it was', function () {
        seedLocally();

        $franco = Firm::where('slug', 'franco')->sole();
        $alvorada = Firm::where('slug', 'alvorada')->sole();

        expect(rowsOf(Lead::class, $alvorada))->toBeGreaterThanOrEqual(40)
            ->and(rowsOf(Appointment::class, $alvorada))->toBeGreaterThanOrEqual(3)
            ->and(rowsOf(Referral::class, $alvorada))->toBeGreaterThan(0)
            ->and(rowsOf(Visitor::class, $alvorada))->toBeGreaterThan(rowsOf(Lead::class, $alvorada))
            ->and(rowsOf(Client::class, $alvorada))->toBeGreaterThanOrEqual(200)
            ->and(rowsOf(ClientContract::class, $alvorada))->toBeGreaterThan(0)
            ->and(rowsOf(NfeDocument::class, $alvorada))->toBeGreaterThan(0)
            ->and(AssistantNote::withoutGlobalScopes()->where('firm_id', $alvorada->id)->whereNotNull('reviewed_at')->count())->toBeGreaterThan(0)
            // Franco's demo is the rnc-1/rnc-3 one, row for row.
            ->and(rowsOf(Lead::class, $franco))->toBe(40)
            ->and(rowsOf(Client::class, $franco))->toBe(ClientSeeder::COUNT)
            ->and(rowsOf(Partner::class, $franco))->toBe(8);

        // Every row stays in its firm: a lead's meeting, a referral's partner, a contract's client.
        expect(Appointment::withoutGlobalScopes()->where('firm_id', $alvorada->id)->whereHas('lead', fn ($query) => $query->withoutGlobalScopes()->where('firm_id', '!=', $alvorada->id))->count())->toBe(0)
            ->and(Referral::withoutGlobalScopes()->where('firm_id', $alvorada->id)->whereHas('partner', fn ($query) => $query->withoutGlobalScopes()->where('firm_id', '!=', $alvorada->id))->count())->toBe(0)
            ->and(ClientContract::withoutGlobalScopes()->where('firm_id', $alvorada->id)->whereHas('client', fn ($query) => $query->withoutGlobalScopes()->where('firm_id', '!=', $alvorada->id))->count())->toBe(0);

        // Its referral messages name Alvorada, never Franco.
        $messages = Referral::withoutGlobalScopes()->where('firm_id', $alvorada->id)->pluck('message')->filter()->implode(' ');

        expect($messages)->toContain('Alvorada')
            ->and($messages)->not->toContain('Franco');
    });

    it('gives Alvorada its own team, specialists with hours and partner logins', function () {
        seedLocally();

        $alvorada = Firm::where('slug', 'alvorada')->sole();
        $people = User::where('firm_id', $alvorada->id)->with('role')->get()->keyBy('username');

        expect($people->keys()->filter(fn (string $username) => ! str_starts_with($username, 'parceiro.'))->sort()->values()->all())
            ->toBe(['bruno.alvorada', 'camila.alvorada', 'equipe.alvorada', 'helena.alvorada'])
            ->and($people['helena.alvorada']->role->slug)->toBe('gestao')
            ->and($people['equipe.alvorada']->role->slug)->toBe('equipe')
            ->and($people['equipe.alvorada']->is_specialist)->toBeFalse()
            ->and($people['camila.alvorada']->is_specialist)->toBeTrue();

        $specialists = Specialist::withoutGlobalScopes()->where('firm_id', $alvorada->id)->withCount('hours')->get();

        expect($specialists)->toHaveCount(2)
            ->and($specialists->every(fn (Specialist $specialist) => $specialist->hours_count > 0))->toBeTrue();

        $logins = $people->filter(fn (User $user) => $user->partner_id !== null);

        expect($logins->count())->toBeGreaterThanOrEqual(4)
            ->and($logins->keys()->every(fn (string $username) => str_starts_with($username, 'parceiro.alvorada.')))->toBeTrue()
            ->and(Partner::withoutGlobalScopes()->whereIn('id', $logins->pluck('partner_id'))->where('firm_id', '!=', $alvorada->id)->count())->toBe(0);
    });

    it("keeps Alvorada's contact data its own, and hub the one platform firm with a manager", function () {
        seedLocally();

        $franco = Firm::where('slug', 'franco')->sole();
        $alvorada = Firm::where('slug', 'alvorada')->sole();
        $hub = Firm::where('is_platform', true)->sole();
        $manager = User::where('username', PlatformManagerSeeder::USERNAME)->with('role')->sole();

        expect($alvorada->address)->not->toBe($franco->address)
            ->and($alvorada->phone)->not->toBe($franco->phone)
            ->and($alvorada->mailbox)->not->toBe($franco->mailbox)
            ->and($alvorada->modules)->not->toContain('brands')
            ->and($alvorada->modules)->not->toContain('channel')
            ->and($hub->slug)->toBe('hub')
            ->and($hub->modules)->toBe([])
            ->and($manager->firm_id)->toBe($hub->id)
            ->and($manager->role->slug)->toBe('gestao')
            ->and($manager->is_specialist)->toBeFalse();
    });
});

describe('a seeder per firm', function () {
    it('seeds no data of a module the firm has off, and the core data still', function () {
        $this->seed([FirmSeeder::class, RoleSeeder::class]);
        app()->detectEnvironment(fn () => 'local');
        $this->seed(DemoFirmSeeder::class);
        $alvorada = Firm::where('slug', 'alvorada')->sole();
        $alvorada->forceFill(['modules' => []])->save();

        $this->seed([SpecialistSeeder::class, PipelineSeeder::class, PartnerSeeder::class, PartnerUserSeeder::class, ClientSeeder::class]);

        expect(rowsOf(Lead::class, $alvorada))->toBe(40)
            ->and(rowsOf(Partner::class, $alvorada))->toBe(0)
            ->and(User::where('username', 'like', 'parceiro.alvorada.%')->count())->toBe(0)
            ->and(rowsOf(Client::class, $alvorada))->toBe(0)
            ->and(rowsOf(Partner::class, Firm::where('slug', 'franco')->sole()))->toBe(8);
    });
});

describe('PlatformManagerSeeder', function () {
    it('seeds no manager outside a local machine', function (string $environment) {
        $this->seed(DatabaseSeeder::class);
        app()->detectEnvironment(fn () => $environment);

        app()->call([app(PlatformManagerSeeder::class), 'run']);

        expect(User::where('username', PlatformManagerSeeder::USERNAME)->exists())->toBeFalse();
    })->with(['testing', 'production', 'staging']);
});
