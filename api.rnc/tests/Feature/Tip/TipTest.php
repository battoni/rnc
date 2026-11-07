<?php

use App\Models\User;
use App\Modules\Tip\Models\Tip;
use App\Modules\Tip\Models\TipHistory;

function manageActor(): User
{
    return userWithPermissions(['tips.read', 'tips.manage']);
}

function createTip($test, $actor, array $overrides = []): array
{
    return $test->actingAs($actor)
        ->postJson('/api/tips', array_merge([
            'anchor_key' => 'users.list',
            'content' => '<p>Primeira versão</p>',
        ], $overrides))
        ->assertCreated()
        ->json('data');
}

describe('Tip CRUD', function () {
    it('creates a tip and opens version 1 with it', function () {
        $actor = manageActor();

        $tip = createTip($this, $actor);

        expect($tip['anchor_key'])->toBe('users.list');
        expect($tip['content'])->toBe('<p>Primeira versão</p>');

        $rows = TipHistory::where('tip_id', $tip['id'])->get();
        expect($rows)->toHaveCount(1);
        expect($rows[0]->content)->toBe('<p>Primeira versão</p>');
        expect($rows[0]->event)->toBe('created');
        expect($rows[0]->changed_by)->toBe($actor->id);
    });

    it('refuses a second active tip on an anchor that is already taken', function () {
        $actor = manageActor();
        createTip($this, $actor);

        $this->actingAs($actor)
            ->postJson('/api/tips', [
                'anchor_key' => 'users.list',
                'content' => '<p>Concorrente</p>',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('anchor_key');
    });

    it('demands a reason when the content changes', function () {
        $actor = manageActor();
        $tip = createTip($this, $actor);

        $this->actingAs($actor)
            ->patchJson("/api/tips/{$tip['id']}", ['content' => '<p>Other text</p>'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('change_reason');

        expect(TipHistory::where('tip_id', $tip['id'])->count())->toBe(1);
    });

    it('does not demand a reason to move a tip to another free anchor', function () {
        $actor = manageActor();
        $tip = createTip($this, $actor);

        $this->actingAs($actor)
            ->patchJson("/api/tips/{$tip['id']}", ['anchor_key' => 'roles.permissions'])
            ->assertOk()
            ->assertJsonPath('data.anchor_key', 'roles.permissions');

        // Moving does not rewrite what anyone read, so it opens no version.
        expect(TipHistory::where('tip_id', $tip['id'])->count())->toBe(1);
    });

    it('appends a version carrying the new text, the author and the reason', function () {
        $actor = manageActor();
        $tip = createTip($this, $actor);

        $this->actingAs($actor)
            ->patchJson("/api/tips/{$tip['id']}", [
                'content' => '<p>Revised text</p>',
                'change_reason' => 'termo errado',
            ])
            ->assertOk()
            ->assertJsonPath('data.content', '<p>Revised text</p>');

        $rows = TipHistory::where('tip_id', $tip['id'])->orderBy('id')->get();
        expect($rows)->toHaveCount(2);
        expect($rows[1]->content)->toBe('<p>Revised text</p>');
        expect($rows[1]->event)->toBe('updated');
        expect($rows[1]->change_reason)->toBe('termo errado');
        expect($rows[1]->changed_by)->toBe($actor->id);

        // The older version keeps its own text, which is what makes it restorable.
        expect($rows[0]->content)->toBe('<p>Primeira versão</p>');
    });

    it('opens no version when the content is submitted unchanged', function () {
        $actor = manageActor();
        $tip = createTip($this, $actor);

        $this->actingAs($actor)
            ->patchJson("/api/tips/{$tip['id']}", ['content' => '<p>Primeira versão</p>'])
            ->assertOk();

        expect(TipHistory::where('tip_id', $tip['id'])->count())->toBe(1);
    });

    it('rejects content that is empty once markup is stripped', function () {
        $actor = manageActor();

        $this->actingAs($actor)
            ->postJson('/api/tips', [
                'anchor_key' => 'roles.permissions',
                'content' => '<p><br></p>',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content');
    });
});

describe('Tip archiving', function () {
    it('archives with a reason, records the event, and frees the anchor', function () {
        $actor = manageActor();
        $tip = createTip($this, $actor);

        $this->actingAs($actor)
            ->deleteJson("/api/tips/{$tip['id']}", ['change_reason' => 'no longer applies'])
            ->assertOk()
            ->assertJsonPath('data.archived_at', fn ($value) => $value !== null);

        $rows = TipHistory::where('tip_id', $tip['id'])->orderBy('id')->get();
        expect($rows)->toHaveCount(2);
        expect($rows[1]->event)->toBe('archived');
        expect($rows[1]->change_reason)->toBe('no longer applies');

        // The anchor is free again.
        createTip($this, $actor, ['content' => '<p>Substituta</p>']);
    });

    it('refuses to archive without a reason', function () {
        $actor = manageActor();
        $tip = createTip($this, $actor);

        $this->actingAs($actor)
            ->deleteJson("/api/tips/{$tip['id']}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('change_reason');
    });

    it('refuses to unarchive onto an anchor another tip has taken meanwhile', function () {
        $actor = manageActor();
        $tip = createTip($this, $actor);

        $this->actingAs($actor)->deleteJson("/api/tips/{$tip['id']}", ['change_reason' => 'saiu']);
        createTip($this, $actor, ['content' => '<p>Substituta</p>']);

        $this->actingAs($actor)
            ->patchJson("/api/tips/{$tip['id']}/unarchive")
            ->assertStatus(422)
            ->assertJsonValidationErrors('anchor_key');
    });

    it('keeps an archived tip out of the rendering payload', function () {
        $actor = manageActor();
        $tip = createTip($this, $actor);

        $this->actingAs($actor)->deleteJson("/api/tips/{$tip['id']}", ['change_reason' => 'saiu']);

        $anchors = collect($this->actingAs($actor)->getJson('/api/tips/active')->json('data'))
            ->pluck('anchor_key');

        expect($anchors)->not->toContain('users.list');
    });
});

describe('Tip restore', function () {
    it('appends a new version with the old text instead of rewinding the log', function () {
        $actor = manageActor();
        $tip = createTip($this, $actor);
        $originalVersionId = TipHistory::where('tip_id', $tip['id'])->value('id');

        $this->actingAs($actor)->patchJson("/api/tips/{$tip['id']}", [
            'content' => '<p>Revised text</p>',
            'change_reason' => 'ajuste',
        ]);

        $this->actingAs($actor)
            ->postJson("/api/tips/{$tip['id']}/restore/{$originalVersionId}")
            ->assertOk()
            ->assertJsonPath('data.content', '<p>Primeira versão</p>');

        $rows = TipHistory::where('tip_id', $tip['id'])->orderBy('id')->get();
        expect($rows)->toHaveCount(3);
        expect($rows[2]->content)->toBe('<p>Primeira versão</p>');
        expect($rows[2]->event)->toBe('restored');
        expect($rows[2]->restored_from_id)->toBe($originalVersionId);

        // The version that was restored FROM is still there, unmodified.
        expect($rows[1]->content)->toBe('<p>Revised text</p>');
    });

    it('refuses a version belonging to another tip', function () {
        $actor = manageActor();
        $first = createTip($this, $actor);
        $second = createTip($this, $actor, ['anchor_key' => 'roles.permissions', 'content' => '<p>Outra</p>']);
        $foreignVersionId = TipHistory::where('tip_id', $second['id'])->value('id');

        $this->actingAs($actor)
            ->postJson("/api/tips/{$first['id']}/restore/{$foreignVersionId}")
            ->assertNotFound();
    });
});

describe('Tip sanitising', function () {
    it('strips script tags and event handlers before storing', function () {
        $actor = manageActor();

        $tip = createTip($this, $actor, [
            'content' => '<p>Olá</p><script>alert(1)</script><p onclick="alert(2)">clique</p>',
        ]);

        expect($tip['content'])->not->toContain('script');
        expect($tip['content'])->not->toContain('onclick');
        expect($tip['content'])->toContain('Olá');

        // What is STORED is what was made safe, not just what is rendered.
        expect(Tip::find($tip['id'])->content)->not->toContain('script');
    });

    it('drops javascript: links while keeping http ones', function () {
        $actor = manageActor();

        $tip = createTip($this, $actor, [
            'content' => '<p><a href="javascript:alert(1)">bad</a> <a href="https://example.com">good</a></p>',
        ]);

        expect($tip['content'])->not->toContain('javascript:');
        expect($tip['content'])->toContain('https://example.com');
    });

    it('collapses the non-breaking spaces the editor emits for ordinary spaces', function () {
        $actor = manageActor();

        $tip = createTip($this, $actor, [
            'content' => '<p>Choices&nbsp;are&nbsp;for&nbsp;the&nbsp;value</p>',
        ]);

        // Quill serialises every space as nbsp; left alone the paragraph never wraps and drags a
        // horizontal scrollbar across the popover it lands in.
        expect($tip['content'])->toBe('<p>Choices are for the value</p>');
        expect($tip['content'])->not->toContain('nbsp');
    });

    it('collapses a literal non-breaking space character too', function () {
        $actor = manageActor();

        $tip = createTip($this, $actor, ['content' => "<p>Choices\u{00A0}are</p>"]);

        expect($tip['content'])->toBe('<p>Choices are</p>');
    });

    it('drops images', function () {
        $actor = manageActor();

        $tip = createTip($this, $actor, ['content' => '<p>text<img src="https://evil.test/p.gif"></p>']);

        expect($tip['content'])->not->toContain('<img');
    });
});

describe('Tip permissions', function () {
    it('serves the rendering payload to any authenticated user', function () {
        $actor = manageActor();
        createTip($this, $actor);

        $plainUser = userWithPermissions([]);

        $this->actingAs($plainUser)
            ->getJson('/api/tips/active')
            ->assertOk()
            ->assertJsonPath('data.0.anchor_key', 'users.list');
    });

    it('does not leak authorship through the rendering payload', function () {
        $actor = manageActor();
        createTip($this, $actor);

        $payload = $this->actingAs(userWithPermissions([]))->getJson('/api/tips/active')->json('data.0');

        expect(array_keys($payload))->toBe(['id', 'anchor_key', 'content']);
    });

    it('refuses the management list without tips.read', function () {
        $this->actingAs(userWithPermissions([]))
            ->getJson('/api/tips')
            ->assertForbidden();
    });

    it('refuses mutations to a reader without tips.manage', function () {
        $actor = manageActor();
        $tip = createTip($this, $actor);
        $reader = userWithPermissions(['tips.read']);

        $this->actingAs($reader)
            ->patchJson("/api/tips/{$tip['id']}", ['content' => '<p>x</p>', 'change_reason' => 'y'])
            ->assertForbidden();

        $this->actingAs($reader)
            ->deleteJson("/api/tips/{$tip['id']}", ['change_reason' => 'y'])
            ->assertForbidden();
    });

    it('refuses everything to a guest', function () {
        $this->getJson('/api/tips/active')->assertUnauthorized();
    });
});
