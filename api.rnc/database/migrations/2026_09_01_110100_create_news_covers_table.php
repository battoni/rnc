<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The image at the top of a Novidades entry, and on its card in the list.
     *
     * A TABLE and not four columns on `news`, for the same reason `idea_attachments` is one: "one
     * cover per entry" is a product decision enforced in the service, and product decisions move.
     * Turning a cover into a gallery should be an edit to a guard clause, not a migration on a
     * table that now holds production rows.
     *
     * `disk` is stored per row rather than read from config at render time. The disk is
     * configurable precisely so it can change, and on the day it moves to object storage every row
     * written before that day must still resolve to where its file actually is.
     */
    public function up(): void
    {
        Schema::create('news_covers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('mime', 64);
            $table->unsignedInteger('size');
            $table->timestamps();

            $table->index('news_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_covers');
    }
};
