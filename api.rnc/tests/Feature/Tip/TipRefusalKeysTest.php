<?php

use App\Models\User;

/*
 * A Tip refusal names its rule with an i18n key, never with a sentence.
 *
 * Found in the overnight review (2026-09-17): `TipService` answered `__('tips.anchorTaken')` — a
 * sentence already translated by the request's locale. The SPA passes `errors.<field>[0]` to
 * `translateError`, which found no such key and showed the red "unexpected error, tell the
 * administrator" toast to someone who had only picked a place another tip had taken.
 */

function tipRefusalEditor(): User
{
    return userWithPermissions(['tips.read', 'tips.manage']);
}

function tipRefusalCreate($test, User $actor): array
{
    return $test->actingAs($actor)
        ->postJson('/api/tips', ['anchor_key' => 'users.list', 'content' => '<p>Primeira versão</p>'])
        ->assertCreated()
        ->json('data');
}

describe('a tip refusal', function () {
    it('names a taken anchor with a key, whatever the language asked for', function () {
        $actor = tipRefusalEditor();
        tipRefusalCreate($this, $actor);

        $this->actingAs($actor)
            ->withHeader('Accept-Language', 'pt-BR')
            ->postJson('/api/tips', ['anchor_key' => 'users.list', 'content' => '<p>Concorrente</p>'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.anchor_key.0', 'tips.errors.anchorTaken');
    });

    it('names a missing change reason with a key', function () {
        $actor = tipRefusalEditor();
        $tip = tipRefusalCreate($this, $actor);

        $this->actingAs($actor)
            ->patchJson("/api/tips/{$tip['id']}", ['content' => '<p>Other text</p>'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.change_reason.0', 'tips.errors.reasonRequired');
    });

    it('names a text with nothing left after sanitising with a key', function () {
        $this->actingAs(tipRefusalEditor())
            ->postJson('/api/tips', ['anchor_key' => 'users.list', 'content' => '<p> </p>'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.content.0', 'tips.errors.contentRequired');
    });
});
