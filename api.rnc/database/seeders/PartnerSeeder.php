<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\Enums\LeadEventType;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Partner\Enums\PartnerSpecialty;
use App\Modules\Partner\Enums\ReferralStatus;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use App\Modules\Partner\Services\PartnerMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The network to show (rnc-1 F6, R21), outside production only: the demo's eight invented partners,
 * one per specialty of facts.md §3, each marked "(exemplo)" with an invented e-mail
 * (`@parceiro.exemplo`) and phone (`55 11 9000-9NNN`) — placeholders until Franco names its own.
 * Then a few referrals in the trail, each for a seeded lead that SAID YES, to the partner that
 * covers the most of its diagnostic, with the consent copied and the timeline line written.
 *
 * Deterministic, after `PipelineSeeder` (it needs the leads). Runs inside `CurrentFirm::runAs` and
 * writes `firm_id` itself — `DatabaseSeeder` runs without model events (state.md Pitfalls).
 */
class PartnerSeeder extends Seeder
{
    /** @var list<array{0: string, 1: PartnerSpecialty, 2: string}> name, specialty, city */
    private const PARTNERS = [
        ['Andrade Tributário (exemplo)', PartnerSpecialty::AdvocaciaTributaria, 'São Paulo-SP'],
        ['Recupera Créditos (exemplo)', PartnerSpecialty::RecuperacaoCreditos, 'São Paulo-SP'],
        ['NexoERP (exemplo)', PartnerSpecialty::ErpNfe, 'Barueri-SP'],
        ['Giro Capital (exemplo)', PartnerSpecialty::CreditoCapitalGiro, 'São Paulo-SP'],
        ['Amparo Seguros (exemplo)', PartnerSpecialty::SegurosBeneficios, 'Osasco-SP'],
        ['Herança Societário (exemplo)', PartnerSpecialty::SocietarioSucessao, 'São Paulo-SP'],
        ['Margem Certa (exemplo)', PartnerSpecialty::PrecificacaoControladoria, 'Santo André-SP'],
        ['CertiDigital (exemplo)', PartnerSpecialty::CertificadoDigital, 'São Paulo-SP'],
    ];

    private const PHONE_PREFIX = '551190009';

    /** The stages whose leads have a diagnostic and a conversation worth handing over. */
    private const REFERABLE_STAGES = ['lead', 'reuniao_marcada', 'proposta', 'cliente'];

    private const REFERRALS = 7;

    /** Who sends them — the team that works the board. */
    private const TEAM = ['patricia', 'mariana.toledo', 'carlos.prado'];

    private const MESSAGE = 'A Franco indica a %s. Podem fazer o primeiro contato em até 24 horas úteis? Nós apresentamos vocês.';

    public function run(CurrentFirm $currentFirm, PartnerMatcher $partnerMatcher): void
    {
        if (app()->environment('production')) {
            return;
        }

        $firm = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->first();

        if (! $firm) {
            return;
        }

        $currentFirm->runAs($firm, fn () => $this->seedFirm($firm, $partnerMatcher));
    }

    private function seedFirm(Firm $firm, PartnerMatcher $partnerMatcher): void
    {
        $partners = new Collection;

        foreach (self::PARTNERS as $index => [$name, $specialty, $city]) {
            $partners->push(Partner::query()->create([
                'firm_id' => $firm->id,
                'name' => $name,
                'specialty' => $specialty->value,
                'email' => 'contato'.($index + 1).'@parceiro.exemplo',
                'phone' => self::PHONE_PREFIX.sprintf('%03d', $index + 1),
                'city' => $city,
                'active' => true,
            ]));
        }

        $this->seedReferrals($firm, $partners, $partnerMatcher);
    }

    /** @param  Collection<int, Partner>  $partners */
    private function seedReferrals(Firm $firm, Collection $partners, PartnerMatcher $partnerMatcher): void
    {
        $team = User::query()->whereIn('username', self::TEAM)->orderBy('id')->get()->values();

        if ($team->isEmpty()) {
            return;
        }

        $leads = Lead::query()
            ->with('latestConsent')
            ->whereIn('stage', self::REFERABLE_STAGES)
            ->orderBy('id')
            ->get()
            ->filter(fn (Lead $lead) => $lead->latestConsent?->given === true)
            ->values()
            ->take(self::REFERRALS);
        $now = CarbonImmutable::now();

        foreach ($leads as $index => $lead) {
            $matches = $partnerMatcher->match($lead, $partners);
            $covering = array_values(array_filter($matches, fn (array $match) => $match['covers'] !== []));
            $match = $covering === [] ? $matches[0] : $covering[$index % count($covering)];
            $member = $team[$index % $team->count()];
            $at = CarbonImmutable::parse($lead->updated_at)->addHours(2)->min($now);
            $consent = $lead->latestConsent;

            $referral = Referral::query()->create([
                'firm_id' => $firm->id,
                'lead_id' => $lead->id,
                'partner_id' => $match['partner']->id,
                'referred_by' => $member->id,
                'message' => sprintf(self::MESSAGE, $lead->company),
                'score' => $match['score'],
                'covers' => $match['covers'],
                'consent_text' => $consent->text,
                'consent_version' => $consent->version,
                'consent_given_at' => $consent->given_at,
                'status' => ReferralStatus::Enviado->value,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            LeadEvent::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $member->id,
                'type' => LeadEventType::ReferralSent->value,
                'data' => [
                    'referral_id' => $referral->id,
                    'partner_id' => $match['partner']->id,
                    'partner' => $match['partner']->name,
                    'specialty' => $match['partner']->specialty,
                    'score' => $match['score'],
                ],
                'at' => $at,
            ]);
        }
    }
}
