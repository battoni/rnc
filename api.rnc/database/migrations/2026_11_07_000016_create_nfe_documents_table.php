<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One NF-e as one client sees it (rnc-2 R16, D7): its own sale (`saida`) or a purchase addressed to
 * it (`entrada`). A document between two portfolio clients is two rows, so the access key is unique
 * per firm + key + CLIENT (spec C1, plan AM4) — that index is what answers `duplicate`. Item checks
 * are counts plus `sample` (up to five item numbers per check); no item table (D7). `client_id` is
 * Portfolio's, with no foreign key (rnc-1 W25). `path` is the raw XML on the private `local` disk,
 * built from the firm id, the client id and the validated key only (plan AM3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfe_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->foreignId('import_id')->constrained('nfe_imports')->restrictOnDelete();
            $table->string('direction', 7);
            $table->string('access_key', 44);
            $table->unsignedInteger('number');
            $table->unsignedSmallInteger('series');
            $table->timestamp('issued_at');
            $table->string('emitter_cnpj', 14);
            $table->unsignedTinyInteger('emitter_crt')->nullable();
            $table->string('recipient_cnpj', 14)->nullable();
            $table->string('recipient_kind', 10);
            $table->unsignedBigInteger('total_cents');
            $table->string('system', 60)->nullable();
            $table->unsignedInteger('items');
            $table->unsignedInteger('items_missing_group');
            $table->unsignedInteger('items_missing_codes');
            $table->unsignedInteger('items_without_test_values');
            $table->json('sample');
            $table->string('path');
            $table->timestamps();

            $table->unique(['firm_id', 'access_key', 'client_id']);
            $table->index(['firm_id', 'client_id', 'direction', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_documents');
    }
};
