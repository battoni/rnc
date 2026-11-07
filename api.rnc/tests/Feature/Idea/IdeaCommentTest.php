<?php

use App\Modules\Auth\Models\UserPermission;
use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\IdeaComment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('POST /api/ideas/{id}/comments', function () {
    it('adds a comment and returns it with its author', function () {
        $user = ideaContributor();
        $idea = Idea::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/ideas/{$idea->id}/comments", [
            'body' => 'Acontece comigo também, mas só no meio da semana.',
        ]);

        $response->assertStatus(201);
        expect($response->json('data.body'))->toContain('meio da semana');
        expect($response->json('data.author.id'))->toBe($user->id);
        expect($response->json('data.isOfficial'))->toBeFalse();
    });

    it('stamps the maintainer reply as official', function () {
        $idea = Idea::factory()->create();

        $response = $this->actingAs(ideaMaintainer())->postJson("/api/ideas/{$idea->id}/comments", [
            'body' => 'Reproduzi aqui, corrigido no mesmo lugar.',
        ]);

        expect($response->json('data.isOfficial'))->toBeTrue();
    });

    it('keeps the official stamp after the author loses the permission', function () {
        $idea = Idea::factory()->create();
        $maintainer = ideaMaintainer();

        $this->actingAs($maintainer)->postJson("/api/ideas/{$idea->id}/comments", ['body' => 'Resposta oficial.']);

        UserPermission::where('user_id', $maintainer->id)->where('permission_key', 'ideas.manage')->delete();

        expect(IdeaComment::first()->is_official)->toBeTrue();
    });

    it('keeps the visible comment count on the idea in step', function () {
        $idea = Idea::factory()->create();

        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/comments", ['body' => 'Primeiro.']);
        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/comments", ['body' => 'Segundo.']);

        expect($idea->fresh()->comments_count)->toBe(2);
    });

    it('refuses a comment on a hidden idea', function () {
        $idea = Idea::factory()->hidden()->create();

        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/comments", ['body' => 'Oi?'])
            ->assertNotFound();
    });

    it('refuses an empty comment', function () {
        $idea = Idea::factory()->create();

        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/comments", ['body' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
    });
});

describe('GET /api/ideas/{id}/comments', function () {
    it('returns them oldest first, so a thread reads as a conversation', function () {
        $idea = Idea::factory()->create();

        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/comments", ['body' => 'Primeiro.']);
        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/comments", ['body' => 'Segundo.']);

        $comments = $this->actingAs(ideaContributor())->getJson("/api/ideas/{$idea->id}/comments")->json('data');

        expect($comments[0]['body'])->toBe('Primeiro.');
        expect($comments[1]['body'])->toBe('Segundo.');
    });

    it('withholds a hidden comment from a contributor and shows it to the maintainer', function () {
        $idea = Idea::factory()->create();
        IdeaComment::factory()->for($idea)->hidden()->create(['body' => 'Escondido.']);

        expect($this->actingAs(ideaContributor())->getJson("/api/ideas/{$idea->id}/comments")->json('data'))
            ->toHaveCount(0);
        expect($this->actingAs(ideaMaintainer())->getJson("/api/ideas/{$idea->id}/comments")->json('data'))
            ->toHaveCount(1);
    });
});

describe('DELETE /api/ideas/comments/{id}', function () {
    it('lets a person delete their own comment outright', function () {
        $user = ideaContributor();
        $idea = Idea::factory()->create();
        $comment = IdeaComment::factory()->for($idea)->for($user, 'user')->create();

        $this->actingAs($user)->deleteJson("/api/ideas/comments/{$comment->id}")->assertOk();

        expect(IdeaComment::find($comment->id))->toBeNull();
    });

    it('hides rather than destroys when the maintainer acts on somebody else words', function () {
        $idea = Idea::factory()->create();
        $comment = IdeaComment::factory()->for($idea)->create();

        $this->actingAs(ideaMaintainer())->deleteJson("/api/ideas/comments/{$comment->id}")->assertOk();

        expect(IdeaComment::find($comment->id))->not->toBeNull();
        expect(IdeaComment::find($comment->id)->isHidden())->toBeTrue();
    });

    it('deletes outright when the maintainer removes their own comment', function () {
        $maintainer = ideaMaintainer();
        $idea = Idea::factory()->create();
        $comment = IdeaComment::factory()->for($idea)->for($maintainer, 'user')->create();

        $this->actingAs($maintainer)->deleteJson("/api/ideas/comments/{$comment->id}")->assertOk();

        expect(IdeaComment::find($comment->id))->toBeNull();
    });

    it('refuses to let one contributor delete a comment written by another', function () {
        $idea = Idea::factory()->create();
        $comment = IdeaComment::factory()->for($idea)->create();

        $this->actingAs(ideaContributor())->deleteJson("/api/ideas/comments/{$comment->id}")->assertNotFound();

        expect(IdeaComment::find($comment->id))->not->toBeNull();
    });

    /*
     * The bug this pins: the detail screen reads its thread from GET /ideas/{id}, which used to
     * filter hidden comments unconditionally. A moderator hid a comment and it vanished for them
     * too, leaving them unable to act on it again.
     */
    it('shows the maintainer the comment they hid, on the idea itself', function () {
        $idea = Idea::factory()->create();
        $comment = IdeaComment::factory()->for($idea)->create(['body' => 'Palavras ocultadas.']);
        $maintainer = ideaMaintainer();

        $this->actingAs($maintainer)->deleteJson("/api/ideas/comments/{$comment->id}");

        $shown = $this->actingAs($maintainer)->getJson("/api/ideas/{$idea->id}")->json('data.comments');
        expect($shown)->toHaveCount(1);
        expect($shown[0]['hidden'])->toBeTrue();

        $hiddenFromContributor = $this->actingAs(ideaContributor())->getJson("/api/ideas/{$idea->id}")->json('data.comments');
        expect($hiddenFromContributor)->toHaveCount(0);
    });

    /* Hiding is the first act; the second removes it for good, or spam lives forever. */
    it('removes a comment for good when the maintainer acts on it a second time', function () {
        $idea = Idea::factory()->create();
        $comment = IdeaComment::factory()->for($idea)->create();
        $maintainer = ideaMaintainer();

        $first = $this->actingAs($maintainer)->deleteJson("/api/ideas/comments/{$comment->id}");
        $first->assertOk();
        expect($first->json('data.hidden'))->toBeTrue();
        expect($first->json('data.deleted'))->toBeFalse();
        expect(IdeaComment::find($comment->id)?->isHidden())->toBeTrue();

        $second = $this->actingAs($maintainer)->deleteJson("/api/ideas/comments/{$comment->id}");
        $second->assertOk();
        expect(IdeaComment::find($comment->id))->toBeNull();
    });

    /*
     * The two gestures return the same shape, so the client needs a signal that is not `hidden`:
     * a comment removed for good was ALREADY hidden and comes back with `hidden: true`. Without
     * `deleted` the thread folds it back in instead of dropping it.
     */
    it('marks a removed comment as deleted, which hidden alone cannot say', function () {
        $idea = Idea::factory()->create();
        $comment = IdeaComment::factory()->for($idea)->hidden()->create();

        $response = $this->actingAs(ideaMaintainer())->deleteJson("/api/ideas/comments/{$comment->id}");

        expect($response->json('data.deleted'))->toBeTrue();
        expect($response->json('data.hidden'))->toBeTrue();
    });

    it('marks an author deleting their own comment as deleted', function () {
        $user = ideaContributor();
        $idea = Idea::factory()->create();
        $comment = IdeaComment::factory()->for($idea)->for($user, 'user')->create();

        $response = $this->actingAs($user)->deleteJson("/api/ideas/comments/{$comment->id}");

        expect($response->json('data.deleted'))->toBeTrue();
        expect($response->json('data.hidden'))->toBeFalse();
    });

    it('takes a hidden comment back out of the count', function () {
        $idea = Idea::factory()->create();

        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/comments", ['body' => 'Some.']);
        $comment = IdeaComment::first();

        $this->actingAs(ideaMaintainer())->deleteJson("/api/ideas/comments/{$comment->id}");

        expect($idea->fresh()->comments_count)->toBe(0);
    });
});
