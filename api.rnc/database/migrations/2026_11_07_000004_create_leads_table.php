<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A prospect from the public diagnostic (rnc-1 R11, plan AM3/AM13). Created at step 1 as a
 * `visitante` with no personal data; the contact turns it into a `lead_parcial`. `token` is the
 * only key the public routes know (48 random characters, 30 days). `stage`, `last_step`, `path`,
 * `channel` and `source` are strings checked by the code, never enum columns (AM11). The visitor is
 * kept by its UUID — the `visitors` row belongs to Tracking, and the two meet on (firm, uuid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('token', 48)->unique();
            $table->timestamp('token_expires_at');
            $table->uuid('visitor_uuid')->nullable()->index();
            $table->string('stage', 20);
            $table->string('last_step', 20)->nullable();
            $table->string('path', 10);
            $table->string('company')->nullable();
            $table->string('cnpj', 14)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->json('profile');
            $table->string('channel', 20);
            $table->string('source', 20);
            $table->string('medium', 60)->nullable();
            $table->timestamps();

            $table->index(['firm_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
