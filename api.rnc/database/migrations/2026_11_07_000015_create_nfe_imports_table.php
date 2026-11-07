<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One NF-e import (rnc-2 R17): who (`user_id`, null for the folder command — a cross-module id
 * with no foreign key, rnc-1 W25), how (`source`: `upload` or `folder`, a string the importer
 * writes — no enum column, AM11), how many files were read and documents stored, every refusal as
 * `[{file, code}]`, and when (`at`, UTC). Written by `NfeImporter` only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfe_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('source', 10);
            $table->unsignedInteger('files')->default(0);
            $table->unsignedInteger('stored')->default(0);
            $table->json('refused');
            $table->timestamp('at');

            $table->index(['firm_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_imports');
    }
};
