<?php

use App\Modules\Assistant\Helpers\NoteMatcher;
use App\Modules\Assistant\Helpers\QuestionNormalizer;

/*
 * A11 (rnc-3 R12, AM7): the demo's matcher (`fiscal.js` `assistMatch`), pure. The question is
 * lower-cased, stripped of accents and of anything not a letter, a digit or a space, and split into
 * words. A key of 4 letters or more matches a word it starts ("cobr" → "cobrança"); a shorter key
 * only the whole word, so "iss" never matches "isso". Score = 2 × strong keys hit + 1 × weak keys
 * hit; under 2 finds nothing; a tie goes to the newer review, then to the lower id.
 */

/** Three of the demo's notes, as the matcher receives them. */
function matcherNotes(): array
{
    return [
        ['id' => 1, 'strong' => ['cbs'], 'weak' => ['cobr', 'comec', 'quando', '2027', 'pis', 'cofins'], 'reviewed_at' => 1_000],
        ['id' => 2, 'strong' => ['icms', 'iss'], 'weak' => ['2033', 'transic', 'extin', 'benefic'], 'reviewed_at' => 1_000],
        ['id' => 9, 'strong' => ['aliquot'], 'weak' => ['referenc', 'ibs', 'cbs', '26'], 'reviewed_at' => 1_000],
    ];
}

describe('QuestionNormalizer (A11, A14)', function () {
    it('lower-cases, strips accents and punctuation, and splits into words', function () {
        expect(QuestionNormalizer::tokens('Quando começa a cobrança da CBS?'))->toBe(['quando', 'comeca', 'a', 'cobranca', 'da', 'cbs'])
            ->and(QuestionNormalizer::tokens('  NF-e   rejeição 1115! '))->toBe(['nfe', 'rejeicao', '1115']);
    });

    it('gives "Quando começa a CBS?" and "quando comeca a cbs" one normalised text', function () {
        expect(QuestionNormalizer::normalise('Quando começa a CBS?'))->toBe('quando comeca a cbs')
            ->and(QuestionNormalizer::normalise('quando   comeca a cbs'))->toBe('quando comeca a cbs');
    });

    it('answers no words for a question of punctuation only', function () {
        expect(QuestionNormalizer::tokens('?!… --'))->toBe([]);
    });
});

describe('NoteMatcher (A11)', function () {
    it('finds the cbs note for "quando começa a cobrança da CBS?"', function () {
        expect(NoteMatcher::best('quando começa a cobrança da CBS?', matcherNotes()))->toBe(1);
    });

    it('matches "cobrança" with the 4-letter key "cobr"', function () {
        expect(NoteMatcher::score(QuestionNormalizer::tokens('cobrança'), [], ['cobr']))->toBe(1);
    });

    it('matches a 3-letter key only as the whole word: "iss" hits "ISS" and never "isso"', function () {
        expect(NoteMatcher::score(QuestionNormalizer::tokens('isso vale?'), ['iss'], []))->toBe(0)
            ->and(NoteMatcher::score(QuestionNormalizer::tokens('e o ISS?'), ['iss'], []))->toBe(2)
            ->and(NoteMatcher::score(QuestionNormalizer::tokens('cbsx'), ['cbs'], []))->toBe(0);
    });

    it('counts a strong key twice and a weak key once', function () {
        expect(NoteMatcher::score(QuestionNormalizer::tokens('cbs em 2027'), ['cbs'], ['2027', 'pis']))->toBe(3);
    });

    it('finds nothing on a score of 1', function () {
        // "pis" is one weak key of the cbs note: 1 < 2.
        expect(NoteMatcher::best('e o pis?', matcherNotes()))->toBeNull()
            ->and(NoteMatcher::best('qual o melhor ERP', matcherNotes()))->toBeNull();
    });

    it('finds a note on a score of exactly 2', function () {
        expect(NoteMatcher::best('quando acaba o pis', matcherNotes()))->toBe(1);
    });

    it('gives a tie to the newer review', function () {
        $notes = [
            ['id' => 3, 'strong' => ['split'], 'weak' => [], 'reviewed_at' => 1_000],
            ['id' => 4, 'strong' => ['split'], 'weak' => [], 'reviewed_at' => 2_000],
            ['id' => 5, 'strong' => ['split'], 'weak' => [], 'reviewed_at' => 1_500],
        ];

        expect(NoteMatcher::best('split payment', $notes))->toBe(4);
    });

    it('gives a tie of the same review moment to the lower id', function () {
        $notes = [
            ['id' => 8, 'strong' => ['split'], 'weak' => [], 'reviewed_at' => 1_000],
            ['id' => 6, 'strong' => ['split'], 'weak' => [], 'reviewed_at' => 1_000],
            ['id' => 7, 'strong' => ['split'], 'weak' => [], 'reviewed_at' => 1_000],
        ];

        expect(NoteMatcher::best('split payment', $notes))->toBe(6);
    });

    it('prefers a higher score over a newer review', function () {
        $notes = [
            ['id' => 1, 'strong' => ['split'], 'weak' => ['caixa'], 'reviewed_at' => 1_000],
            ['id' => 2, 'strong' => ['split'], 'weak' => [], 'reviewed_at' => 9_000],
        ];

        expect(NoteMatcher::best('split no caixa', $notes))->toBe(1);
    });

    it('reads keys written with accents or capitals the way it reads the question', function () {
        expect(NoteMatcher::score(QuestionNormalizer::tokens('redução'), [], ['Reduç']))->toBe(1);
    });

    it('finds nothing among no notes', function () {
        expect(NoteMatcher::best('quando começa a CBS', []))->toBeNull();
    });
});
