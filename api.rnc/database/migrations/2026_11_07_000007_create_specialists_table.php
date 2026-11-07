<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The firm's bookable people (rnc-1 R15): one row per team member a visitor can book, with the
 * area shown on the slot picker and the `focus` that makes one "Indicado para o seu perfil"
 * (`SpecialistFocus`, a string checked by the code — AM11). The person is the `users` row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('area')->nullable();
            $table->string('focus', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialists');
    }
};
