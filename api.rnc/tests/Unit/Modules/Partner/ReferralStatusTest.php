<?php

use App\Modules\Partner\Enums\ReferralStatus;

/*
 * The referral's graph (rnc-2 R7, A6, D4): enviado → aceito | recusado; aceito → contato; contato →
 * reuniao; reuniao → proposta; proposta → fechado; fechado and recusado are terminal. No going back,
 * no skipping, no staying put.
 */
const STATUS_GRAPH = [
    'enviado' => ['aceito', 'recusado'],
    'aceito' => ['contato'],
    'contato' => ['reuniao'],
    'reuniao' => ['proposta'],
    'proposta' => ['fechado'],
    'fechado' => [],
    'recusado' => [],
];

/** @return array<string, array{0: string, 1: string, 2: bool}> every ordered pair, with whether the graph allows it */
function statusPairs(): array
{
    $pairs = [];

    foreach (ReferralStatus::cases() as $from) {
        foreach (ReferralStatus::cases() as $to) {
            $pairs["{$from->value} → {$to->value}"] = [$from->value, $to->value, in_array($to->value, STATUS_GRAPH[$from->value], true)];
        }
    }

    return $pairs;
}

it('allows exactly the demo graph and refuses every other pair (A6)', function (string $from, string $to, bool $allowed) {
    expect(ReferralStatus::from($from)->canTransitionTo(ReferralStatus::from($to)))->toBe($allowed);
})->with(statusPairs());

it('lists each status\'s next moves in the graph\'s order (A6)', function () {
    foreach (STATUS_GRAPH as $from => $next) {
        expect(array_map(fn (ReferralStatus $status) => $status->value, ReferralStatus::from($from)->allowedTransitions()))->toBe($next);
    }
});

it('makes fechado and recusado terminal, and only them (A6)', function () {
    $terminal = array_values(array_map(
        fn (ReferralStatus $status) => $status->value,
        array_filter(ReferralStatus::cases(), fn (ReferralStatus $status) => $status->isTerminal()),
    ));

    expect($terminal)->toBe(['fechado', 'recusado']);
});

it('labels each status with the demo\'s words', function () {
    expect(array_map(fn (ReferralStatus $status) => $status->label(), ReferralStatus::cases()))
        ->toBe(['Novo', 'Aceito', 'Em contato', 'Reunião', 'Proposta', 'Fechado', 'Recusado']);
});
