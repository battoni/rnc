<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Applause. Three kinds, and you may spend as many as you like.
     *
     * A reaction here is deliberately NOT a toggle like an idea's vote. A vote is a judgement and
     * has to be one-per-person or the ranking is a popularity of enthusiasm rather than of ideas.
     * This is the opposite: it is applause, and clapping twice is the whole point.
     *
     * That is why it is a counter and not a row per click. Somebody delighted enough to hammer the
     * button thirty times should cost thirty increments on one row, not thirty rows — and the two
     * questions anyone actually asks ("how loud was the room" and "how loud was I") are both
     * answered by a count. The trade is that the individual clicks are not timestamped, which
     * nothing needs.
     */
    public function up(): void
    {
        Schema::create('news_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            // One counter per person per kind. The increment upserts against this.
            $table->unique(['news_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_reactions');
    }
};
