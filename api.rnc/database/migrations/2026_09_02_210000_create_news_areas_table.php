<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Which part of the product an entry is about.
 *
 * A table and not a column on `news`, for the reason the module's own rules give: an entry touches
 * one area today and three tomorrow, and a row per area keeps that a service decision instead of a
 * schema change. It also leaves the door open to filtering the changelog by area without a JSON
 * search.
 *
 * The vocabulary is the one the Ideas board already uses (`IdeaArea`), deliberately: an idea about
 * Trilhas and the entry announcing it should not be filed under two different words.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained()->cascadeOnDelete();
            $table->string('area', 20);
            $table->timestamps();

            // An entry carries an area once. Saying it twice is not emphasis.
            $table->unique(['news_id', 'area']);
            $table->index('area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_areas');
    }
};
