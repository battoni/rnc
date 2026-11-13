<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The firm's client portfolio (rnc-1 R25): the companies Franco already serves, imported from a CSV
 * or seeded locally. The profile columns are what the rules engine reads (sector, regime, band, the
 * B2B share); `monthly_fee` is today's honorário. `repricing` is the batch mark of R26 —
 * `{at, by, pct, suggested}`, copied at the moment of the mark (arcus "What was agreed is copied
 * onto the row"); null until marked. One row per CNPJ per firm: a re-import updates it. Plain
 * strings, no enum column (AM11).
 *
 * rnc-2 F9 (R28, folded — D15): the client's consent to be introduced to partners —
 * `partner_consent` true (sim) · false (não) · null (never answered), with when (UTC) and how it
 * was given (`contrato`: the clause of the services contract, the CSV's `autoriza_parceiros`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('cnpj', 14);
            $table->string('sector', 20);
            $table->string('regime', 20);
            $table->string('band', 4);
            $table->unsignedTinyInteger('b2b_share');
            $table->decimal('monthly_fee', 10, 2);
            $table->json('repricing')->nullable();
            $table->boolean('partner_consent')->nullable();
            $table->timestamp('partner_consent_at')->nullable();
            $table->string('partner_consent_via', 20)->nullable();
            $table->timestamps();

            $table->unique(['firm_id', 'cnpj']);
            $table->index(['firm_id', 'sector', 'regime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
