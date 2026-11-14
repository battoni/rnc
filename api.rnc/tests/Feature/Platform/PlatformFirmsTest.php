<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * rnc-4 F4a — A27 (R25, R11, R18). The operator's console API: list the firms with their lead
 * counts, create one through `FirmProvisioner`, edit its look (white on the primary ≥ 4,5:1), set
 * its modules through `FirmModulesService`, and create or reset its manager's login — the password
 * answered once. Every `platform/*` route refuses anyone but the operator (403), and the firm a
 * route names is the one it touches, never the caller's.
 */
beforeEach(function () {
    config(['firms.default_slug' => PlatformFirmSeeder::PLATFORM_SLUG]);

    $this->seed([FirmSeeder::class, PlatformFirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->firstOrFail();
    $this->hub = Firm::where('slug', PlatformFirmSeeder::PLATFORM_SLUG)->firstOrFail();
    $this->operator = User::factory()->create([
        'firm_id' => null,
        'username' => 'operador.console',
        'role_id' => Role::where('slug', 'operador')->value('id'),
    ]);
});

function platformPerson(string $role, Firm $firm): User
{
    return User::factory()->create(['firm_id' => $firm->id, 'role_id' => Role::where('slug', $role)->value('id')]);
}

function platformSignInAt(string $username, string $password, ?string $firmHeader): TestResponse
{
    auth()->forgetGuards();
    $request = test()->flushHeaders();

    if ($firmHeader !== null) {
        $request = $request->withHeader('X-Firm', $firmHeader);
    }

    return $request->postJson('/api/auth/login', ['username' => $username, 'password' => $password]);
}

describe('GET platform/firms', function () {
    it('lists every firm with its lead count, counted across firms in one query', function () {
        Lead::factory()->count(3)->create(['firm_id' => $this->franco->id]);
        Lead::factory()->create(['firm_id' => $this->hub->id]);

        DB::enableQueryLog();
        $firms = collect($this->actingAs($this->operator)->getJson('/api/platform/firms')->assertOk()->json('data'))->keyBy('slug');
        // SQLite quotes `"leads"`, MySQL `` `leads` ``.
        $leadQueries = collect(DB::getQueryLog())->filter(fn (array $query) => preg_match('/["`]leads["`]/', $query['query']) === 1);

        expect($firms->keys()->all())->toContain('franco', 'hub')
            ->and($firms['franco']['lead_count'])->toBe(3)
            ->and($firms['hub']['lead_count'])->toBe(1)
            ->and($firms['hub']['is_platform'])->toBeTrue()
            ->and($firms['franco']['modules'])->toContain('briefing')
            ->and($firms['franco']['site_url'])->toContain('franco.')
            ->and($firms['franco'])->toHaveKeys(['id', 'name', 'short_name', 'logo_text', 'brand_primary', 'brand_accent', 'created_at'])
            ->and($leadQueries)->toHaveCount(1);
    });
});

describe('POST platform/firms', function () {
    it('creates a firm reachable by its host, with its manager and a password once', function () {
        $response = $this->actingAs($this->operator)->postJson('/api/platform/firms', [
            'slug' => 'ipe',
            'name' => 'Ipê Contábil',
            'manager' => 'gestao.ipe',
            'email' => 'contato@ipe.exemplo',
        ])->assertCreated()
            ->assertJsonPath('data.firm.slug', 'ipe')
            ->assertJsonPath('data.firm.modules', ['briefing'])
            ->assertJsonPath('data.manager.username', 'gestao.ipe');

        $password = $response->json('data.password');
        expect($password)->toBeString()->toHaveLength(16);

        $this->flushHeaders();
        auth()->forgetGuards();
        $this->withHeader('X-Firm', 'ipe')->getJson('/api/public/firm')->assertOk()->assertJsonPath('data.name', 'Ipê Contábil');

        platformSignInAt('gestao.ipe', $password, 'ipe')->assertOk()->assertJsonPath('data.firm.slug', 'ipe');
    });

    it('refuses a reserved, malformed or taken slug with the reason', function (string $slug, string $key) {
        $this->actingAs($this->operator)->postJson('/api/platform/firms', ['slug' => $slug, 'name' => 'Firma', 'manager' => 'gestao.nova'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.slug.0', $key);

        expect(User::where('username', 'gestao.nova')->exists())->toBeFalse();
    })->with([
        'reserved' => ['www', 'firms.slugReserved'],
        'platform' => ['hub', 'firms.slugReserved'],
        'malformed' => ['Ipê Contábil', 'firms.slugInvalid'],
        'taken' => ['franco', 'firms.slugTaken'],
    ]);

    it('puts the new manager in the new firm, nowhere else', function () {
        $this->actingAs($this->operator)->postJson('/api/platform/firms', ['slug' => 'ipe', 'name' => 'Ipê', 'manager' => 'gestao.ipe'])->assertCreated();

        expect(Firm::where('slug', 'ipe')->value('id'))->not->toBeNull()
            ->and(User::where('username', 'gestao.ipe')->value('firm_id'))->toBe(Firm::where('slug', 'ipe')->value('id'));
    });
});

describe('PATCH platform/firms/{id}', function () {
    it("edits another firm's look, and only that firm's", function () {
        $this->actingAs($this->operator)->patchJson("/api/platform/firms/{$this->franco->id}", [
            'short_name' => 'Franco C.',
            'tagline' => 'Contabilidade para a Reforma',
            'brand_primary' => '#1F4E79',
            'proof' => [
                ['value' => '30', 'label' => 'anos'],
                ['value' => '500', 'label' => 'clientes'],
                ['value' => '12', 'label' => 'especialistas'],
                ['value' => '4', 'label' => 'cidades'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.short_name', 'Franco C.')
            ->assertJsonPath('data.brand_primary', '#1f4e79');

        expect($this->franco->fresh()->tagline)->toBe('Contabilidade para a Reforma')
            ->and($this->hub->fresh()->short_name)->not->toBe('Franco C.');
    });

    it('refuses a primary white text cannot read on', function () {
        $before = $this->franco->brand_primary;

        $this->actingAs($this->operator)->patchJson("/api/platform/firms/{$this->franco->id}", ['brand_primary' => '#7fd3ff'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.brand_primary.0', 'brand.lowContrast');

        expect($this->franco->fresh()->brand_primary)->toBe($before);
    });

    it('never touches the slug, the platform flag or the modules', function () {
        $this->actingAs($this->operator)->patchJson("/api/platform/firms/{$this->franco->id}", [
            'slug' => 'roubada', 'is_platform' => true, 'modules' => [], 'name' => 'Franco',
        ])->assertOk();

        $franco = $this->franco->fresh();
        expect($franco->slug)->toBe('franco')->and($franco->is_platform)->toBeFalse()->and($franco->modules)->not->toBe([]);
    });

    it('answers 404 for a firm that does not exist', function () {
        $this->actingAs($this->operator)->patchJson('/api/platform/firms/99999', ['name' => 'X'])->assertNotFound();
    });
});

describe('PUT platform/firms/{id}/modules', function () {
    it('writes the whole opt-in list, the operator may turn channel on', function () {
        $alvorada = Firm::factory()->create(['slug' => 'alvorada']);

        $this->actingAs($this->operator)->putJson("/api/platform/firms/{$alvorada->id}/modules", ['modules' => ['portfolio', 'radar', 'channel', 'pipeline']])
            ->assertOk()
            ->assertJsonPath('data.modules', ['portfolio', 'radar', 'channel']);

        expect($alvorada->fresh()->modules)->toBe(['portfolio', 'radar', 'channel'])
            ->and($this->franco->fresh()->modules)->toContain('briefing');
    });

    it('refuses a module without its dependency, or an unknown one (R18)', function (array $modules, string $key) {
        $this->actingAs($this->operator)->putJson("/api/platform/firms/{$this->franco->id}/modules", ['modules' => $modules])
            ->assertUnprocessable()
            ->assertJsonPath('errors.modules.0', $key);
    })->with([
        'radar without portfolio' => [['radar'], 'modules.missingDependency'],
        'unknown' => [['teleporte'], 'modules.unknown'],
    ]);

    it('answers 404 for a firm that does not exist', function () {
        $this->actingAs($this->operator)->putJson('/api/platform/firms/99999/modules', ['modules' => []])->assertNotFound();
    });
});

describe('POST platform/firms/{id}/manager', function () {
    it("creates the firm's manager in that firm, the password once, and it signs in on its host", function () {
        $response = $this->actingAs($this->operator)->postJson("/api/platform/firms/{$this->franco->id}/manager", ['username' => 'nova.gestao', 'email' => 'nova@franco.exemplo'])
            ->assertCreated()
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.manager.username', 'nova.gestao');

        $manager = User::where('username', 'nova.gestao')->firstOrFail();
        expect($manager->firm_id)->toBe($this->franco->id)->and($manager->role?->slug)->toBe('gestao');

        platformSignInAt('nova.gestao', $response->json('data.password'), 'franco')->assertOk();
    });

    it("resets an existing manager's password: the new one signs in, the old one does not", function () {
        $manager = platformPerson('gestao', $this->franco);

        $response = $this->actingAs($this->operator)->postJson("/api/platform/firms/{$this->franco->id}/manager", ['username' => $manager->username])
            ->assertOk()
            ->assertJsonPath('data.created', false);

        platformSignInAt($manager->username, (string) config('seeding.password'), 'franco')->assertStatus(422);
        platformSignInAt($manager->username, $response->json('data.password'), 'franco')->assertOk();
    });

    it("refuses a username of another firm's account, or a non-manager's, and changes nothing", function (string $whose) {
        $alvorada = Firm::factory()->create(['slug' => 'alvorada']);
        $person = $whose === 'other firm' ? platformPerson('gestao', $alvorada) : platformPerson('equipe', $this->franco);
        $hash = $person->password;

        $this->actingAs($this->operator)->postJson("/api/platform/firms/{$this->franco->id}/manager", ['username' => $person->username])
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'users.form.usernameTaken');

        expect($person->fresh()->password)->toBe($hash)->and($person->fresh()->firm_id)->toBe($person->firm_id);
    })->with(['other firm', 'not a manager']);

    it('answers 404 for a firm that does not exist', function () {
        $this->actingAs($this->operator)->postJson('/api/platform/firms/99999/manager', ['username' => 'ninguem'])->assertNotFound();
        expect(User::where('username', 'ninguem')->exists())->toBeFalse();
    });
});

describe('who reaches platform/*', function () {
    it("refuses Franco's manager on every route, its own firm included, and writes nothing", function () {
        $manager = platformPerson('gestao', $this->franco);
        $name = $this->franco->name;

        $this->actingAs($manager)->getJson('/api/platform/firms')->assertForbidden();
        $this->actingAs($manager)->postJson('/api/platform/firms', ['slug' => 'ipe', 'name' => 'Ipê', 'manager' => 'gestao.ipe'])->assertForbidden();
        $this->actingAs($manager)->patchJson("/api/platform/firms/{$this->franco->id}", ['name' => 'Roubada'])->assertForbidden();
        $this->actingAs($manager)->patchJson("/api/platform/firms/{$this->hub->id}", ['name' => 'Roubada'])->assertForbidden();
        $this->actingAs($manager)->putJson("/api/platform/firms/{$this->franco->id}/modules", ['modules' => ['channel']])->assertForbidden();
        $this->actingAs($manager)->postJson("/api/platform/firms/{$this->franco->id}/manager", ['username' => $manager->username])->assertForbidden();

        expect(Firm::where('slug', 'ipe')->exists())->toBeFalse()
            ->and($this->franco->fresh()->name)->toBe($name);
    });

    it('refuses the operator on a firm host header (it works at the root only)', function () {
        $this->actingAs($this->operator)->withHeader('X-Firm', 'franco')->getJson('/api/platform/firms')
            ->assertForbidden()
            ->assertJsonPath('error', 'firms.mismatch');
    });
});
