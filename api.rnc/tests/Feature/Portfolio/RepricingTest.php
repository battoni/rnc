<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;

/*
 * Fee repricing (rnc-1 F8, R26, A14; plan AM8): behind `repricing.manage` (gestao, operador) — the
 * team gets 403 (A2). Another firm's client is absent (404, A1).
 *
 *   GET  /api/repricing?sector=&regime=   the filtered list, its totals and the spread by band
 *   GET  /api/repricing/{id}              one client with the items behind its uplift
 *   GET  /api/repricing/{id}/letter       what the branded letter prints
 *   POST /api/repricing/mark              `{client_ids}` → each stores `{at, by, pct, suggested}`
 */
function portfolioPerson(string $role): User
{
    return User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

function portfolioClient(array $overrides = []): Client
{
    static $sequence = 0;
    $sequence++;

    return Client::withoutGlobalScopes()->create(array_merge([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'name' => "Cliente {$sequence} Ltda",
        'cnpj' => sprintf('%014d', $sequence),
        'sector' => 'servicos',
        'regime' => 'simples',
        'band' => 'b2',
        'b2b_share' => 80,
        'monthly_fee' => 2000,
    ], $overrides));
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->other = Firm::factory()->create();
});

describe('the list (A14)', function () {
    it('totals Σ (suggested − current) × 12 over the clients, each pct inside 4–25 %', function () {
        portfolioClient(['sector' => 'comercio', 'regime' => 'presumido', 'band' => 'b3', 'monthly_fee' => 3850]);
        portfolioClient(['sector' => 'industria', 'regime' => 'real', 'band' => 'b4', 'monthly_fee' => 7200]);
        portfolioClient(['sector' => 'saude', 'regime' => 'simples', 'band' => 'b1', 'b2b_share' => 10, 'monthly_fee' => 900]);

        $data = $this->actingAs(portfolioPerson('gestao'))->getJson('/api/repricing')->assertOk()->json('data');

        $sum = array_sum(array_map(fn (array $row) => ($row['suggested_cents'] - $row['current_cents']) * 12, $data['clients']));

        expect($data['clients'])->toHaveCount(3)
            ->and($data['summary']['clients'])->toBe(3)
            ->and($data['summary']['uplift_total_cents'])->toBe($sum)
            ->and($data['summary']['current_yearly_cents'])->toBe((3850 + 7200 + 900) * 12 * 100)
            ->and(collect($data['clients'])->every(fn (array $row) => $row['pct'] >= 4 && $row['pct'] <= 25))->toBeTrue()
            ->and(collect($data['clients'])->every(fn (array $row) => $row['suggested_cents'] % 5000 === 0))->toBeTrue()
            ->and(collect($data['clients'])->every(fn (array $row) => $row['uplift_cents'] === ($row['suggested_cents'] - $row['current_cents']) * 12))->toBeTrue()
            ->and(array_sum(array_column($data['spread'], 'count')))->toBe(3);
    });

    it('lists the biggest yearly uplift first', function () {
        portfolioClient(['name' => 'Pequena', 'monthly_fee' => 900]);
        portfolioClient(['name' => 'Grande', 'monthly_fee' => 9000]);

        $names = array_column($this->actingAs(portfolioPerson('gestao'))->getJson('/api/repricing')->json('data.clients'), 'name');

        expect($names)->toBe(['Grande', 'Pequena']);
    });

    it('filters by sector and regime, and the total follows the filter', function () {
        portfolioClient(['sector' => 'comercio', 'regime' => 'simples', 'monthly_fee' => 1500]);
        portfolioClient(['sector' => 'comercio', 'regime' => 'real', 'monthly_fee' => 4000]);
        portfolioClient(['sector' => 'agro', 'regime' => 'real', 'monthly_fee' => 6000]);

        $person = portfolioPerson('gestao');
        $all = $this->actingAs($person)->getJson('/api/repricing')->json('data');
        $comercio = $this->actingAs($person)->getJson('/api/repricing?sector=comercio')->json('data');
        $comercioReal = $this->actingAs($person)->getJson('/api/repricing?sector=comercio&regime=real')->json('data');

        expect(array_column($comercio['clients'], 'sector'))->toBe(['comercio', 'comercio'])
            ->and($comercioReal['clients'])->toHaveCount(1)
            ->and($comercio['summary']['uplift_total_cents'])->toBeLessThan($all['summary']['uplift_total_cents'])
            ->and($comercioReal['summary']['uplift_total_cents'])->toBe(($comercioReal['clients'][0]['suggested_cents'] - 400_000) * 12);
    });

    it('refuses an unknown sector or regime', function () {
        $this->actingAs(portfolioPerson('gestao'))->getJson('/api/repricing?sector=mineracao&regime=mei')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sector', 'regime']);
    });

    it('never lists another firm\'s client', function () {
        portfolioClient(['name' => 'Da Franco']);
        portfolioClient(['name' => 'De outra firma', 'firm_id' => $this->other->id]);

        $names = array_column($this->actingAs(portfolioPerson('gestao'))->getJson('/api/repricing')->json('data.clients'), 'name');

        expect($names)->toBe(['Da Franco']);
    });
});

describe('the detail and the letter (A14)', function () {
    it('shows a client with the items behind its uplift', function () {
        $client = portfolioClient(['name' => 'Atlas Soluções em TI Ltda']);

        $data = $this->actingAs(portfolioPerson('gestao'))->getJson("/api/repricing/{$client->id}")->assertOk()->json('data');

        expect($data['name'])->toBe('Atlas Soluções em TI Ltda')
            ->and($data['items'])->not->toBeEmpty()
            ->and($data['items'][0])->toHaveKeys(['id', 'kind', 'title', 'rule', 'detail'])
            ->and($data['opportunities'] + $data['risks'])->toBe(count($data['items']));
    });

    it('prints a letter that names the client, its fees and the firm that signs it', function () {
        $client = portfolioClient(['name' => 'Clínica Bem Viver Ltda', 'sector' => 'saude', 'regime' => 'presumido', 'band' => 'b3', 'monthly_fee' => 3800]);

        $letter = $this->actingAs(portfolioPerson('gestao'))->getJson("/api/repricing/{$client->id}/letter")->assertOk()->json('data');

        expect($letter['client']['name'])->toBe('Clínica Bem Viver Ltda')
            ->and($letter['current_cents'])->toBe(380_000)
            ->and($letter['suggested_cents'])->toBeGreaterThan(380_000)
            ->and($letter['items'])->not->toBeEmpty()
            ->and($letter['firm']['name'])->toBe('Franco Contabilidade')
            ->and($letter['firm'])->toHaveKeys(['address', 'phone', 'mailbox'])
            ->and($letter['effective_from'])->toBe('2027-01-01');
    });

    it('answers 404 for another firm\'s client (A1)', function () {
        $foreign = portfolioClient(['firm_id' => $this->other->id]);
        $person = portfolioPerson('gestao');

        $this->actingAs($person)->getJson("/api/repricing/{$foreign->id}")->assertNotFound();
        $this->actingAs($person)->getJson("/api/repricing/{$foreign->id}/letter")->assertNotFound();
    });
});

describe('marking in batch (R26)', function () {
    beforeEach(fn () => CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo')));

    afterEach(fn () => CarbonImmutable::setTestNow());

    it('stores {at, by, pct, suggested} on each chosen client and answers them', function () {
        $first = portfolioClient(['monthly_fee' => 2000]);
        $second = portfolioClient(['monthly_fee' => 4500, 'regime' => 'real']);
        $untouched = portfolioClient();
        $person = portfolioPerson('gestao');

        $quoted = collect($this->actingAs($person)->getJson('/api/repricing')->json('data.clients'))->keyBy('id');
        $response = $this->actingAs($person)->postJson('/api/repricing/mark', ['client_ids' => [$first->id, $second->id]])->assertOk()->json('data');

        $stored = $first->fresh()->repricing;

        expect($response['marked'])->toBe(2)
            ->and(array_column($response['clients'], 'id'))->toEqualCanonicalizing([$first->id, $second->id])
            ->and($stored)->toMatchArray(['by' => $person->id, 'pct' => $quoted[$first->id]['pct'], 'suggested' => $quoted[$first->id]['suggested_cents'] / 100])
            ->and(CarbonImmutable::parse($stored['at'])->equalTo(CarbonImmutable::now()))->toBeTrue()
            ->and($second->fresh()->repricing['suggested'] * 100)->toEqual($quoted[$second->id]['suggested_cents'])
            ->and($untouched->fresh()->repricing)->toBeNull()
            ->and($this->actingAs($person)->getJson('/api/repricing')->json('data.summary.marked'))->toBe(2);
    });

    it('shows who marked it on the list', function () {
        $client = portfolioClient();
        $person = portfolioPerson('gestao');

        $this->actingAs($person)->postJson('/api/repricing/mark', ['client_ids' => [$client->id]])->assertOk();

        $row = $this->actingAs($person)->getJson('/api/repricing')->json('data.clients.0');

        expect($row['repricing'])->toMatchArray(['by' => $person->id, 'by_name' => $person->name]);
    });

    it('refuses an empty selection and another firm\'s client', function () {
        $foreign = portfolioClient(['firm_id' => $this->other->id]);
        $person = portfolioPerson('gestao');

        $this->actingAs($person)->postJson('/api/repricing/mark', ['client_ids' => []])->assertUnprocessable()->assertJsonValidationErrors(['client_ids']);
        $this->actingAs($person)->postJson('/api/repricing/mark', ['client_ids' => [$foreign->id]])->assertUnprocessable()->assertJsonValidationErrors(['client_ids.0']);

        expect($foreign->fresh()->repricing)->toBeNull();
    });
});

describe('who reaches it (A2, AM8)', function () {
    it('lets the operator in as well', function () {
        portfolioClient();

        $this->actingAs(portfolioPerson('operador'))->getJson('/api/repricing')->assertOk();
    });

    it('refuses the team with a 403 on every repricing endpoint', function () {
        $client = portfolioClient();
        $team = portfolioPerson('equipe');

        $this->actingAs($team)->getJson('/api/repricing')->assertForbidden();
        $this->actingAs($team)->getJson("/api/repricing/{$client->id}")->assertForbidden();
        $this->actingAs($team)->getJson("/api/repricing/{$client->id}/letter")->assertForbidden();
        $this->actingAs($team)->postJson('/api/repricing/mark', ['client_ids' => [$client->id]])->assertForbidden();

        expect($client->fresh()->repricing)->toBeNull();
    });

    it('asks a guest to sign in', function () {
        $this->getJson('/api/repricing')->assertUnauthorized();
    });
});
