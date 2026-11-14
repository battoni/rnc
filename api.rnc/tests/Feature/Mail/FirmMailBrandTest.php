<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Booking\Mail\BookingConfirmed;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Briefing\Mail\DailyBriefingMail;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Helpers\ConsentTerms;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Partner\Mail\ReferralSent;
use App\Modules\Partner\Models\Partner;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialistSeeder;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/*
 * A9 (rnc-4 R12, D11; plan F2a): every e-mail takes the brand of the firm it concerns. From the
 * platform's verified address under the firm's name, replies to the firm's mailbox, the band in the
 * firm's primary, and not one "Franco" in the HTML or the text — for the meeting confirmation, the
 * partner's referral and the manager's 07:00 briefing. Links go to the firm's own host.
 *
 * The clock is Thursday 19/11/2026 10:00 in São Paulo: Monday 23/11 09:00 is a bookable slot, and
 * Monday 23/11 07:00 a business day for the briefing.
 */
const MAIL_ZONE = 'America/Sao_Paulo';

const MAIL_PROFILE = ['sector' => 'servicos', 'regime' => 'simples', 'band' => 'b2', 'b2b' => 80, 'incentivos' => false, 'exporta' => false];

function mailAlvoradaPerson(Firm $firm, string $username, string $role, bool $specialist = false): User
{
    return User::factory()->create([
        'firm_id' => $firm->id,
        'role_id' => Role::where('slug', $role)->value('id'),
        'username' => $username,
        'name' => ucfirst($username),
        'last_name' => 'Martins',
        'email' => "{$username}@alvorada.exemplo.com.br",
        'is_specialist' => $specialist,
    ]);
}

function mailAlvoradaLead(Firm $firm): Lead
{
    return Lead::factory()->create([
        'firm_id' => $firm->id,
        'stage' => 'lead',
        'last_step' => 'resultado',
        'company' => 'Clínica Horizonte',
        'contact_name' => 'Ana Souza',
        'email' => 'ana@horizonte.com.br',
        'profile' => MAIL_PROFILE,
    ]);
}

/** The brand checks every message of alvorada's must pass (A9). */
function assertAlvoradaBrand(Mailable $mail, Firm $firm): bool
{
    $envelope = $mail->envelope();

    expect($envelope->from?->address)->toBe(config('mail.from.address'))
        ->and($envelope->from?->name)->toBe('Alvorada Contabilidade')
        ->and(array_map(fn ($address) => $address->address, $envelope->replyTo))->toBe([$firm->mailbox]);

    $mail->assertSeeInHtml('background-color:#0f5c4d', false)
        ->assertSeeInHtml('Alvorada Contabilidade')
        ->assertDontSeeInHtml('Franco')
        ->assertDontSeeInText('Franco');

    return true;
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', MAIL_ZONE));
    Mail::fake();
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->alvorada = Firm::factory()->create([
        'slug' => 'alvorada',
        'name' => 'Alvorada Contabilidade',
        'short_name' => 'Alvorada',
        'brand_primary' => '#0f5c4d',
        'brand_accent' => '#f4b63f',
        'mailbox' => 'contato@alvorada.exemplo.com.br',
        'address' => 'Rua das Acácias, 120, Centro, Cidade Exemplo-SP',
    ]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe("alvorada's e-mails (A9)", function () {
    it('brands the meeting confirmation with alvorada', function () {
        mailAlvoradaPerson($this->alvorada, 'bruno.alvorada', 'equipe', specialist: true);
        $this->seed(SpecialistSeeder::class);
        $lead = mailAlvoradaLead($this->alvorada);
        $bruno = Specialist::withoutGlobalScopes()->where('firm_id', $this->alvorada->id)->sole();

        $this->withHeader('X-Firm', 'alvorada')->postJson("/api/public/diagnostics/{$lead->token}/booking", [
            'specialist_id' => $bruno->id,
            'starts_at' => CarbonImmutable::parse('2026-11-23 09:00', MAIL_ZONE)->toIso8601String(),
        ])->assertCreated();

        Mail::assertQueued(BookingConfirmed::class, function (BookingConfirmed $mail) {
            $mail->assertSeeInHtml('http://alvorada.localhost:5173/relatorio/', false);

            return assertAlvoradaBrand($mail, $this->alvorada);
        });
    });

    it("brands the partner's referral notice with alvorada", function () {
        $member = mailAlvoradaPerson($this->alvorada, 'equipe.alvorada', 'equipe');
        $partner = Partner::withoutGlobalScopes()->create([
            'firm_id' => $this->alvorada->id,
            'name' => 'Margem Certa (exemplo)',
            'specialty' => 'precificacao_controladoria',
            'email' => 'contato@parceiro.exemplo',
            'active' => true,
        ]);
        $lead = mailAlvoradaLead($this->alvorada);
        LeadConsent::query()->create(['lead_id' => $lead->id, 'text' => 'Autorizo Alvorada Contabilidade a me apresentar a especialistas parceiros quando o diagnóstico indicar.', 'version' => 1, 'given' => true, 'via' => ConsentTerms::VIA_FORM, 'given_at' => CarbonImmutable::now()]);

        $this->actingAs($member)->postJson("/api/leads/{$lead->id}/referrals", [
            'partner_id' => $partner->id,
            'message' => 'A Alvorada indica a Clínica Horizonte.',
        ])->assertCreated();

        Mail::assertQueued(ReferralSent::class, fn (ReferralSent $mail) => assertAlvoradaBrand($mail, $this->alvorada));
    });

    it("brands the manager's 07:00 briefing with alvorada and links to its host", function () {
        $helena = mailAlvoradaPerson($this->alvorada, 'helena.alvorada', 'gestao');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-23 07:00', MAIL_ZONE));
        $this->artisan('briefing:send')->assertSuccessful();

        Mail::assertQueued(DailyBriefingMail::class, function (DailyBriefingMail $mail) use ($helena) {
            if (! $mail->hasTo($helena->email)) {
                return false;
            }

            $mail->assertSeeInHtml('http://alvorada.localhost:5173', false);

            return assertAlvoradaBrand($mail, $this->alvorada);
        });
    });
});
