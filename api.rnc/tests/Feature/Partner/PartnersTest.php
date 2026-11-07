<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Partner\Models\Partner;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;

/*
 * The partner registry (rnc-1 F6, R21; plan AM8): `partners.manage` (ceo, operador) lists, creates,
 * edits and deactivates; `referrals.read` reads the options the trail filters by. The team holds
 * neither. Another firm's partner is a 404 (A1).
 *
 *   GET   /api/partners           every partner of the firm, active first
 *   POST  /api/partners           a new one (201)
 *   PATCH /api/partners/{id}      edit, or `active: false` to deactivate
 *   GET   /api/partners/options   [{value, label, active}] for the trail's filter
 */
function partnerPerson(string $role): User
{
    return User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

function francoPartner(array $overrides = []): Partner
{
    return Partner::withoutGlobalScopes()->create(array_merge([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'name' => 'NexoERP (exemplo)',
        'specialty' => 'erp_nfe',
        'email' => 'contato3@parceiro.exemplo',
        'phone' => '551190009003',
        'city' => 'Barueri-SP',
        'active' => true,
    ], $overrides));
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->other = Firm::factory()->create();
});

describe('the registry (R21)', function () {
    it('lists the firm\'s partners, active first, never another firm\'s', function () {
        francoPartner(['name' => 'Zeta Seguros (exemplo)', 'specialty' => 'seguros_beneficios']);
        francoPartner(['name' => 'Alfa Inativo (exemplo)', 'active' => false]);
        francoPartner(['name' => 'Beta Tributário (exemplo)', 'specialty' => 'advocacia_tributaria']);
        Partner::withoutGlobalScopes()->create(['firm_id' => $this->other->id, 'name' => 'De outra firma', 'specialty' => 'erp_nfe', 'email' => 'x@outra.exemplo', 'active' => true]);

        $partners = $this->actingAs(partnerPerson('ceo'))->getJson('/api/partners')->assertOk()->json('data');

        expect(array_column($partners, 'name'))->toBe(['Beta Tributário (exemplo)', 'Zeta Seguros (exemplo)', 'Alfa Inativo (exemplo)'])
            ->and($partners[0])->toMatchArray(['specialty' => 'advocacia_tributaria', 'active' => true, 'email' => 'contato3@parceiro.exemplo']);
    });

    it('creates a partner for the firm', function () {
        $created = $this->actingAs(partnerPerson('operador'))->postJson('/api/partners', [
            'name' => '  Margem Certa (exemplo) ',
            'specialty' => 'precificacao_controladoria',
            'email' => 'CONTATO7@Parceiro.Exemplo',
            'phone' => '551190009007',
            'city' => 'Santo André-SP',
        ])->assertCreated()->json('data');

        expect($created)->toMatchArray(['name' => 'Margem Certa (exemplo)', 'email' => 'contato7@parceiro.exemplo', 'active' => true])
            ->and(Partner::withoutGlobalScopes()->find($created['id'])->firm_id)->toBe(Firm::where('slug', 'franco')->value('id'));
    });

    it('refuses an unknown specialty and a missing e-mail', function () {
        $this->actingAs(partnerPerson('ceo'))->postJson('/api/partners', ['name' => 'Sem e-mail', 'specialty' => 'esg'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['specialty', 'email']);
    });

    it('edits and deactivates a partner, and answers it as it is now', function () {
        $partner = francoPartner();

        $this->actingAs(partnerPerson('ceo'))->patchJson("/api/partners/{$partner->id}", ['city' => 'Osasco-SP'])
            ->assertOk()
            ->assertJsonPath('data.city', 'Osasco-SP');

        $this->patchJson("/api/partners/{$partner->id}", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);

        expect($partner->fresh()->active)->toBeFalse();
    });

    it('answers 404 for another firm\'s partner (A1)', function () {
        $foreign = Partner::withoutGlobalScopes()->create(['firm_id' => $this->other->id, 'name' => 'De outra firma', 'specialty' => 'erp_nfe', 'email' => 'x@outra.exemplo', 'active' => true]);

        $this->actingAs(partnerPerson('ceo'))->patchJson("/api/partners/{$foreign->id}", ['active' => false])->assertNotFound();

        expect($foreign->fresh()->active)->toBeTrue();
    });

    it('gives the trail its filter options, inactive partners included', function () {
        francoPartner(['name' => 'Beta (exemplo)', 'active' => false]);
        francoPartner(['name' => 'Alfa (exemplo)']);

        $this->actingAs(partnerPerson('ceo'))->getJson('/api/partners/options')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Alfa (exemplo)')
            ->assertJsonPath('data.1.active', false);
    });
});

describe('who may manage it (AM8)', function () {
    it('refuses the team every registry route', function () {
        $partner = francoPartner();
        $member = partnerPerson('equipe');

        $this->actingAs($member)->getJson('/api/partners')->assertForbidden();
        $this->actingAs($member)->postJson('/api/partners', ['name' => 'X', 'specialty' => 'erp_nfe', 'email' => 'x@x.exemplo'])->assertForbidden();
        $this->actingAs($member)->patchJson("/api/partners/{$partner->id}", ['active' => false])->assertForbidden();
        $this->actingAs($member)->getJson('/api/partners/options')->assertForbidden();
    });

    it('asks a signed-in person', function () {
        $this->getJson('/api/partners')->assertUnauthorized();
    });
});
