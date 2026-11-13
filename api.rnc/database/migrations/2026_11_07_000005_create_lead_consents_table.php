<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Every answer to the partner-introduction checkbox (rnc-1 R11, plan AM12): the words the person
 * read (`text`, `version`), the answer (`given`, true or false — the box starts unticked), when, and
 * where (`via`). No IP and no user agent. A child of its lead, so no firm column of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->unsignedSmallInteger('version');
            $table->boolean('given');
            $table->string('via', 20);
            $table->timestamp('given_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_consents');
    }
};
