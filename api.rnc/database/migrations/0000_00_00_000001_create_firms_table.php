<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The tenants (R1, D1). Sorted before `users` so `users.firm_id` can carry its key in the users
 * table's own create. Business hours are a list of `{weekdays, from, to}` (ISO weekdays, 1 = Monday)
 * — a json column, the same on MySQL and SQLite (AM11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 60)->unique();
            $table->string('logo_text');
            $table->string('brand_primary', 7);
            $table->string('brand_accent', 7);
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mailbox')->nullable();
            $table->json('business_hours');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firms');
    }
};
