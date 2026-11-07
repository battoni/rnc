<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The questions the assistant handed to a specialist (rnc-3 R13, R14): who asked, which specialist
 * holds it, the words, `status` (`QuestionStatus`: aberta · respondida — a string, plan P2), and the
 * specialist's answer once given (F5). People are user ids without a foreign key (rnc-1 W25).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('asked_by');
            $table->unsignedBigInteger('specialist_id');
            $table->string('question', 300);
            $table->string('status', 20);
            $table->string('answer', 2000)->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->timestamps();

            $table->index(['firm_id', 'specialist_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_questions');
    }
};
