<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerificationCodeMail;
use App\Modules\Booking\DTOs\MeetingMailDTO;
use App\Modules\Booking\Mail\BookingConfirmed;
use App\Modules\Booking\Mail\MeetingReminder;
use App\Modules\Briefing\DTOs\BriefingMailDTO;
use App\Modules\Briefing\Helpers\ActionPicker;
use App\Modules\Briefing\Mail\DailyBriefingMail;
use App\Modules\Firm\Services\FirmResolver;
use App\Modules\Firm\Services\MailBrand;
use App\Modules\Partner\DTOs\ReferralMailDTO;
use App\Modules\Partner\Mail\ReferralSent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Mail\Mailable;

/**
 * Renders the e-mails in a browser, so they can be looked at without being sent.
 *
 * An e-mail is the one screen in a product nobody opens while building it: it is written,
 * sent to a log, and seen for the first time by the person it was written for. This is the
 * cheapest possible fix — a URL per message, and an index that lists them.
 *
 * LOCAL ONLY. Registered behind `app()->isLocal()` in `routes/web.php`, because these
 * routes render with sample data and no authentication, and the sample data is the whole
 * point. Nothing here should ever answer in production.
 *
 * Every message wears a firm's brand (rnc-4 R12): `?firma=<slug>` picks the firm, else the default
 * firm; with no firm at all, the platform's own look.
 */
class MailPreviewController extends Controller
{
    /**
     * Every message the API can send, with the sample arguments to render it.
     *
     * A new Mailable adds a line here. That is deliberate: a preview needs example data,
     * and only the person writing the message knows what a realistic example looks like.
     *
     * @return array<string, array{label: string, make: callable(): Mailable}>
     */
    private function messages(): array
    {
        return [
            'verification-code' => [
                'label' => 'Registration — verification code',
                'make' => fn (): Mailable => new EmailVerificationCodeMail('482913'),
            ],
            'booking-confirmed' => [
                'label' => 'Booking — confirmation with the Relatório de Impacto link',
                'make' => fn (): Mailable => (new BookingConfirmed($this->sampleMeeting()))->locale('pt_BR'),
            ],
            'meeting-reminder' => [
                'label' => 'Booking — the 24-hour reminder',
                'make' => fn (): Mailable => (new MeetingReminder($this->sampleMeeting()))->locale('pt_BR'),
            ],
            'referral-sent' => [
                'label' => 'Network — a referral to a partner (company and the firm\'s contact, never the lead\'s)',
                'make' => fn (): Mailable => (new ReferralSent($this->sampleReferral()))->locale('pt_BR'),
            ],
            'daily-briefing' => [
                'label' => 'Briefing — the manager\'s 07:00 e-mail on business days (rnc-3 F8)',
                'make' => fn (): Mailable => (new DailyBriefingMail($this->sampleBriefing()))->locale('pt_BR'),
            ],
        ];
    }

    /** The firm `?firma=` names, else the default firm, else the platform's own look. */
    private function sampleBrand(): MailBrand
    {
        $resolver = app(FirmResolver::class);
        $slug = request()->query('firma');
        $firm = (is_string($slug) ? $resolver->findTenantBySlug($slug) : null) ?? $resolver->findDefaultFirm();

        return $firm ? MailBrand::fromFirm($firm) : MailBrand::platform();
    }

    /** A meeting as a visitor of the demo would book it (invented names, rnc-1 F4). */
    private function sampleMeeting(): MeetingMailDTO
    {
        return new MeetingMailDTO(
            firstName: 'Ana',
            company: 'Clínica Horizonte',
            specialist: 'Juliana Reis',
            area: 'Contábil · Simples Nacional',
            startsAt: CarbonImmutable::parse('2026-11-24 14:30', 'America/Sao_Paulo'),
            minutes: 30,
            opportunities: 3,
            risks: 2,
            reportUrl: $this->sampleBrand()->siteUrl.'/relatorio/exemplo',
            firmName: $this->sampleBrand()->name,
            firmAddress: 'Rua das Acácias, 120, Centro, Cidade Exemplo-SP',
            firmPhone: '11 4000-1234',
            brand: $this->sampleBrand(),
        );
    }

    /** A referral as the team would send it from the lead detail (invented names, rnc-1 F6). */
    private function sampleReferral(): ReferralMailDTO
    {
        return new ReferralMailDTO(
            partnerName: 'Margem Certa (exemplo)',
            specialty: 'Precificação e controladoria',
            company: 'Consultoria Prisma',
            sector: 'servicos',
            covers: ['Crédito do Simples limitado', 'Regime de 30% para serviços', 'Contratos de longo prazo'],
            message: 'Indicamos a Consultoria Prisma. Podem fazer o primeiro contato em até 24 horas úteis?',
            referredBy: 'Patrícia Gomes',
            firmName: $this->sampleBrand()->name,
            firmPhone: '11 4000-1234',
            firmMailbox: $this->sampleBrand()->mailbox,
            firmAddress: 'Rua das Acácias, 120, Centro, Cidade Exemplo-SP',
            brand: $this->sampleBrand(),
        );
    }

    /** A Thursday's briefing as the seed would give it (invented names, rnc-3 F8). */
    private function sampleBriefing(): BriefingMailDTO
    {
        return new BriefingMailDTO(
            firstName: 'Helena',
            firmName: $this->sampleBrand()->name,
            frontendUrl: $this->sampleBrand()->siteUrl,
            briefing: [
                'as_of' => '2026-11-19T07:00:00-03:00',
                'leads' => ['last_7_days' => 23, 'last_24_hours' => 4],
                'meetings' => ['count' => 6, 'today' => 2, 'window_end' => '2026-11-26', 'next' => [
                    ['lead_id' => 1, 'company' => 'Clínica Horizonte', 'starts_at' => '2026-11-19T10:30:00-03:00', 'specialist' => 'Juliana Reis'],
                    ['lead_id' => 2, 'company' => 'Padaria Sol', 'starts_at' => '2026-11-19T15:00:00-03:00', 'specialist' => 'Mariana Toledo'],
                ]],
                'referrals' => ['count' => 7, 'by_state' => ['ok' => 3, 'atencao' => 2, 'estourado' => 2], 'goal_hours' => 24, 'more' => 2, 'top' => [
                    ['id' => 1, 'lead_id' => 3, 'company' => 'Metalúrgica Aço Forte', 'partner' => ['id' => 1, 'name' => 'Andrade Advocacia (exemplo)'], 'state' => 'estourado', 'hours' => 31.5],
                    ['id' => 2, 'lead_id' => 4, 'company' => 'Consultoria Prisma', 'partner' => ['id' => 2, 'name' => 'Margem Certa (exemplo)'], 'state' => 'atencao', 'hours' => 14],
                ]],
                'pipeline' => ['count' => 12, 'range_cents' => [32_400_000, 86_400_000], 'stages' => []],
                'actions' => [
                    ['kind' => ActionPicker::SLA_BREACHED, 'params' => ['partner' => 'Andrade Advocacia (exemplo)', 'company' => 'Metalúrgica Aço Forte', 'hours' => 31.5, 'goal_hours' => 24], 'to' => '/gestao/trilha?parceiro=1'],
                    ['kind' => ActionPicker::MEETINGS_TODAY, 'params' => ['count' => 2], 'to' => '/equipe/agenda'],
                    ['kind' => ActionPicker::PROPOSALS, 'params' => ['count' => 3, 'range_cents' => [5_400_000, 10_800_000]], 'to' => '/equipe/pipeline?etapa=proposta'],
                ],
            ],
            brand: $this->sampleBrand(),
        );
    }

    public function index(): Response
    {
        $rows = '';
        foreach ($this->messages() as $key => $message) {
            $rows .= sprintf(
                '<li><a href="/mail-preview/%s">%s</a> <code>%s</code> '
                .'<a href="/mail-preview/%s?format=text">text</a></li>',
                $key,
                e($message['label']),
                e($key),
                $key,
            );
        }

        return response(
            '<!doctype html><meta charset="utf-8"><title>Mail preview</title>'
            .'<body style="font-family:system-ui;margin:40px;line-height:1.6">'
            .'<h1>Mail preview</h1>'
            .'<p>Rendered with sample data. Local only.</p>'
            ."<ul>{$rows}</ul></body>"
        );
    }

    public function show(string $message): Response
    {
        $messages = $this->messages();

        abort_unless(isset($messages[$message]), 404);

        $mailable = $messages[$message]['make']();

        // The text alternative is half the message and never gets looked at. One query string
        // away is close enough that it might actually be read.
        //
        // Rendered from the `Content` the Mailable declares, because `Mailable` exposes no
        // renderText(): `render()` gives the HTML part and nothing gives the other one.
        if (request()->query('format') === 'text') {
            // `content()` is the Mailable convention, not part of the base class: a message built
            // the older way (`build()`) has no declared text part to show.
            abort_unless(method_exists($mailable, 'content'), 404, 'This message declares no content.');

            $content = $mailable->content();

            abort_unless($content->text, 404, 'This message has no text alternative.');

            $text = view($content->text, array_merge($mailable->buildViewData(), $content->with))->render();

            return response($text, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return response($mailable->render());
    }
}
