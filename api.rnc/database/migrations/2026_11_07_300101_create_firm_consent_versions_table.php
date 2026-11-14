<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A firm's partner-introduction consent, versioned (rnc-4 R13, D12): an edit is version n+1 and an
 * older row is never touched. `text` may hold `{escritorio}`, filled with the firm's — or the
 * sub-brand's — name when a visitor reads and answers it. `author_user_id` is who wrote it (null for
 * a seed or the platform default's copy); no foreign key, so a removed account leaves the history.
 * `lead_consents` keeps storing the version and the filled words, with no key to this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firm_consent_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->text('text');
            $table->unsignedBigInteger('author_user_id')->nullable();
            $table->timestamps();

            $table->unique(['firm_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firm_consent_versions');
    }
};
