<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A booked meeting (rnc-1 R16): a lead, a specialist, a 30-minute slot. `starts_at`/`ends_at` are
 * UTC (AM9, the `UtcDateTime` cast). The unique (specialist, start) is what makes two visitors
 * unable to take the same slot; the unique lead is one meeting per lead. `reminded_at` makes the
 * 24-hour reminder go once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('specialist_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['specialist_id', 'starts_at']);
            $table->index(['firm_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
