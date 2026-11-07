<?php

namespace App\Modules\Assistant\Helpers;

/**
 * The demo's matcher (`fiscal.js` `assistMatch`; rnc-3 R12, AM7), pure. A key of 4 characters or
 * more matches a word it starts ("cobr" → "cobrança"); a shorter key only the whole word, so "iss"
 * never matches "isso". Score = 2 × strong keys hit + 1 × weak keys hit; under `MIN_SCORE` finds
 * nothing. A tie goes to the newer review, then to the lower id — the demo kept the first, which
 * depended on the order of a list nobody saw.
 *
 * @phpstan-type Candidate array{id: int, strong: list<string>, weak: list<string>, reviewed_at: int}
 */
final class NoteMatcher
{
    public const MIN_SCORE = 2;

    private const STRONG_WEIGHT = 2;

    private const PREFIX_KEY_LENGTH = 4;

    /**
     * @param  iterable<Candidate>  $candidates
     * @return int|null the best candidate's id, or null when none scores `MIN_SCORE`
     */
    public static function best(string $question, iterable $candidates): ?int
    {
        $tokens = QuestionNormalizer::tokens($question);
        $best = null;

        foreach ($candidates as $candidate) {
            $score = self::score($tokens, $candidate['strong'], $candidate['weak']);

            if ($score < self::MIN_SCORE) {
                continue;
            }

            if ($best === null || self::beats([$score, $candidate], $best)) {
                $best = [$score, $candidate];
            }
        }

        return $best === null ? null : $best[1]['id'];
    }

    /**
     * @param  list<string>  $tokens  the question's words (`QuestionNormalizer::tokens`)
     * @param  list<string>  $strong
     * @param  list<string>  $weak
     */
    public static function score(array $tokens, array $strong, array $weak): int
    {
        return self::STRONG_WEIGHT * self::hits($tokens, $strong) + self::hits($tokens, $weak);
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $keys
     */
    private static function hits(array $tokens, array $keys): int
    {
        $hits = 0;

        foreach ($keys as $key) {
            $hits += self::hit($tokens, QuestionNormalizer::normalise($key)) ? 1 : 0;
        }

        return $hits;
    }

    /** @param  list<string>  $tokens */
    private static function hit(array $tokens, string $key): bool
    {
        if ($key === '') {
            return false;
        }

        foreach ($tokens as $token) {
            if (strlen($key) >= self::PREFIX_KEY_LENGTH ? str_starts_with($token, $key) : $token === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{0: int, 1: Candidate}  $challenger
     * @param  array{0: int, 1: Candidate}  $holder
     */
    private static function beats(array $challenger, array $holder): bool
    {
        [$score, $candidate] = $challenger;
        [$heldScore, $held] = $holder;

        if ($score !== $heldScore) {
            return $score > $heldScore;
        }

        if ($candidate['reviewed_at'] !== $held['reviewed_at']) {
            return $candidate['reviewed_at'] > $held['reviewed_at'];
        }

        return $candidate['id'] < $held['id'];
    }
}
