<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A firm's sub-brands (rnc-3 R1, D1): Franco Express and Franco Prime are looks of the one Franco
 * firm, not firms — same data, network, address and mailbox. The main brand stays on `firms`.
 * `proof` is exactly four `{value, label}` points (a json column, the same on MySQL and SQLite).
 * The `300001` sequence keeps Phase 3's tables apart from Phase 2's, built on another branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firm_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 40);
            $table->string('name', 120);
            $table->string('short_name', 40);
            $table->string('tagline', 160)->nullable();
            $table->string('brand_primary', 7);
            $table->string('brand_accent', 7);
            $table->json('proof');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['firm_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firm_brands');
    }
};
