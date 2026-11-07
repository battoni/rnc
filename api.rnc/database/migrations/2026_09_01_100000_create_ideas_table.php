<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An idea — one piece of feedback about the product itself, written by a Shifter and read
     * by every other Shifter. This is the only table in the app whose rows are PUBLIC: a trail,
     * a week and a season belong to one person, an idea belongs to the board.
     *
     * user_id is nullOnDelete, NOT cascade, and the difference matters. An idea other people
     * supported is no longer only its author's: deleting the account would take the votes, the
     * comments and the reason a delivered feature exists with it. A null author renders as a
     * removed Shifter and the board keeps its history.
     *
     * votes_count is denormalised on purpose. The board's default order is "most supported",
     * and the alternative is a correlated subquery on every list render for a number that
     * changes once per click. idea_votes stays the truth; this is the read model, written in
     * the same transaction as the vote.
     *
     * comments_count and delivered_at are written by phases the first cut does not ship. They
     * are here anyway: `arcus-api-architecture` §3 forbids ALTER migrations outright, so a
     * column a planned phase needs costs an unused default here and a migration nobody is
     * allowed to write later. The project this came from argued the same thing from a launch
     * date; the bootstrap does not need the argument, only the rule.
     *
     * status carries `not_now` deliberately, and the interface never paints it as an error. A
     * board that can only say yes goes silent instead of saying no, and silence is what makes
     * people stop writing. admin_note is the other half of that: the public sentence explaining
     * a status, which is what turns a refusal into an answer.
     *
     * hidden_at moderates without deleting — the author still sees their row, the board does
     * not. merged_into_id points a duplicate at the canonical idea so its supporters land
     * somewhere real instead of watching their entry vanish.
     */
    public function up(): void
    {
        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->text('description');
            $table->string('type', 24);
            $table->string('area', 24)->nullable();
            $table->string('status', 24)->default('received');
            $table->text('admin_note')->nullable();
            $table->unsignedInteger('votes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->foreignId('merged_into_id')->nullable()->constrained('ideas')->nullOnDelete();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('hidden_at');
            $table->index('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ideas');
    }
};
