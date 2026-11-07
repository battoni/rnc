<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tip_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tip_id')->constrained('tips')->cascadeOnDelete();
            /*
             * The full content OF THIS VERSION, not a diff. Restoring an old version has to
             * reproduce it exactly years later, and a diff chain only does that while every
             * link survives.
             *
             * Deliberately NOT the Reference Time-Ledger shape (starts_at/ends_at): that models
             * "which value was in force when this quotation was priced". A tip is never read
             * retroactively — this is an append-only audit log, and dating it would imply a
             * temporal query nobody makes.
             */
            // mediumText: a tip accepts 20,000 characters, which can exceed TEXT's 65,535 bytes.
            $table->mediumText('content');
            /*
             * What happened, not just what the text became. Archiving does not change a word of
             * the content, so a content-versions-only log would record a tip vanishing from the
             * app with nobody's name and no reason against it — which is the one thing the log
             * exists to make impossible.
             */
            $table->string('event')->default('updated');
            /*
             * Set when this version came from restoring an older one, pointing at the version
             * that was restored. Structured rather than a sentence in `change_reason`: the label
             * ("restored the version from 12/03") is rendered from this, so it stays in the
             * reader's language instead of being frozen in whatever locale the restorer had.
             */
            $table->foreignId('restored_from_id')->nullable()->constrained('tip_histories')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason')->nullable();
            $table->timestamps();
            $table->index(['tip_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tip_histories');
    }
};
