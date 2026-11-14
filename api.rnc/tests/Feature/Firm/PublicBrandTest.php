<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Tracking\Models\TrackingEvent;
use Database\Seeders\BrandSeeder;
use Database\Seeders\FirmSeeder;
use Illuminate\Support\Str;

/*
 * A1 (rnc-3 R1, R2; plan F1, AM21): `GET /api/public/firm` under `X-Brand: <slug>` answers the
 * sub-brand's name, short name, tagline, colours and proof over the firm's own address, phone,
 * mailbox and hours. The brand is looked up inside the request's firm only, so a header can never
 * reach another firm's brand — nor carry a visitor into another firm's contact data.
 */
beforeEach(function () {
    $this->seed([FirmSeeder::class, BrandSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
});

function publicBrandEventPayload(): array
{
    return ['visitor' => (string) Str::uuid(), 'type' => 'visit', 'path' => '/', 'source' => 'direto'];
}

describe('GET /api/public/firm under a brand (A1)', function () {
    it("answers Express over Franco's own phone, mailbox, address and hours", function () {
        $response = $this->withHeader('X-Brand', 'express')->getJson('/api/public/firm')->assertOk();

        expect($response->json('data'))->toMatchArray([
            'name' => 'Franco Express Contabilidade',
            'short_name' => 'Franco Express',
            'brand' => 'express',
            'logo_text' => 'Franco Express Contabilidade',
            'tagline' => 'A contabilidade da Franco no ritmo da pequena empresa',
            'brand_primary' => '#3b2a86',
            'brand_accent' => '#ff8a3d',
            'slug' => 'franco',
            'phone' => $this->franco->phone,
            'phone_href' => 'tel:+551137325500',
            'mailbox' => $this->franco->mailbox,
            'address' => $this->franco->address,
            'business_hours' => $this->franco->business_hours,
        ])->and($response->json('data.proof'))->toHaveCount(4)
            ->and($response->json('data.proof.0'))->toBeIgnoringKeyOrder(['value' => '35 anos', 'label' => 'da Franco em São Paulo']);
    });

    it('answers the main brand without the header', function () {
        $this->getJson('/api/public/firm')
            ->assertOk()
            ->assertJsonPath('data.name', 'Franco Contabilidade')
            ->assertJsonPath('data.brand', null)
            ->assertJsonPath('data.brand_primary', '#00375a')
            ->assertJsonPath('data.short_name', 'Franco')
            ->assertJsonPathIgnoringKeyOrder('data.proof.0', ['value' => '35 anos', 'label' => 'em São Paulo']);
    });

    it('answers 404 brands.notFound for an unknown slug', function () {
        $this->withHeader('X-Brand', 'nada')->getJson('/api/public/firm')
            ->assertNotFound()
            ->assertJsonPath('error', 'brands.notFound');
    });

    it('answers 404 brands.notFound for an inactive brand', function () {
        FirmBrand::withoutGlobalScopes()->where('slug', 'prime')->update(['active' => false]);

        $this->withHeader('X-Brand', 'prime')->getJson('/api/public/firm')
            ->assertNotFound()
            ->assertJsonPath('error', 'brands.notFound');
    });

    it("answers 404 for another firm's brand slug, and never shows that firm's contact", function () {
        $other = Firm::factory()->create(['slug' => 'outra', 'name' => 'Outra Contabilidade', 'phone' => '21 4000-0000']);
        FirmBrand::factory()->create(['firm_id' => $other->id, 'slug' => 'outra-express', 'name' => 'Outra Express']);

        $this->withHeader('X-Brand', 'outra-express')->getJson('/api/public/firm')
            ->assertNotFound()
            ->assertJsonPath('error', 'brands.notFound');

        // The same slug inside its own firm resolves: the 404 above is the firm boundary, not a typo.
        $this->withHeaders(['X-Firm' => 'outra', 'X-Brand' => 'outra-express'])->getJson('/api/public/firm')
            ->assertOk()
            ->assertJsonPath('data.name', 'Outra Express')
            ->assertJsonPath('data.phone', '21 4000-0000');
    });

    it("refuses Franco's brand under another firm's X-Firm", function () {
        Firm::factory()->create(['slug' => 'outra']);

        $this->withHeaders(['X-Firm' => 'outra', 'X-Brand' => 'express'])->getJson('/api/public/firm')
            ->assertNotFound()
            ->assertJsonPath('error', 'brands.notFound');
    });

    it('applies to every public site route, so a stale brand is told apart from a page that is gone', function () {
        $this->withHeader('X-Brand', 'nada')->getJson('/api/public/sectors')
            ->assertNotFound()
            ->assertJsonPath('error', 'brands.notFound');
    });
});

describe('POST /api/public/events under a stale brand (AM21)', function () {
    it('records the event on the main brand for an unknown slug', function () {
        $this->withHeader('X-Brand', 'nada')->postJson('/api/public/events', publicBrandEventPayload())->assertCreated();

        expect(TrackingEvent::withoutGlobalScopes()->where('firm_id', $this->franco->id)->count())->toBe(1);
    });

    it('records the event on the main brand for an inactive slug', function () {
        FirmBrand::withoutGlobalScopes()->where('slug', 'express')->update(['active' => false]);

        $this->withHeader('X-Brand', 'express')->postJson('/api/public/events', publicBrandEventPayload())->assertCreated();

        expect(TrackingEvent::withoutGlobalScopes()->where('firm_id', $this->franco->id)->count())->toBe(1);
    });
});
