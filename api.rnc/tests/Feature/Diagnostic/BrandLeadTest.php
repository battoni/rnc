<?php

use App\Modules\Booking\Mail\BookingConfirmed;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use Carbon\CarbonImmutable;
use Database\Seeders\BrandSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialistSeeder;
use Illuminate\Support\Facades\Mail;

/*
 * A3 (rnc-3 R3, R4; plan F1): a diagnostic started under `X-Brand: prime` creates a lead with
 * Prime's `brand_id`, fixed at creation like the channel — no later PATCH, under another header or
 * none, moves it. The consent the visitor reads and the one stored name Prime; the confirmation
 * e-mail comes from "Franco Prime Contabilidade" at Franco's own mailbox and is signed by it.
 *
 * The clock is Thursday 19/11/2026 10:00 in São Paulo, so Monday 23/11 09:00 is a bookable slot.
 */
const BRAND_LEAD_VISITOR = '0b7d4c9e-2f1a-4e3b-8c5d-6a7b8c9d0e1f';

/** Franco's v1 for the main brand (rnc-4 F2a, C5): its name in the placeholder. */
const FRANCO_MAIN_CONSENT = 'Autorizo a Franco Contabilidade a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';

const PRIME_CONSENT = 'Autorizo a Franco Prime Contabilidade a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';

function startBrandDiagnostic(?string $brand, string $visitor = BRAND_LEAD_VISITOR): string
{
    $request = $brand === null ? test() : test()->withHeader('X-Brand', $brand);

    return $request->postJson('/api/public/diagnostics', ['visitor' => $visitor, 'path' => 'quiz', 'source' => 'direto'])
        ->assertCreated()
        ->json('data.token');
}

function brandLeadOf(string $token): Lead
{
    return Lead::withoutGlobalScopes()->where('token', $token)->sole();
}

function brandContact(): array
{
    return ['contact' => ['company' => 'Clínica Horizonte', 'name' => 'Ana Souza', 'phone' => '(11) 98888-7777', 'email' => 'ana@horizonte.com.br', 'consent' => true], 'step' => 'perfil'];
}

function bookBrandLead(?int $brandId): void
{
    Mail::fake();

    $lead = Lead::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'brand_id' => $brandId,
        'stage' => 'lead',
        'last_step' => 'resultado',
        'company' => 'Clínica Horizonte',
        'contact_name' => 'Ana Souza',
        'email' => 'ana@horizonte.com.br',
        'profile' => ['sector' => 'saude', 'regime' => 'simples', 'band' => 'b2', 'b2b' => 30, 'incentivos' => false, 'exporta' => false],
    ]);
    $carlos = Specialist::withoutGlobalScopes()->whereHas('user', fn ($query) => $query->where('username', 'carlos.prado'))->sole();

    test()->postJson("/api/public/diagnostics/{$lead->token}/booking", [
        'specialist_id' => $carlos->id,
        'starts_at' => CarbonImmutable::parse('2026-11-23 09:00', 'America/Sao_Paulo')->toIso8601String(),
    ])->assertCreated();
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, BrandSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->prime = FirmBrand::withoutGlobalScopes()->where('slug', 'prime')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('a lead started under a brand (A3)', function () {
    it("carries Prime's brand_id", function () {
        expect(brandLeadOf(startBrandDiagnostic('prime'))->brand_id)->toBe($this->prime->id);
    });

    it('is the main brand (null) without the header', function () {
        expect(brandLeadOf(startBrandDiagnostic(null))->brand_id)->toBeNull();
    });

    it('keeps its brand through a PATCH under another header or none', function () {
        $token = startBrandDiagnostic('prime');

        $this->withHeader('X-Brand', 'express')->patchJson("/api/public/diagnostics/{$token}", brandContact())->assertOk();
        $this->patchJson("/api/public/diagnostics/{$token}", ['profile' => ['regime' => 'simples'], 'step' => 'perfil'])->assertOk();

        expect(brandLeadOf($token)->brand_id)->toBe($this->prime->id);
    });

    it('keeps its brand when the same visitor starts again under another one', function () {
        $token = startBrandDiagnostic('prime');

        expect(startBrandDiagnostic('express'))->toBe($token)
            ->and(brandLeadOf($token)->brand_id)->toBe($this->prime->id);
    });

    it('shows and stores a consent that names Prime', function () {
        $token = startBrandDiagnostic('prime');

        $this->patchJson("/api/public/diagnostics/{$token}", brandContact())
            ->assertOk()
            ->assertJsonPath('data.consent.text', PRIME_CONSENT);

        expect(LeadConsent::query()->where('lead_id', brandLeadOf($token)->id)->sole()->text)->toBe(PRIME_CONSENT);
    });

    it("keeps Franco's own consent words for the main brand", function () {
        $token = startBrandDiagnostic(null);

        $this->patchJson("/api/public/diagnostics/{$token}", brandContact())->assertOk()->assertJsonPath('data.consent.text', FRANCO_MAIN_CONSENT);
    });
});

describe("the confirmation e-mail of a brand's lead (A3)", function () {
    beforeEach(fn () => $this->seed([LocalUsersSeeder::class, SpecialistSeeder::class]));

    it("is from Franco Prime Contabilidade, replies to Franco's mailbox, and is signed by it", function () {
        bookBrandLead($this->prime->id);

        Mail::assertQueued(BookingConfirmed::class, function (BookingConfirmed $mail) {
            $from = $mail->envelope()->from;

            $mail->assertSeeInHtml('Franco Prime Contabilidade')->assertSeeInText('Franco Prime Contabilidade');

            // From the platform's verified address under the brand's name; replies go to the firm (rnc-4 D11).
            return $from?->address === config('mail.from.address') && $from->name === 'Franco Prime Contabilidade'
                && $mail->envelope()->replyTo[0]->address === $this->franco->mailbox;
        });
    });

    it("is from Franco Contabilidade for the main brand's lead", function () {
        bookBrandLead(null);

        Mail::assertQueued(BookingConfirmed::class, fn (BookingConfirmed $mail) => $mail->envelope()->from?->name === 'Franco Contabilidade'
            && $mail->envelope()->from->address === config('mail.from.address')
            && $mail->envelope()->replyTo[0]->address === $this->franco->mailbox);
    });
});
