<?php

use App\Models\User;

/*
 * Nothing this API serves is meant to be indexed, quoted or used as training data.
 *
 * Two mechanisms, deliberately both, because they answer different questions. `robots.txt` asks
 * a crawler not to FETCH; `X-Robots-Tag` tells one that fetched anyway not to INDEX. A URL
 * merely disallowed in robots.txt can still appear in an index from an inbound link, because the
 * crawler never reads the page to discover it is unwelcome — only the header removes it, and a
 * JSON API has no meta tag to carry instead.
 *
 * Neither is a control. Both are requests that well-behaved crawlers honour; the real protection
 * is authentication. That is why this file asserts they exist rather than that they work.
 */

describe('robots.txt', function () {
    it('disallows every path, rather than allowing every path', function () {
        $robots = file_get_contents(public_path('robots.txt'));

        // The bootstrap shipped `Disallow:` with nothing after it, which does not mean "block" —
        // an empty Disallow means ALLOW EVERYTHING. It is the one directive whose failure mode
        // is the exact inverse of what it looks like.
        expect($robots)->toContain('Disallow: /');
        expect($robots)->not->toMatch('/^Disallow:\s*$/m');
    });

    it('names the AI crawlers individually as well as by wildcard', function () {
        $robots = file_get_contents(public_path('robots.txt'));

        // Several operators honour only their own token and treat `User-agent: *` as not
        // addressed to them, so the wildcard alone is not the same request.
        expect($robots)->toContain('User-agent: *');

        foreach (['GPTBot', 'ClaudeBot', 'Google-Extended', 'Bytespider', 'PerplexityBot'] as $crawler) {
            expect($robots)->toContain($crawler);
        }
    });

    it('refuses training in every user-agent group, not just the first', function () {
        $robots = file_get_contents(public_path('robots.txt'));

        // `Content-Signal` states what may be DONE with the content, which `Disallow` does not
        // cover. It is not redundant here: Claude-User, ChatGPT-User and Perplexity-User are
        // USER-TRIGGERED fetches, which their operators document as NOT bound by robots.txt
        // exclusion — somebody pasted a link and the agent goes to read it. For those three
        // this line is the only statement that reaches them, and the only one of the three
        // layers that says anything about TRAINING at all; `noindex` speaks about indexing.
        $signals = preg_match_all('/^Content-Signal:\s*(.+)$/m', $robots, $matches);

        // One per group. A signal in the wildcard group alone is silently ignored by every
        // operator that reads only its own token — the same trap as the wildcard Disallow.
        $groups = preg_match_all('/^User-agent: \*$/m', $robots)
            + (int) str_contains($robots, 'User-agent: ClaudeBot');

        // Anchor the count before comparing it: `$signals === $groups` is trivially true when
        // BOTH are zero, so an emptied robots.txt would satisfy this silently. app.rnc's
        // equivalent e2e spec passed against a blank page until a mutation run caught it.
        expect($groups)->toBeGreaterThan(1);
        expect($signals)->toBe($groups);

        foreach ($matches[1] as $signal) {
            expect($signal)->toContain('ai-train=no');
            expect($signal)->toContain('ai-input=no');
            expect($signal)->toContain('search=no');
        }
    });
});

describe('X-Robots-Tag', function () {
    it('stamps an API response', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');
    });

    it('stamps a web response too', function () {
        $this->get('/')->assertHeader('X-Robots-Tag');
    });

    it('stamps the health check, the one route with nothing in front of it', function () {
        $this->get('/up')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');
    });

    it('stamps an error, which is the response a crawler is most likely to reach', function () {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertHeader('X-Robots-Tag');
    });
});
