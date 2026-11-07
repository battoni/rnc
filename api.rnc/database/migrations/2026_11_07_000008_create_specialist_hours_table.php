<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A specialist's weekly hours (rnc-1 R15): ISO weekday (1 = segunda … 7 = domingo) and the local
 * opening and closing time, `HH:MM` in America/Sao_Paulo (AM9). A child of its specialist — no
 * firm column of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialist_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialist_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->string('opens_at', 5);
            $table->string('closes_at', 5);
            $table->timestamps();

            $table->index(['specialist_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialist_hours');
    }
};
