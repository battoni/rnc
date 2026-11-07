<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who has read which entry, and when.
     *
     * Two things depend on this table, and they pull in opposite directions. The reader gets a dot
     * on the nav until nothing is left unread, which wants the table small and the query cheap. The
     * writer gets to see who actually read what, which wants a row per person and a timestamp.
     * One row per (entry, person) serves both: the unread check is a NOT EXISTS, and the audience
     * is a join.
     *
     * A read is recorded once and never updated. "When did they first read it" is the useful fact;
     * re-opening an entry a month later says nothing new, and overwriting the timestamp would
     * quietly destroy the only answer to "did anyone read this when I published it".
     */
    public function up(): void
    {
        Schema::create('news_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            // One read per person per entry. The unique index is the rule, not the service.
            $table->unique(['news_id', 'user_id']);

            // The reader's unread check runs on every page load; this is the index it uses.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_reads');
    }
};
