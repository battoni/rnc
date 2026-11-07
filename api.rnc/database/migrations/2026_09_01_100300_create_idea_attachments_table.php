<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An image attached to an idea — in practice, the screenshot on a bug report, which is the
     * difference between "the week grid looks wrong" and a fix.
     *
     * A TABLE, not three columns on `ideas`, even though the service allows exactly one row per
     * idea today. The limit is a product decision and product decisions move; raising it to
     * three images has to be a change to one guard clause, not a migration this project's rules
     * forbid writing (see `ideas`). One row per idea is enforced in IdeaAttachmentService.
     *
     * `disk` is stored per row rather than read from config at render time. The disk is
     * configurable (IDEA_ATTACHMENT_DISK) precisely so it can change — and on the day it
     * changes from local storage to object storage, every row written before that day must
     * still resolve to the place its file actually is.
     *
     * What is NOT here, and why: no original filename. The stored name is generated, so a
     * traversal sequence or a collision never reaches the filesystem, and the user's own name
     * for the file carries nothing the interface needs.
     */
    public function up(): void
    {
        Schema::create('idea_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('mime', 64);
            $table->unsignedInteger('size');
            $table->timestamps();

            $table->index('idea_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_attachments');
    }
};
