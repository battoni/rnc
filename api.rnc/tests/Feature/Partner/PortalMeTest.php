<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Partner\Models\Partner;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;

/*
 * The portal's first answer (rnc-2 F1, R3–R4; plan AM1):
 *
 *   GET /api/portal/me   the signed-in partner — name, specialty — and the firm whose portal it is
 *
 * `permission:portal.access` + `portal` (ResolveCurrentPartner): the Partner is loaded from
 * `users.partner_id` through FirmScope, so a user without one — the operator included, who holds
 * every key — or one pointing at another firm's partner gets 403 `portal.noPartner`.
 */
function portalPartner(int $firmId, string $name = 'NexoERP (exemplo)'): Partner
{
    return Partner::withoutGlobalScopes()->create([
        'firm_id' => $firmId,
        'name' => $name,
        'specialty' => 'erp_nfe',
        'email' => 'contato3@parceiro.exemplo',
        'active' => true,
    ]);
}

function portalUser(string $role, ?int $partnerId = null, ?int $firmId = null): User
{
    return User::factory()->create([
        'firm_id' => $firmId ?? Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
        'partner_id' => $partnerId,
    ]);
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->francoId = (int) Firm::where('slug', 'franco')->value('id');
});

it('answers the signed-in partner and its firm', function () {
    $partner = portalPartner($this->francoId);

    $this->actingAs(portalUser('parceiro', $partner->id))
        ->getJson('/api/portal/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'partner' => ['id' => $partner->id, 'name' => 'NexoERP (exemplo)', 'specialty' => 'erp_nfe'],
            'firm' => ['name' => Firm::find($this->francoId)->name, 'slug' => 'franco'],
        ]]);
});

it('refuses the operator, who holds every key but no partner, with portal.noPartner', function () {
    $this->actingAs(portalUser('operador'))
        ->getJson('/api/portal/me')
        ->assertForbidden()
        ->assertJsonPath('error', 'portal.noPartner');
});

it('refuses staff without the key with 403', function () {
    $this->actingAs(portalUser('equipe'))->getJson('/api/portal/me')->assertForbidden();
    $this->actingAs(portalUser('gestao'))->getJson('/api/portal/me')->assertForbidden();
});

it('asks a guest to sign in', function () {
    $this->getJson('/api/portal/me')->assertUnauthorized();
});

it('never finds a partner of another firm, whatever X-Firm says (AM1)', function () {
    $other = Firm::factory()->create();
    $foreign = portalPartner($other->id, 'De outra firma');

    $login = portalUser('parceiro', $foreign->id);

    $this->actingAs($login)
        ->getJson('/api/portal/me')
        ->assertForbidden()
        ->assertJsonPath('error', 'portal.noPartner');

    // rnc-4 D5: naming the partner's firm in the header used to be ignored; it is now refused.
    $this->actingAs($login)
        ->withHeader('X-Firm', $other->slug)
        ->getJson('/api/portal/me')
        ->assertForbidden()
        ->assertJsonPath('error', 'firms.mismatch');
});

it('refuses a partner login whose partner was deactivated', function () {
    $partner = portalPartner($this->francoId);
    $partner->update(['active' => false]);

    $this->actingAs(portalUser('parceiro', $partner->id))
        ->getJson('/api/portal/me')
        ->assertForbidden()
        ->assertJsonPath('error', 'portal.noPartner');
});
