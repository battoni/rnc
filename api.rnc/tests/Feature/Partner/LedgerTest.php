<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\PartnerAccessEvent;
use App\Modules\Partner\Models\Referral;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/*
 * The network ledger (rnc-2 F4, R11, D10; A9; plan AM9):
 *
 *   GET   /api/portal/statement?month=   the signed-in partner's month, under the firm's model only
 *   GET   /api/ledger?month=             every partner's month, the total and the other model's (`ledger.read`)
 *   PATCH /api/ledger/model              {model: comissao|assinatura} — one model for the whole network (`partners.manage`)
 *
 * Comissão: 10 % of each closing's first-year value, half-up to the cent
 * (`intdiv(cents * rate_bp + 5000, 10000)`), in the month of its `closed_at` in São Paulo.
 * Assinatura: R$ 490 for each partner whose portal access existed on any day of the month, read
 * from `partner_access_events` — a revoke and a later regrant bill both periods. Nothing is billed:
 * every figure is "a conferir". Now: Monday 16/11/2026 10:00 in São Paulo.
 */
function ledgerFrancoId(): int
{
    return (int) Firm::where('slug', 'franco')->value('id');
}

function ledgerPartner(int $firmId, string $name, string $specialty = 'erp_nfe'): Partner
{
    return Partner::withoutGlobalScopes()->create([
        'firm_id' => $firmId,
        'name' => $name,
        'specialty' => $specialty,
        'email' => str($name)->slug().'@parceiro.exemplo',
        'active' => true,
    ]);
}

function ledgerLogin(Partner $partner): User
{
    return User::factory()->create([
        'firm_id' => $partner->firm_id,
        'role_id' => Role::where('slug', 'parceiro')->value('id'),
        'partner_id' => $partner->id,
        'name' => $partner->name,
        'last_name' => '',
    ]);
}

function ledgerStaff(string $role, ?int $firmId = null): User
{
    return User::factory()->create(['firm_id' => $firmId ?? ledgerFrancoId(), 'role_id' => Role::where('slug', $role)->value('id')]);
}

/** A referral its partner closed at `$closedAt` (São Paulo) for `$cents` in the first year. */
function ledgerClosing(Partner $partner, int $cents, string $closedAt, string $company = 'Consultoria Prisma Ltda'): Referral
{
    $lead = Lead::factory()->create(['firm_id' => $partner->firm_id, 'stage' => 'lead', 'last_step' => 'resultado', 'company' => $company]);
    $referrer = User::query()->where('firm_id', $partner->firm_id)->value('id') ?? User::factory()->create(['firm_id' => $partner->firm_id])->id;
    $closed = CarbonImmutable::parse($closedAt, 'America/Sao_Paulo');

    return Referral::withoutGlobalScopes()->forceCreate([
        'firm_id' => $partner->firm_id,
        'lead_id' => $lead->id,
        'partner_id' => $partner->id,
        'referred_by' => $referrer,
        'message' => 'A Franco indica a empresa.',
        'score' => 81,
        'covers' => [['id' => 'nfe_layout', 'title' => 'Novo layout da NF-e']],
        'consent_text' => 'Autorizo a Franco a me apresentar a parceiros.',
        'consent_version' => 1,
        'consent_given_at' => $closed->subMonth(),
        'status' => 'fechado',
        'status_at' => $closed,
        'contacted_at' => $closed->subWeeks(2),
        'deal_value_cents' => $cents,
        'closed_at' => $closed,
        'created_at' => $closed->subWeeks(3),
        'updated_at' => $closed,
    ]);
}

/** One grant or revoke of the partner's portal access, at `$at` in São Paulo. */
function ledgerAccess(Partner $partner, string $type, string $at): void
{
    PartnerAccessEvent::withoutGlobalScopes()->create([
        'firm_id' => $partner->firm_id,
        'partner_id' => $partner->id,
        'user_id' => 1,
        'type' => $type,
        'at' => CarbonImmutable::parse($at, 'America/Sao_Paulo'),
    ]);
}

/** @return array<string, mixed> */
function ledgerStatement(User $login, ?string $month = null): array
{
    $query = $month === null ? '' : "?month={$month}";

    return test()->actingAs($login)->getJson("/api/portal/statement{$query}")->assertOk()->json('data');
}

/** @return array<string, mixed> */
function ledgerNetwork(User $ceo, ?string $month = null): array
{
    $query = $month === null ? '' : "?month={$month}";

    return test()->actingAs($ceo)->getJson("/api/ledger{$query}")->assertOk()->json('data');
}

/** @return array<string, mixed> the network row of one partner */
function ledgerRowOf(array $network, Partner $partner): array
{
    return collect($network['partners'])->firstWhere('partner.id', $partner->id);
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->other = Firm::factory()->create();
    $this->nexo = ledgerPartner(ledgerFrancoId(), 'NexoERP (exemplo)');
    $this->giro = ledgerPartner(ledgerFrancoId(), 'Giro Capital (exemplo)', 'credito_capital_giro');
    $this->foreign = ledgerPartner($this->other->id, 'Parceiro de outra firma');
    $this->nexoLogin = ledgerLogin($this->nexo);
    $this->giroLogin = ledgerLogin($this->giro);
    $this->foreignLogin = ledgerLogin($this->foreign);
    $this->ceo = ledgerStaff('ceo');
    ledgerAccess($this->nexo, 'granted', '2026-10-05 09:00');
    ledgerAccess($this->giro, 'granted', '2026-10-20 09:00');
    ledgerAccess($this->foreign, 'granted', '2026-10-01 09:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('comissão (A9)', function () {
    it('owes 10 % of each closing of the month: R$ 1.200 + R$ 2.400 = R$ 3.600', function () {
        $first = ledgerClosing($this->nexo, 1_200_000, '2026-11-03 15:00', 'Padaria Trigo Dourado');
        $second = ledgerClosing($this->nexo, 2_400_000, '2026-11-12 11:00', 'Consultoria Prisma Ltda');

        expect(ledgerStatement($this->nexoLogin))->toBe([
            'month' => '2026-11',
            'model' => 'comissao',
            'firm' => ['name' => 'Franco Contabilidade'],
            'commission_rate_bp' => 1000,
            'lines' => [
                ['kind' => 'closing', 'referral_id' => $second->id, 'company' => 'Consultoria Prisma Ltda', 'closed_at' => '2026-11-12T14:00:00+00:00', 'deal_value_cents' => 2_400_000, 'amount_cents' => 240_000],
                ['kind' => 'closing', 'referral_id' => $first->id, 'company' => 'Padaria Trigo Dourado', 'closed_at' => '2026-11-03T18:00:00+00:00', 'deal_value_cents' => 1_200_000, 'amount_cents' => 120_000],
            ],
            'deals' => 2,
            'deal_total_cents' => 3_600_000,
            'total_cents' => 360_000,
        ]);
    });

    it('rounds each closing half-up to the cent: R$ 12.345,67 at 10 % is R$ 1.234,57 (AM9)', function () {
        ledgerClosing($this->nexo, 1_234_567, '2026-11-03 15:00');
        ledgerClosing($this->nexo, 1_234_564, '2026-11-04 15:00');

        $statement = ledgerStatement($this->nexoLogin);

        expect(array_column($statement['lines'], 'amount_cents'))->toBe([123_456, 123_457])
            ->and($statement['total_cents'])->toBe(246_913);
    });

    it('puts a closing in the month of its closed_at in São Paulo, not in UTC', function () {
        ledgerClosing($this->nexo, 1_000_000, '2026-10-31 23:30'); // 01/11 02:30 UTC — still October
        ledgerClosing($this->nexo, 2_000_000, '2026-11-01 00:10');
        ledgerClosing($this->nexo, 3_000_000, '2026-11-30 23:59'); // 01/12 02:59 UTC — still November
        ledgerClosing($this->nexo, 4_000_000, '2026-12-01 00:00');

        expect(ledgerStatement($this->nexoLogin, '2026-10')['deal_total_cents'])->toBe(1_000_000)
            ->and(ledgerStatement($this->nexoLogin, '2026-11')['deal_total_cents'])->toBe(5_000_000)
            ->and(ledgerStatement($this->nexoLogin, '2026-12')['deal_total_cents'])->toBe(4_000_000);
    });

    it('reads only closed referrals', function () {
        $open = ledgerClosing($this->nexo, 1_200_000, '2026-11-03 15:00');
        Referral::withoutGlobalScopes()->whereKey($open->id)->update(['status' => 'proposta']);

        expect(ledgerStatement($this->nexoLogin)['lines'])->toBe([]);
    });

    it('owes nothing in a month without closings', function () {
        expect(ledgerStatement($this->nexoLogin, '2026-09'))->toMatchArray(['month' => '2026-09', 'lines' => [], 'deals' => 0, 'deal_total_cents' => 0, 'total_cents' => 0]);
    });
});

describe('assinatura (A9)', function () {
    beforeEach(function () {
        $this->actingAs($this->ceo)->patchJson('/api/ledger/model', ['model' => 'assinatura'])->assertOk();
    });

    it('owes R$ 490 in the month, and the closings add nothing', function () {
        $closing = ledgerClosing($this->nexo, 1_200_000, '2026-11-03 15:00');

        expect(ledgerStatement($this->nexoLogin))->toBe([
            'month' => '2026-11',
            'model' => 'assinatura',
            'firm' => ['name' => 'Franco Contabilidade'],
            'monthly_fee_cents' => 49_000,
            'lines' => [
                ['kind' => 'subscription', 'referral_id' => null, 'company' => null, 'closed_at' => null, 'deal_value_cents' => null, 'amount_cents' => 49_000],
                ['kind' => 'closing', 'referral_id' => $closing->id, 'company' => 'Consultoria Prisma Ltda', 'closed_at' => '2026-11-03T18:00:00+00:00', 'deal_value_cents' => 1_200_000, 'amount_cents' => 0],
            ],
            'deals' => 1,
            'deal_total_cents' => 1_200_000,
            'total_cents' => 49_000,
        ]);
    });

    it('bills a month the access existed on any day of it, and none before the grant', function () {
        ledgerAccess($this->nexo, 'revoked', '2026-10-05 17:00'); // granted and revoked the same day

        expect(ledgerStatement($this->nexoLogin, '2026-10')['total_cents'])->toBe(49_000)
            ->and(ledgerStatement($this->nexoLogin, '2026-09')['total_cents'])->toBe(0)
            ->and(ledgerStatement($this->giroLogin, '2026-09')['total_cents'])->toBe(0)
            ->and(ledgerStatement($this->giroLogin, '2026-10')['total_cents'])->toBe(49_000);
    });

    it('still bills the first period after a revoke and a regrant in a later month (AM9)', function () {
        ledgerAccess($this->nexo, 'revoked', '2026-10-20 12:00');
        ledgerAccess($this->nexo, 'granted', '2026-12-02 09:00');

        $months = collect(['2026-09', '2026-10', '2026-11', '2026-12'])
            ->mapWithKeys(fn (string $month) => [$month => ledgerRowOf(ledgerNetwork($this->ceo, $month), $this->nexo)['subscribed']])
            ->all();

        expect($months)->toBe(['2026-09' => false, '2026-10' => true, '2026-11' => false, '2026-12' => true]);
    });

    it('bills nothing to a partner revoked before the month', function () {
        ledgerAccess($this->giro, 'revoked', '2026-10-31 23:00');

        expect(ledgerRowOf(ledgerNetwork($this->ceo), $this->giro))->toMatchArray(['subscribed' => false, 'subscription_cents' => 0, 'due_cents' => 0]);
    });
});

describe('the network ledger (R11)', function () {
    it('shows every partner\'s month, the total and what the other model would give', function () {
        ledgerClosing($this->nexo, 1_200_000, '2026-11-03 15:00');
        ledgerClosing($this->nexo, 2_400_000, '2026-11-12 11:00');
        ledgerClosing($this->foreign, 9_900_000, '2026-11-05 11:00');

        expect(ledgerNetwork($this->ceo))->toBe([
            'month' => '2026-11',
            'model' => 'comissao',
            'commission_rate_bp' => 1000,
            'monthly_fee_cents' => 49_000,
            'partners' => [
                ['partner' => ['id' => $this->giro->id, 'name' => 'Giro Capital (exemplo)', 'specialty' => 'credito_capital_giro', 'active' => true], 'deals' => 0, 'deal_total_cents' => 0, 'commission_cents' => 0, 'subscribed' => true, 'subscription_cents' => 49_000, 'due_cents' => 0],
                ['partner' => ['id' => $this->nexo->id, 'name' => 'NexoERP (exemplo)', 'specialty' => 'erp_nfe', 'active' => true], 'deals' => 2, 'deal_total_cents' => 3_600_000, 'commission_cents' => 360_000, 'subscribed' => true, 'subscription_cents' => 49_000, 'due_cents' => 360_000],
            ],
            'deals' => 2,
            'deal_total_cents' => 3_600_000,
            'total_cents' => 360_000,
            'other' => ['model' => 'assinatura', 'total_cents' => 98_000],
        ]);
    });

    it('swaps the total and the other model\'s when the model switches — on both screens (A9)', function () {
        ledgerClosing($this->nexo, 1_200_000, '2026-11-03 15:00');
        ledgerClosing($this->nexo, 2_400_000, '2026-11-12 11:00');

        $answer = $this->actingAs($this->ceo)->patchJson('/api/ledger/model', ['model' => 'assinatura'])->assertOk()->json('data');
        $network = ledgerNetwork($this->ceo);

        expect($answer)->toBe(['model' => 'assinatura', 'commission_rate_bp' => 1000, 'monthly_fee_cents' => 49_000])
            ->and($network['model'])->toBe('assinatura')
            ->and($network['total_cents'])->toBe(98_000)
            ->and($network['other'])->toBe(['model' => 'comissao', 'total_cents' => 360_000])
            ->and(ledgerRowOf($network, $this->nexo)['due_cents'])->toBe(49_000)
            ->and(ledgerStatement($this->nexoLogin)['total_cents'])->toBe(49_000)
            ->and(Firm::find(ledgerFrancoId())->partner_money_model)->toBe('assinatura')
            ->and($this->other->fresh()->partner_money_model)->toBe('comissao');
    });

    it('lists an inactive partner too, whose history stays', function () {
        $this->giro->update(['active' => false]);

        expect(ledgerRowOf(ledgerNetwork($this->ceo), $this->giro)['partner']['active'])->toBeFalse();
    });

    it('asks the database the same few queries for two partners or ten (no N+1)', function () {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            ledgerNetwork($this->ceo);
            ledgerStatement($this->nexoLogin);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };
        ledgerClosing($this->nexo, 1_200_000, '2026-11-03 15:00');
        $few = $count();

        foreach (range(1, 8) as $index) {
            $partner = ledgerPartner(ledgerFrancoId(), "Parceiro {$index} (exemplo)");
            ledgerAccess($partner, 'granted', '2026-10-01 09:00');
            ledgerClosing($partner, 1_000_000 + $index, '2026-11-04 10:00');
            ledgerClosing($this->nexo, 1_000_000 + $index, '2026-11-05 10:00');
        }

        expect($count())->toBe($few);
    });
});

describe('who sees what (A9, D10, D14)', function () {
    it('never shows a partner another partner\'s line, nor another firm\'s', function () {
        ledgerClosing($this->nexo, 1_200_000, '2026-11-03 15:00', 'Empresa da Nexo');
        ledgerClosing($this->giro, 2_400_000, '2026-11-04 15:00', 'Empresa da Giro');
        ledgerClosing($this->foreign, 9_900_000, '2026-11-05 15:00', 'Empresa de fora');

        $nexo = $this->actingAs($this->nexoLogin)->getJson("/api/portal/statement?partner_id={$this->giro->id}")->assertOk()->json('data');
        $foreign = $this->actingAs($this->foreignLogin)->getJson('/api/portal/statement')->assertOk()->json('data');
        // rnc-4 D5: Franco's header used to be ignored for the other firm's partner; it is now refused.
        $this->actingAs($this->foreignLogin)->withHeader('X-Firm', 'franco')->getJson('/api/portal/statement')->assertForbidden();

        expect(array_column($nexo['lines'], 'company'))->toBe(['Empresa da Nexo'])
            ->and(array_column($foreign['lines'], 'company'))->toBe(['Empresa de fora'])
            ->and($foreign['firm']['name'])->toBe($this->other->name);
    });

    it('never shows the partner the other model, nor the other partners\' total (D10)', function () {
        ledgerClosing($this->nexo, 1_200_000, '2026-11-03 15:00');

        $statement = ledgerStatement($this->nexoLogin);

        expect($statement)->not->toHaveKey('other')
            ->and($statement)->not->toHaveKey('partners')
            ->and($statement)->not->toHaveKey('monthly_fee_cents');
    });

    it('refuses the network ledger and the model switch to the team (403)', function () {
        $team = ledgerStaff('equipe');

        $this->actingAs($team)->getJson('/api/ledger')->assertForbidden();
        $this->actingAs($team)->patchJson('/api/ledger/model', ['model' => 'assinatura'])->assertForbidden();

        expect(Firm::find(ledgerFrancoId())->partner_money_model)->toBe('comissao');
    });

    it('refuses the network ledger and the model switch to a partner (403)', function () {
        $this->actingAs($this->nexoLogin)->getJson('/api/ledger')->assertForbidden();
        $this->actingAs($this->nexoLogin)->patchJson('/api/ledger/model', ['model' => 'assinatura'])->assertForbidden();
    });

    it('switches only the signed-in person\'s firm, whatever X-Firm says', function () {
        $otherCeo = ledgerStaff('ceo', $this->other->id);

        // rnc-4 D5: Franco's header is refused outright, and nothing moves.
        $this->actingAs($otherCeo)->withHeader('X-Firm', 'franco')->patchJson('/api/ledger/model', ['model' => 'assinatura'])->assertForbidden();
        expect(Firm::find(ledgerFrancoId())->partner_money_model)->toBe('comissao');

        $this->flushHeaders()->actingAs($otherCeo)->patchJson('/api/ledger/model', ['model' => 'assinatura'])->assertOk();

        expect($this->other->fresh()->partner_money_model)->toBe('assinatura')
            ->and(Firm::find(ledgerFrancoId())->partner_money_model)->toBe('comissao');
    });
});

describe('the input', function () {
    it('refuses a month that is not YYYY-MM (422)', function (string $month) {
        $this->actingAs($this->ceo)->getJson("/api/ledger?month={$month}")->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->actingAs($this->nexoLogin)->getJson("/api/portal/statement?month={$month}")->assertUnprocessable()->assertJsonValidationErrors('month');
    })->with(['2026-13', '2026-1', 'novembro', '2026-11-01', '1999-12']);

    it('refuses a model that is not comissao or assinatura (422)', function (mixed $model) {
        $this->actingAs($this->ceo)->patchJson('/api/ledger/model', ['model' => $model])->assertUnprocessable()->assertJsonValidationErrors('model');
    })->with(['gratis', '', null]);
});
