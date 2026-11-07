<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The Reforma knowledge base (rnc-3 R10, R11, D7, D8): Franco's own notes, the only thing the
 * assistant ever answers with. `status` is a `NoteStatus` value (rascunho · revisada · aposentada;
 * a string, no `enum`: plan P2); `reviewed_at` is UTC and a review is served for 90 days
 * (`AssistantNote::VALID_DAYS`). `as_of` is the date the source was read (`facts.md`). The
 * reviewer and the last editor are user ids without a foreign key (rnc-1 W25). Keywords are JSON
 * lists, matched by `NoteMatcher`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('question', 200);
            $table->string('answer', 2000);
            $table->string('rule', 200);
            $table->string('source_label', 160);
            $table->string('source_url', 300);
            $table->date('as_of');
            $table->json('keywords_strong');
            $table->json('keywords_weak');
            $table->string('status', 20);
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->unsignedBigInteger('last_edited_by')->nullable();
            $table->timestamps();

            $table->unique(['firm_id', 'slug']);
            $table->index(['firm_id', 'status', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_notes');
    }
};
