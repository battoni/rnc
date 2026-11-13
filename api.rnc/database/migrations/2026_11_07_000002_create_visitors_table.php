<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * An anonymous visitor of the public site (rnc-1 R7): the UUID v4 the browser keeps in
 * localStorage, and the source it first arrived from. No IP, no user agent, no raw referrer (AM2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('source', 20);
            $table->string('medium', 60)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['firm_id', 'uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
