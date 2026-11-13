<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The public site's sector pages (rnc-1 R6), one row per firm and sector. `facts` is a list of
 * `{date, text, source: {label, url}}` and `seo` is `{title, description}` — json columns, the same
 * on MySQL and SQLite (AM11). `position` is the order the site lists them in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 60);
            $table->string('label', 60);
            $table->string('title');
            $table->text('lede');
            $table->json('facts');
            $table->json('seo');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['firm_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_pages');
    }
};
