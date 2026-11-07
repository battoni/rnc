<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A lead's timeline (rnc-1 R18–R19): the diagnostic's steps from F3, the team's moves and notes
 * from F5. `user_id` is who did it — null for the visitor. A child of its lead, no firm column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->json('data')->nullable();
            $table->timestamp('at');
            $table->timestamps();

            $table->index(['lead_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_events');
    }
};
