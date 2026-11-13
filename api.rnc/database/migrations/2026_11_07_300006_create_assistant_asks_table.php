<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Every question put to the assistant (rnc-3 R15): who, the words, the words as the matcher reads
 * them (`normalised` — "Perguntas sem nota" groups by it), the note that answered or null, and when
 * (UTC). Written once, never edited. The user and the note carry no foreign key (rnc-1 W25): a
 * retired note keeps its asks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_asks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('question', 300);
            $table->string('normalised', 300);
            $table->unsignedBigInteger('note_id')->nullable();
            $table->dateTime('at');

            $table->index(['firm_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_asks');
    }
};
