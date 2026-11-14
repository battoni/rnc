<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Models\LeadEvent;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * A49 (rnc-4 R40, F9a): `firm:offboard <slug> --confirm` — a firm leaving the platform. It first
 * writes the firm's data (the firm, its logins, every lead with its consents and timeline) to a JSON
 * file on the private disk, then anonymises every lead (A48's rules), then closes the firm: its host
 * is 404 and its people are refused, on its host and at the root. Without `--confirm` it only says
 * what it would do; the platform firm and an unknown slug are refused; nothing of another firm moves.
 */
const OFFBOARD_PASSWORD = 'segredo-de-teste-1234';

/** @param  array<string, mixed>  $options */
function offboard(string $slug, array $options = ['--confirm' => true]): array
{
    $exitCode = Artisan::call('firm:offboard', ['slug' => $slug, ...$options]);

    return [$exitCode, Artisan::output()];
}

function offboardLogin(string $username, ?string $firmHeader): TestResponse
{
    auth()->forgetGuards();
    app()->forgetInstance('auth.driver');
    $request = test()->flushHeaders();

    if ($firmHeader !== null) {
        $request = $request->withHeader('X-Firm', $firmHeader);
    }

    return $request->postJson('/api/auth/login', ['username' => $username, 'password' => OFFBOARD_PASSWORD]);
}

beforeEach(function () {
    Storage::fake('local');
    config(['firms.default_slug' => PlatformFirmSeeder::PLATFORM_SLUG]);
    $this->seed([FirmSeeder::class, PlatformFirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->firstOrFail();
    $this->acme = Firm::factory()->create(['slug' => 'acme', 'name' => 'Acme Contábil']);
    $this->acmeManager = User::factory()->create([
        'username' => 'helena.acme',
        'firm_id' => $this->acme->id,
        'role_id' => Role::where('slug', 'gestao')->value('id'),
        'password' => Hash::make(OFFBOARD_PASSWORD),
    ]);
    $this->acmeLead = Lead::factory()->create(['firm_id' => $this->acme->id, 'stage' => 'lead', 'contact_name' => 'Ana Acme', 'email' => 'ana@acme.exemplo', 'cnpj' => '12345678000195']);
    LeadConsent::query()->create(['lead_id' => $this->acmeLead->id, 'text' => 'Autorizo a Acme.', 'version' => 1, 'given' => true, 'via' => 'formulario', 'given_at' => now()]);
    $this->francoLead = Lead::factory()->create(['firm_id' => $this->franco->id, 'email' => 'cliente@franco.exemplo']);
});

describe('what it refuses (A49, negatives first)', function () {
    it('changes nothing without --confirm, and says what it would do', function () {
        [$exitCode, $output] = offboard('acme', []);

        expect($exitCode)->not->toBe(0)
            ->and($output)->toContain('--confirm')
            ->and($this->acme->fresh()->offboarded_at)->toBeNull()
            ->and($this->acmeLead->fresh()->email)->toBe('ana@acme.exemplo')
            ->and(Storage::disk('local')->allFiles('offboarding'))->toBe([]);
    });

    it('refuses the platform firm', function () {
        [$exitCode] = offboard(PlatformFirmSeeder::PLATFORM_SLUG);

        expect($exitCode)->not->toBe(0)
            ->and(Firm::where('slug', PlatformFirmSeeder::PLATFORM_SLUG)->value('offboarded_at'))->toBeNull()
            ->and(Storage::disk('local')->allFiles('offboarding'))->toBe([]);
    });

    it('refuses an unknown slug', function () {
        [$exitCode] = offboard('nao-existe');

        expect($exitCode)->not->toBe(0);
    });

    it('refuses a firm already offboarded, writing no second file', function () {
        offboard('acme');
        [$exitCode] = offboard('acme');

        expect($exitCode)->not->toBe(0)
            ->and(Storage::disk('local')->allFiles('offboarding'))->toHaveCount(1);
    });

    it("leaves every other firm's leads and people untouched", function () {
        offboard('acme');

        expect($this->francoLead->fresh()->email)->toBe('cliente@franco.exemplo')
            ->and($this->francoLead->fresh()->anonymised_at)->toBeNull()
            ->and($this->franco->fresh()->offboarded_at)->toBeNull();
        $this->withHeader('X-Firm', FirmSeeder::FRANCO_SLUG)->getJson('/api/public/firm')->assertOk();
    });
});

describe('the exit (A49)', function () {
    it("writes the firm's data to the private disk first: the firm, its logins, its leads with consents", function () {
        [$exitCode, $output] = offboard('acme');

        expect($exitCode)->toBe(0);
        $files = Storage::disk('local')->allFiles('offboarding');
        expect($files)->toHaveCount(1)
            ->and($output)->toContain($files[0]);

        $export = json_decode(Storage::disk('local')->get($files[0]), true);
        expect($export['firm']['slug'])->toBe('acme')
            ->and(array_column($export['users'], 'username'))->toBe(['helena.acme'])
            ->and($export['leads'])->toHaveCount(1)
            ->and($export['leads'][0]['lead']['email'])->toBe('ana@acme.exemplo')
            ->and($export['leads'][0]['consents'][0]['text'])->toBe('Autorizo a Acme.')
            ->and(json_encode($export))->not->toContain('cliente@franco.exemplo')
            ->and(json_encode($export))->not->toContain('password');
    });

    it('anonymises every lead of the firm, keeping the rows and their consents', function () {
        offboard('acme');

        $row = Lead::withoutGlobalScopes()->find($this->acmeLead->id);
        expect($row->only(['contact_name', 'email', 'cnpj']))->toBe(['contact_name' => null, 'email' => null, 'cnpj' => null])
            ->and($row->anonymised_at)->not->toBeNull()
            ->and(LeadConsent::query()->where('lead_id', $row->id)->count())->toBe(1)
            ->and(LeadEvent::query()->where('lead_id', $row->id)->where('type', 'dados.anonimizados')->count())->toBe(1);
    });

    it("answers 404 for the firm's host afterwards", function () {
        $this->withHeader('X-Firm', 'acme')->getJson('/api/public/firm')->assertOk();

        offboard('acme');

        $this->withHeader('X-Firm', 'acme')->getJson('/api/public/firm')->assertNotFound();
        $this->withHeader('X-Firm', 'acme')->getJson('/api/public/privacy')->assertNotFound();
    });

    it('refuses its manager: the login on its host and at the root, and a session already open', function () {
        offboardLogin('helena.acme', 'acme')->assertOk();
        $this->getJson('/api/auth/me')->assertOk();

        offboard('acme');

        $this->flushHeaders()->getJson('/api/auth/me')->assertForbidden();
        expect(offboardLogin('helena.acme', 'acme')->status())->toBe(404)
            ->and(offboardLogin('helena.acme', null)->status())->toBe(422);
    });

    it('keeps the firm row (the offboarding date written) for the records', function () {
        offboard('acme');

        expect($this->acme->fresh())->not->toBeNull()
            ->and($this->acme->fresh()->offboarded_at)->not->toBeNull();
    });
});
