<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SectorPageSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/*
 * The funnel's "Por canal" table (rnc-2 F5, R14, A12): from "início diagnóstico" on, the visitors
 * split by the channel their lead started in — formulário · chat, both rows always listed. A
 * visitor is counted under its lead's channel; one that never started a lead has no channel and
 * sits in neither row (it never reached "início").
 */
function funnelPerson(string $role): User
{
    return User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

/** @return array<string, array<string, int|string>> channel → its row */
function channelRows(User $person): array
{
    return collect(test()->actingAs($person)->getJson('/api/metrics/funnel?days=7')->assertOk()->json('data.by_channel'))->keyBy('channel')->all();
}

/** A visitor walking the public diagnostic through the API, as the site does, up to `$until`. */
function channelWalk(string $channel, string $until, bool $throughCta = true): void
{
    $visitor = (string) Str::uuid();
    $beacon = fn (string $type, ?string $token = null) => test()->postJson('/api/public/events', array_filter([
        'visitor' => $visitor, 'type' => $type, 'path' => $type === 'visit' && $throughCta ? '/' : '/diagnostico', 'source' => 'organico', 'lead_token' => $token,
    ]))->assertCreated();

    $beacon('visit');

    if ($throughCta) {
        $beacon('cta');
    }

    $token = test()->postJson('/api/public/diagnostics', ['path' => 'quiz', 'visitor' => $visitor, 'source' => 'organico', 'channel' => $channel])->assertCreated()->json('data.token');

    foreach (['diag.start', 'diag.contact', 'diag.result', 'booking'] as $type) {
        $beacon($type, $token);

        if ($type === $until) {
            return;
        }
    }
}

beforeEach(function () {
    Http::preventStrayRequests();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, SectorPageSeeder::class]);
    $this->ceo = funnelPerson('gestao');
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('the funnel by channel (R14, A12)', function () {
    it('lists formulário and chat, in that order, from início on, even with no traffic', function () {
        $rows = channelRows($this->ceo);

        expect(array_keys($rows))->toBe(['formulario', 'chat'])
            ->and($rows['chat'])->toBe(['channel' => 'chat', 'inicio' => 0, 'contato' => 0, 'resultado' => 0, 'agendou' => 0, 'cliente' => 0]);
    });

    it('counts a chat walk under chat and not under formulário', function () {
        $before = channelRows($this->ceo);

        channelWalk('chat', 'booking');

        $after = channelRows($this->ceo);

        expect($after['chat'])->toMatchArray(['inicio' => 1, 'contato' => 1, 'resultado' => 1, 'agendou' => 1, 'cliente' => 0])
            ->and($after['formulario'])->toBe($before['formulario']);
    });

    it('counts each walk under its own channel, a step at a time', function () {
        channelWalk('formulario', 'diag.result');
        channelWalk('formulario', 'diag.start');
        channelWalk('chat', 'diag.contact');

        $rows = channelRows($this->ceo);

        expect($rows['formulario'])->toMatchArray(['inicio' => 2, 'contato' => 1, 'resultado' => 1, 'agendou' => 0])
            ->and($rows['chat'])->toMatchArray(['inicio' => 1, 'contato' => 1, 'resultado' => 0]);
    });

    it('counts a walk that opened /diagnostico straight, with no CTA click, from início on', function () {
        // the QA of 07/11: three diagnostics entered by the link, São Paulo afternoon, the funnel read minutes later
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-07 15:19', 'America/Sao_Paulo'));
        channelWalk('chat', 'diag.result', throughCta: false);
        channelWalk('chat', 'diag.start', throughCta: false);
        channelWalk('formulario', 'diag.result', throughCta: false);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-07 15:25', 'America/Sao_Paulo'));

        $funnel = $this->actingAs($this->ceo)->getJson('/api/metrics/funnel?days=7')->assertOk()->json('data');
        $rows = collect($funnel['by_channel'])->keyBy('channel');

        expect($rows['chat'])->toMatchArray(['inicio' => 2, 'contato' => 1, 'resultado' => 1])
            ->and($rows['formulario'])->toMatchArray(['inicio' => 1, 'contato' => 1, 'resultado' => 1])
            ->and(array_column($funnel['steps'], 'count', 'key'))->toMatchArray(['visitas' => 3, 'cta' => 3, 'inicio' => 3, 'contato' => 2, 'resultado' => 2]);
    });

    it('keeps the two rows within the funnel\'s own início', function () {
        channelWalk('formulario', 'booking');
        channelWalk('chat', 'diag.start');

        $funnel = $this->actingAs($this->ceo)->getJson('/api/metrics/funnel?days=7')->json('data');
        $inicio = collect($funnel['steps'])->firstWhere('key', 'inicio')['count'];

        expect(collect($funnel['by_channel'])->sum('inicio'))->toBe($inicio)
            ->and(Lead::withoutGlobalScopes()->where('channel', 'chat')->count())->toBe(1);
    });
});
