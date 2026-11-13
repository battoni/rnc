<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The funnel's raw material (rnc-1 R7, R24): one row per page view, CTA click and diagnostic step.
 * `type` is a string checked by the request, not an enum column (AM11). `lead_id` is a plain indexed
 * column without a foreign key: the `leads` table arrives in F3, and an event never blocks the
 * delete of what it logged (database/RULES.md). The API fills it from a lead token, never from the
 * client (AM2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('path');
            $table->string('sector', 60)->nullable();
            $table->string('source', 20);
            $table->string('medium', 60)->nullable();
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->timestamp('at');
            $table->timestamps();

            $table->index(['firm_id', 'type', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
