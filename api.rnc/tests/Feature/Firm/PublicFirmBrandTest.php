<?php

use App\Models\User;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

/*
 * A8 (rnc-4 R8, R9; plan F2a): a firm carries its own short name, tagline, proof, logo and modules,
 * and `GET public/firm` answers them for the main brand — with `is_platform`, the logo's public URL
 * (null without a file) and the product's name from `config('platform.name')`, the one place it is
 * written. `GET auth/me` carries the same product name. Under a sub-brand the look stays the
 * brand's (rnc-3 R2) and the firm's logo is not shown: the brand has none.
 */
const ALVORADA_PROOF = [
    ['value' => '18 anos', 'label' => 'em Campinas'],
    ['value' => '300+', 'label' => 'empresas atendidas'],
    ['value' => 'Simples', 'label' => 'e Lucro Presumido'],
    ['value' => 'Online', 'label' => 'rotina sem papel'],
];

function brandAlvorada(array $overrides = []): Firm
{
    return Firm::factory()->create(array_merge([
        'slug' => 'alvorada',
        'name' => 'Alvorada Contabilidade',
        'short_name' => 'Alvorada',
        'tagline' => 'Contabilidade para quem está começando',
        'proof' => ALVORADA_PROOF,
        'brand_primary' => '#0f5c4d',
        'brand_accent' => '#f4b63f',
    ], $overrides));
}

describe('GET /api/public/firm — the firm brand (A8)', function () {
    beforeEach(fn () => Firm::factory()->create(['slug' => 'franco', 'name' => 'Franco Contabilidade']));

    it("answers alvorada's short name, tagline and proof, no logo, not the platform, and the product name", function () {
        brandAlvorada();

        $this->withHeader('X-Firm', 'alvorada')->getJson('/api/public/firm')
            ->assertOk()
            ->assertJsonPath('data.name', 'Alvorada Contabilidade')
            ->assertJsonPath('data.short_name', 'Alvorada')
            ->assertJsonPath('data.tagline', 'Contabilidade para quem está começando')
            ->assertJsonPath('data.proof', ALVORADA_PROOF)
            ->assertJsonPath('data.is_platform', false)
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonPath('data.platform', ['name' => 'Hub da Reforma']);
    });

    it('names the product from PLATFORM_NAME, nowhere else', function () {
        brandAlvorada();
        config(['platform.name' => 'Outro']);

        $this->withHeader('X-Firm', 'alvorada')->getJson('/api/public/firm')->assertOk()->assertJsonPath('data.platform.name', 'Outro');
    });

    it("serves a stored logo's public URL", function () {
        Storage::fake('public');
        brandAlvorada(['logo_path' => 'firms/9/logo-abc.png']);

        $this->withHeader('X-Firm', 'alvorada')->getJson('/api/public/firm')
            ->assertOk()
            ->assertJsonPath('data.logo_url', Storage::disk('public')->url('firms/9/logo-abc.png'));
    });

    it('says is_platform for the platform firm at the root', function () {
        config(['firms.default_slug' => 'hub']);
        Firm::factory()->create(['slug' => 'hub', 'name' => 'Hub da Reforma', 'short_name' => 'Hub da Reforma', 'is_platform' => true]);

        $this->getJson('/api/public/firm')->assertOk()->assertJsonPath('data.is_platform', true)->assertJsonPath('data.slug', 'hub');
    });

    it("keeps a sub-brand's own look and hides the firm's logo under it", function () {
        $alvorada = brandAlvorada(['logo_path' => 'firms/9/logo-abc.png']);
        FirmBrand::withoutGlobalScopes()->forceCreate([
            'firm_id' => $alvorada->id,
            'slug' => 'nova',
            'name' => 'Alvorada Nova Contabilidade',
            'short_name' => 'Alvorada Nova',
            'tagline' => null,
            'brand_primary' => '#3b2a86',
            'brand_accent' => '#ff8a3d',
            'proof' => ALVORADA_PROOF,
            'active' => true,
        ]);

        $this->withHeaders(['X-Firm' => 'alvorada', 'X-Brand' => 'nova'])->getJson('/api/public/firm')
            ->assertOk()
            ->assertJsonPath('data.short_name', 'Alvorada Nova')
            ->assertJsonPath('data.tagline', null)
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonPath('data.platform.name', 'Hub da Reforma');
    });
});

describe('GET /api/auth/me — the product name (R9)', function () {
    it('carries platform.name beside the signed-in person', function () {
        $this->seed(RoleSeeder::class);
        config(['platform.name' => 'Outro']);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.platform.name', 'Outro');
    });
});
