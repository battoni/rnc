<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

/*
 * The chat receptionist's channel (rnc-2 F5, R14, A10's API half):
 *
 *   POST  /api/public/diagnostics   takes `channel` ∈ {formulario, chat}, formulario when absent
 *   PATCH /api/public/diagnostics/{token}   the contact's consent `via` is the lead's channel
 *
 * The channel is set once, when the lead is created, and nothing after that changes it: not a later
 * PATCH, not a start from the form on the same visitor (which reopens the same `visitante`).
 * `carteira` is the portfolio's own channel (F10) and never a visitor's.
 */
const CHAT_VISITOR = '0b7c9a3e-4d2f-4e8a-9c1b-2f3e4d5a6b7c';

function chatStart(array $overrides = []): string
{
    return test()->postJson('/api/public/diagnostics', array_merge([
        'visitor' => CHAT_VISITOR,
        'path' => 'quiz',
        'source' => 'organico',
    ], $overrides))->assertCreated()->json('data.token');
}

function chatLeadOf(string $token): Lead
{
    return Lead::withoutGlobalScopes()->where('token', $token)->sole();
}

function chatContact(string $token): void
{
    test()->patchJson("/api/public/diagnostics/{$token}", ['contact' => [
        'company' => 'Clínica Horizonte',
        'name' => 'Ana Souza',
        'phone' => '(11) 98888-7777',
        'email' => null,
        'consent' => true,
    ], 'step' => 'perfil'])->assertOk();
}

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
});

describe('the channel a lead starts with (R14)', function () {
    it('starts a chat lead when the chat starts it, and says so to the visitor', function () {
        $response = $this->postJson('/api/public/diagnostics', ['visitor' => CHAT_VISITOR, 'path' => 'quiz', 'source' => 'organico', 'channel' => 'chat'])->assertCreated();

        expect($response->json('data.channel'))->toBe('chat')
            ->and(chatLeadOf($response->json('data.token'))->channel)->toBe('chat');
    });

    it('starts a form lead when no channel is sent', function () {
        expect(chatLeadOf(chatStart())->channel)->toBe('formulario');
    });

    it('refuses a channel a visitor cannot start from', function (string $channel) {
        $this->postJson('/api/public/diagnostics', ['path' => 'quiz', 'source' => 'organico', 'channel' => $channel])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channel');
    })->with(['carteira', 'whatsapp', '']);
});

describe('the consent given in the chat (R14, A10)', function () {
    it('stores the consent via chat for a chat lead', function () {
        chatContact(chatStart(['channel' => 'chat']));

        expect(LeadConsent::query()->sole()->via)->toBe('chat');
    });

    it('keeps the consent via formulario for a form lead', function () {
        chatContact(chatStart());

        expect(LeadConsent::query()->sole()->via)->toBe('formulario');
    });
});

describe('the channel never changes after the lead exists (R14)', function () {
    it('keeps chat through every later PATCH, a channel key in it included', function () {
        $token = chatStart(['channel' => 'chat']);

        chatContact($token);
        $this->patchJson("/api/public/diagnostics/{$token}", ['profile' => ['sector' => 'servicos', 'regime' => 'simples', 'b2b' => 80], 'step' => 'faturamento', 'channel' => 'formulario'])->assertOk();

        expect(chatLeadOf($token)->channel)->toBe('chat');
    });

    it('keeps chat when the same visitor starts again from the form', function () {
        $first = chatStart(['channel' => 'chat']);
        $again = $this->postJson('/api/public/diagnostics', ['visitor' => CHAT_VISITOR, 'path' => 'cnpj', 'source' => 'organico', 'channel' => 'formulario'])->assertCreated();

        expect(Lead::withoutGlobalScopes()->count())->toBe(1)
            ->and($again->json('data.channel'))->toBe('chat')
            ->and(chatLeadOf($first)->channel)->toBe('chat');
    });

    it('keeps formulario when the same visitor starts again from the chat', function () {
        chatStart();
        $this->postJson('/api/public/diagnostics', ['visitor' => CHAT_VISITOR, 'path' => 'quiz', 'source' => 'organico', 'channel' => 'chat'])->assertCreated();

        expect(Lead::withoutGlobalScopes()->sole()->channel)->toBe('formulario');
    });
});

describe('the team sees the channel (R14, A12)', function () {
    it('shows chat on the board card and on the lead detail', function () {
        $token = chatStart(['channel' => 'chat']);
        chatContact($token);
        $lead = chatLeadOf($token);
        $member = User::factory()->create([
            'firm_id' => Firm::where('slug', 'franco')->value('id'),
            'role_id' => Role::where('slug', 'equipe')->value('id'),
        ]);

        $card = collect($this->actingAs($member)->getJson('/api/leads')->assertOk()->json('data.columns'))->flatMap(fn (array $column) => $column['items'])->firstWhere('id', $lead->id);
        $detail = $this->actingAs($member)->getJson("/api/leads/{$lead->id}")->assertOk()->json('data');

        expect($card['channel'])->toBe('chat')
            ->and($detail['channel'])->toBe('chat')
            ->and($detail['consent']['via'])->toBe('chat');
    });
});
