<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One person's support for one idea.
     *
     * The unique on (idea_id, user_id) is the whole point of the table. "One vote per person"
     * enforced only in a service is a promise that survives until two requests arrive at once;
     * enforced here it is arithmetic. The vote service catches the integrity violation and
     * treats it as "already supported", which is also the correct answer to a double click.
     *
     * Both foreign keys cascade, unlike the author on `ideas`. A vote is not content — it
     * carries no text and nothing of its own — so a deleted account's votes should leave with
     * it, and the counter is recomputed rather than left pointing at nobody.
     */
    public function up(): void
    {
        Schema::create('idea_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['idea_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_votes');
    }
};
