<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The platform's own settings (rnc-4 D12): one row per key, no firm — the platform's, not a
 * tenant's. Today one key, `consent_default`: the consent sentence a new firm's version 1 copies.
 * `PlatformSettings` (Firm module) is the only writer. The `300100` sequence keeps rnc-4's tables
 * apart from rnc-3's `3000xx`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
