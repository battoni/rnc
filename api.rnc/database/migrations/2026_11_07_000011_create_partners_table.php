<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The firm's network of specialists (rnc-1 R21): who a lead can be recommended to. `specialty` is
 * one of `PartnerSpecialty` (a string validated by the request, no enum column — AM11). A partner is
 * deactivated, never deleted: the trail keeps pointing at it. No login in Phase 1 (D7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('specialty', 40);
            $table->string('email', 160);
            $table->string('phone', 20)->nullable();
            $table->string('city', 80)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['firm_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
