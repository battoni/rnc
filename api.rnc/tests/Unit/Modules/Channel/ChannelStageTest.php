<?php

use App\Modules\Channel\Enums\ChannelStage;

/*
 * A8 (rnc-3 R6, R7): an RNC firm walks contato → demo → implantacao → ativo, one step at a time and
 * forward only; ativo is terminal. Skipping, going back and staying put are all refused.
 */
const CHANNEL_STAGE_ORDER = ['contato', 'demo', 'implantacao', 'ativo'];

/** @return array<string, array{0: string, 1: string, 2: bool}> every ordered pair, with whether the graph allows it */
function channelStagePairs(): array
{
    $pairs = [];

    foreach (CHANNEL_STAGE_ORDER as $i => $from) {
        foreach (CHANNEL_STAGE_ORDER as $j => $to) {
            $pairs["{$from} → {$to}"] = [$from, $to, $j === $i + 1];
        }
    }

    return $pairs;
}

describe('ChannelStage (A8)', function () {
    it('lists the four stages in the demo\'s order', function () {
        expect(array_map(fn (ChannelStage $stage) => $stage->value, ChannelStage::cases()))->toBe(CHANNEL_STAGE_ORDER)
            ->and(ChannelStage::values())->toBe(CHANNEL_STAGE_ORDER);
    });

    it('allows only the next stage', function (string $from, string $to, bool $allowed) {
        expect(ChannelStage::from($from)->canTransitionTo(ChannelStage::from($to)))->toBe($allowed);
    })->with(channelStagePairs());

    it('names the next stage, and none after ativo', function () {
        expect(ChannelStage::Contato->next())->toBe(ChannelStage::Demo)
            ->and(ChannelStage::Demo->next())->toBe(ChannelStage::Implantacao)
            ->and(ChannelStage::Implantacao->next())->toBe(ChannelStage::Ativo)
            ->and(ChannelStage::Ativo->next())->toBeNull();
    });

    it('makes ativo the only terminal stage', function () {
        expect(array_map(fn (ChannelStage $stage) => $stage->isTerminal(), ChannelStage::cases()))->toBe([false, false, false, true]);
    });
});
