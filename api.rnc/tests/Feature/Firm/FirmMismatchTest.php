<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;

/*
 * rnc-4 F1a — A4 (R5, D5; plan AM1). The header never moves a signed-in person: a request whose
 * `X-Firm` names another firm is refused with 403 `firms.mismatch` (the SPA then signs out
 * locally), and a person with no firm never falls through to the header or the default firm.
 */
function mismatchLeadCount(User $user, ?string $firmHeader = null): int
{
    $request = test()->actingAs($user);

    if ($firmHeader !== null) {
        $request = $request->withHeader('X-Firm', $firmHeader);
    }

    $columns = $request->getJson('/api/leads')->assertOk()->json('data.columns');

    return (int) array_sum(array_column($columns, 'count'));
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->alvorada = Firm::factory()->create(['slug' => 'alvorada']);
    $this->patricia = User::where('username', 'patricia')->firstOrFail();

    Lead::factory()->create(['firm_id' => $this->franco->id, 'stage' => 'lead']);
    Lead::factory()->create(['firm_id' => $this->alvorada->id, 'stage' => 'lead']);
});

describe('a signed-in member of a firm', function () {
    it('is refused with 403 firms.mismatch when the header names another firm', function () {
        $this->actingAs($this->patricia)->withHeader('X-Firm', 'alvorada')->getJson('/api/leads')
            ->assertForbidden()
            ->assertJsonPath('error', 'firms.mismatch');
    });

    it('is refused even on a public route and for a firm that does not exist', function () {
        $this->actingAs($this->patricia)->withHeader('X-Firm', 'alvorada')->getJson('/api/public/firm')
            ->assertForbidden()
            ->assertJsonPath('error', 'firms.mismatch');

        $this->actingAs($this->patricia)->withHeader('X-Firm', 'nenhuma')->getJson('/api/auth/me')
            ->assertForbidden()
            ->assertJsonPath('error', 'firms.mismatch');
    });

    it('reads its own firm with its own header or with none (D5)', function () {
        expect(mismatchLeadCount($this->patricia, 'franco'))->toBe(1)
            ->and(mismatchLeadCount($this->patricia))->toBe(1);
    });
});

describe('a signed-in person with no firm', function () {
    beforeEach(function () {
        Firm::factory()->create(['slug' => 'hub', 'is_platform' => true]);
        $this->operator = User::factory()->create([
            'firm_id' => null,
            'role_id' => Role::where('slug', 'operador')->value('id'),
        ]);
    });

    it("reads no firm's rows without a header: the platform firm has none", function () {
        expect(mismatchLeadCount($this->operator))->toBe(0);
    });

    it('is refused when a header names a firm, instead of falling through to it', function (string $slug) {
        $this->actingAs($this->operator)->withHeader('X-Firm', $slug)->getJson('/api/leads')
            ->assertForbidden()
            ->assertJsonPath('error', 'firms.mismatch');
    })->with(['franco', 'alvorada', 'hub']);
});
