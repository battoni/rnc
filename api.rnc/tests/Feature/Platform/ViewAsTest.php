<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Platform\Models\ViewAsSession;
use App\Modules\Platform\Services\ViewAsService;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;

/*
 * rnc-4 F4c — A42 (R26, D20; plan AM1). "Acessar como suporte": the firm-less operator enters ONE
 * firm from the console; while it lasts every request of that session resolves to that firm and
 * `auth/me` carries `platform.view_as`; it ends on "Sair" (`sair`), on logout (`logout`) and, left
 * open, at the operator's next sign-in (`expirou`). Each start is a row (who, which firm, from which
 * IP, started, ended, why), listed by `GET platform/view-as-log`. Security-sensitive (P6): nobody but
 * the firm-less operator starts one or rides one, it never nests, never targets the platform firm,
 * and it grants nothing the viewed firm's modules do not.
 */
beforeEach(function () {
    config(['firms.default_slug' => PlatformFirmSeeder::PLATFORM_SLUG]);

    $this->seed([FirmSeeder::class, PlatformFirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->firstOrFail();
    $this->hub = Firm::where('slug', PlatformFirmSeeder::PLATFORM_SLUG)->firstOrFail();
    $this->alvorada = Firm::factory()->create(['slug' => 'alvorada', 'name' => 'Alvorada Contabilidade']);
    $this->operator = viewAsPerson('operador', null);
    $this->alvoradaLead = Lead::factory()->create(['firm_id' => $this->alvorada->id]);
    $this->francoLead = Lead::factory()->create(['firm_id' => $this->franco->id]);
    $this->hubLead = Lead::factory()->create(['firm_id' => $this->hub->id]);
});

function viewAsPerson(string $role, ?int $firmId): User
{
    return User::factory()->create(['firm_id' => $firmId, 'role_id' => Role::where('slug', $role)->value('id')]);
}

/** @return list<int> the lead ids the pipeline answers this session */
function viewAsLeadIds(): array
{
    return collect(test()->getJson('/api/leads')->assertOk()->json('data.columns'))
        ->flatMap(fn (array $column) => array_column($column['items'], 'id'))
        ->all();
}

function viewAsStart(Firm $firm): TestResponse
{
    return test()->postJson("/api/platform/firms/{$firm->id}/view-as");
}

describe('starting', function () {
    it('logs a row and makes every request that firm\'s, with the flag in me', function () {
        $this->actingAs($this->operator);

        expect(viewAsLeadIds())->toContain($this->hubLead->id)->not->toContain($this->alvoradaLead->id);

        viewAsStart($this->alvorada)
            ->assertCreated()
            ->assertJsonPath('data.firm.slug', 'alvorada')
            ->assertJsonPath('data.firm.name', 'Alvorada Contabilidade')
            ->assertJsonPath('data.ended_at', null);

        $row = ViewAsSession::query()->sole();
        expect($row->user_id)->toBe($this->operator->id)
            ->and($row->firm_id)->toBe($this->alvorada->id)
            ->and($row->ip_address)->toBe('127.0.0.1')
            ->and($row->started_at)->not->toBeNull()
            ->and($row->ended_at)->toBeNull()
            ->and($row->end_reason)->toBeNull();

        $ids = viewAsLeadIds();
        expect($ids)->toContain($this->alvoradaLead->id)
            ->not->toContain($this->hubLead->id)
            ->not->toContain($this->francoLead->id);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.platform.view_as.slug', 'alvorada')
            ->assertJsonPath('data.platform.view_as.name', 'Alvorada Contabilidade')
            ->assertJsonPath('data.platform.is_operator', true);
        $this->getJson('/api/public/firm')->assertOk()->assertJsonPath('data.slug', 'alvorada');
    });

    it('writes into the viewed firm (read and write allowed, D20)', function () {
        $this->actingAs($this->operator);
        viewAsStart($this->alvorada)->assertCreated();

        $this->postJson("/api/leads/{$this->alvoradaLead->id}/notes", ['text' => 'nota do suporte'])->assertSuccessful();
        $this->postJson("/api/leads/{$this->francoLead->id}/notes", ['text' => 'nota do suporte'])->assertNotFound();
    });

    it('cannot be nested: a second start while one is active is 409 and logs nothing', function () {
        $this->actingAs($this->operator);
        viewAsStart($this->alvorada)->assertCreated();

        viewAsStart($this->franco)->assertStatus(409)->assertJsonPath('error', 'platform.viewAsActive');

        expect(ViewAsSession::query()->count())->toBe(1)
            ->and(viewAsLeadIds())->toContain($this->alvoradaLead->id)->not->toContain($this->francoLead->id);
    });

    it('cannot target the platform firm', function () {
        $this->actingAs($this->operator);

        viewAsStart($this->hub)->assertUnprocessable()->assertJsonPath('error', 'platform.viewAsPlatformFirm');

        expect(ViewAsSession::query()->count())->toBe(0);
    });

    it('cannot target an offboarded firm (422 platform.viewAsOffboarded, verify P2-A3)', function () {
        $this->alvorada->forceFill(['offboarded_at' => now()])->save();
        $this->actingAs($this->operator);

        viewAsStart($this->alvorada)
            ->assertUnprocessable()
            ->assertJsonPath('error', 'platform.viewAsOffboarded')
            ->assertJsonPath('code', 'view_as_offboarded');

        expect(ViewAsSession::query()->count())->toBe(0);
    });

    it('falls back to the platform firm when the viewed firm is offboarded mid-visit (verify P2-A3)', function () {
        $this->actingAs($this->operator);
        viewAsStart($this->alvorada)->assertCreated();

        $this->alvorada->forceFill(['offboarded_at' => now()])->save();

        expect(viewAsLeadIds())->toContain($this->hubLead->id)->not->toContain($this->alvoradaLead->id);
        $this->getJson('/api/auth/me')->assertJsonPath('data.platform.view_as', null);
    });

    it('answers 404 for an unknown firm', function () {
        $this->actingAs($this->operator)->postJson('/api/platform/firms/999999/view-as')->assertNotFound()->assertJsonPath('error', 'firms.notFound');

        expect(ViewAsSession::query()->count())->toBe(0);
    });

    it('is refused to a firm user and to an operator placed in a firm (403), logging nothing', function (string $role) {
        $this->actingAs(viewAsPerson($role, $this->franco->id));

        viewAsStart($this->alvorada)->assertForbidden();

        expect(ViewAsSession::query()->count())->toBe(0);
    })->with(['gestao', 'equipe', 'operador']);

    it('still refuses an X-Firm header while viewing (403 firms.mismatch, W9)', function () {
        $this->actingAs($this->operator);
        viewAsStart($this->alvorada)->assertCreated();

        $this->withHeader('X-Firm', 'alvorada')->getJson('/api/leads')->assertForbidden()->assertJsonPath('error', 'firms.mismatch');
    });

    it("grants nothing the viewed firm's modules do not", function () {
        $this->alvorada->update(['modules' => ['briefing']]);
        $this->actingAs($this->operator);
        viewAsStart($this->alvorada)->assertCreated();

        $this->getJson('/api/repricing')->assertNotFound()->assertJsonPath('error', 'modules.disabled');
    });

    it("reports the viewed firm's opt-in modules in me, not the platform firm's (W93)", function () {
        $this->alvorada->update(['modules' => ['partners', 'briefing']]);
        $this->hub->update(['modules' => ['portfolio']]);
        $this->actingAs($this->operator);

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.firm', null)->assertJsonPath('data.platform.view_as', null);

        viewAsStart($this->alvorada)->assertCreated();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.firm', null)
            ->assertJsonPath('data.platform.view_as.slug', 'alvorada')
            ->assertJsonPath('data.platform.view_as.modules', ['partners', 'briefing']);
    });
});

describe('the audit address behind a proxy (F7, F4c Known Gap)', function () {
    it('logs the forwarded client address when the peer is a trusted proxy', function () {
        config(['trustedproxy.proxies' => '10.0.0.5']);
        $this->actingAs($this->operator);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeader('X-Forwarded-For', '203.0.113.7')
            ->postJson("/api/platform/firms/{$this->alvorada->id}/view-as")
            ->assertCreated();

        expect(ViewAsSession::query()->sole()->ip_address)->toBe('203.0.113.7');
    });

    it('ignores X-Forwarded-For from a peer that is not a trusted proxy', function () {
        config(['trustedproxy.proxies' => '10.0.0.5']);
        $this->actingAs($this->operator);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->withHeader('X-Forwarded-For', '203.0.113.7')
            ->postJson("/api/platform/firms/{$this->alvorada->id}/view-as")
            ->assertCreated();

        expect(ViewAsSession::query()->sole()->ip_address)->toBe('198.51.100.9');
    });
});

describe('ending', function () {
    it('closes on Sair with reason sair, and the next request is the platform firm\'s again', function () {
        $this->actingAs($this->operator);
        viewAsStart($this->alvorada)->assertCreated();

        $this->deleteJson('/api/platform/view-as')
            ->assertOk()
            ->assertJsonPath('data.firm.slug', 'alvorada')
            ->assertJsonPath('data.end_reason', 'sair');

        $row = ViewAsSession::query()->sole();
        expect($row->ended_at)->not->toBeNull()
            ->and($row->end_reason->value)->toBe('sair')
            ->and(session()->has(ViewAsService::SESSION_KEY))->toBeFalse()
            ->and(viewAsLeadIds())->toContain($this->hubLead->id)->not->toContain($this->alvoradaLead->id);
        $this->getJson('/api/auth/me')->assertJsonPath('data.platform.view_as', null);
    });

    it('answers 200 with null when nothing is active (Sair twice)', function () {
        $this->actingAs($this->operator)->deleteJson('/api/platform/view-as')->assertOk()->assertJsonPath('data', null);
    });

    it('closes on logout with reason logout', function () {
        $this->actingAs($this->operator);
        viewAsStart($this->alvorada)->assertCreated();

        $this->postJson('/api/auth/logout')->assertOk();

        $row = ViewAsSession::query()->sole();
        expect($row->ended_at)->not->toBeNull()
            ->and($row->end_reason->value)->toBe('logout');
    });

    it('never lends the viewed firm to a sign-in at the root, and ends the view-as as logout (verify P2-A4)', function () {
        $alvoradaManager = viewAsPerson('gestao', $this->alvorada->id);
        $this->actingAs($this->operator);
        viewAsStart($this->alvorada)->assertCreated();

        $this->postJson('/api/auth/login', ['username' => $alvoradaManager->username, 'password' => (string) config('seeding.password')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'auth.login.invalidCredentials');

        $row = ViewAsSession::query()->sole();
        expect($row->ended_at)->not->toBeNull()
            ->and($row->end_reason->value)->toBe('logout');
    });

    it("signs in on a firm host by the host's header, not the operator's session (verify P2-A4)", function () {
        $alvoradaManager = viewAsPerson('gestao', $this->alvorada->id);
        $this->actingAs($this->operator);
        viewAsStart($this->alvorada)->assertCreated();

        $this->withHeader('X-Firm', 'alvorada')
            ->postJson('/api/auth/login', ['username' => $alvoradaManager->username, 'password' => (string) config('seeding.password')])
            ->assertOk()
            ->assertJsonPath('data.username', $alvoradaManager->username);

        expect(ViewAsSession::query()->sole()->end_reason?->value)->toBe('logout');
    });

    it('closes a session left open with reason expirou at the operator\'s next sign-in', function () {
        $stale = ViewAsSession::query()->create([
            'user_id' => $this->operator->id,
            'firm_id' => $this->alvorada->id,
            'ip_address' => '10.0.0.1',
            'started_at' => now()->subDay(),
        ]);
        $other = viewAsPerson('operador', null);
        $othersOpen = ViewAsSession::query()->create([
            'user_id' => $other->id,
            'firm_id' => $this->franco->id,
            'ip_address' => '10.0.0.2',
            'started_at' => now()->subHour(),
        ]);

        $this->postJson('/api/auth/login', ['username' => $this->operator->username, 'password' => (string) config('seeding.password')])
            ->assertOk()
            ->assertJsonPath('data.platform.view_as', null);

        expect($stale->fresh()->end_reason?->value)->toBe('expirou')
            ->and($stale->fresh()->ended_at)->not->toBeNull()
            ->and($othersOpen->fresh()->ended_at)->toBeNull();
    });
});

describe('never inherited', function () {
    beforeEach(function () {
        $this->openRow = ViewAsSession::query()->create([
            'user_id' => $this->operator->id,
            'firm_id' => $this->alvorada->id,
            'ip_address' => '127.0.0.1',
            'started_at' => now(),
        ]);
    });

    it("rides the operator's own open row", function () {
        $this->withSession([ViewAsService::SESSION_KEY => $this->openRow->id])->actingAs($this->operator);

        expect(viewAsLeadIds())->toContain($this->alvoradaLead->id);
    });

    it('is ignored for a firm user whose session carries the key', function (string $role) {
        $this->withSession([ViewAsService::SESSION_KEY => $this->openRow->id])->actingAs(viewAsPerson($role, $this->franco->id));

        expect(viewAsLeadIds())->toContain($this->francoLead->id)->not->toContain($this->alvoradaLead->id);
        $this->getJson('/api/auth/me')->assertJsonPath('data.platform.view_as', null);
    })->with(['gestao', 'operador']);

    it("is ignored for another operator riding someone else's row", function () {
        $this->withSession([ViewAsService::SESSION_KEY => $this->openRow->id])->actingAs(viewAsPerson('operador', null));

        expect(viewAsLeadIds())->toContain($this->hubLead->id)->not->toContain($this->alvoradaLead->id);
    });

    it('is ignored once its own operator is placed in a firm', function () {
        $this->operator->update(['firm_id' => $this->franco->id]);
        $this->withSession([ViewAsService::SESSION_KEY => $this->openRow->id])->actingAs($this->operator->fresh());

        expect(viewAsLeadIds())->toContain($this->francoLead->id)->not->toContain($this->alvoradaLead->id);
        $this->getJson('/api/auth/me')->assertJsonPath('data.platform.view_as', null);
    });

    it('is ignored once the row is closed', function () {
        $this->openRow->update(['ended_at' => now(), 'end_reason' => 'sair']);
        $this->withSession([ViewAsService::SESSION_KEY => $this->openRow->id])->actingAs($this->operator);

        expect(viewAsLeadIds())->toContain($this->hubLead->id)->not->toContain($this->alvoradaLead->id);
    });

    it('is ignored for a guest', function () {
        $this->withSession([ViewAsService::SESSION_KEY => $this->openRow->id]);

        $this->getJson('/api/public/firm')->assertOk()->assertJsonPath('data.slug', 'hub');
    });
});

describe('GET platform/view-as-log', function () {
    it('lists every session, latest first, with who, which firm, when, why and from where', function () {
        $this->actingAs($this->operator);
        viewAsStart($this->alvorada)->assertCreated();
        $this->deleteJson('/api/platform/view-as')->assertOk();
        $this->travel(1)->minutes();
        viewAsStart($this->franco)->assertCreated();
        $this->postJson('/api/auth/logout')->assertOk();

        auth()->forgetGuards();
        $log = $this->actingAs($this->operator)->getJson('/api/platform/view-as-log')->assertOk()->json('data.data');

        expect($log)->toHaveCount(2)
            ->and($log[0]['firm']['slug'])->toBe('franco')
            ->and($log[0]['end_reason'])->toBe('logout')
            ->and($log[1]['firm']['slug'])->toBe('alvorada')
            ->and($log[1]['end_reason'])->toBe('sair')
            ->and($log[1]['operator'])->toBe(['id' => $this->operator->id, 'name' => $this->operator->name])
            ->and($log[1])->toHaveKeys(['id', 'started_at', 'ended_at', 'ip_address']);
    });
});
