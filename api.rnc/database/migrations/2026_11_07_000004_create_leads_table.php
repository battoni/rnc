<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A prospect from the public diagnostic (rnc-1 R11, plan AM3/AM13). Created at step 1 as a
 * `visitante` with no personal data; the contact turns it into a `lead_parcial`. `token` is the
 * only key the public routes know (48 random characters, 30 days); `report_token` opens the report. `stage`, `last_step`, `path`,
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
            // The "Relatório de Impacto" link (F4, plan AM4): made at booking, 30 days, a key of its own
            // so the e-mailed link never doubles as the diagnostic's token.
            $table->string('report_token', 48)->nullable()->unique();
            $table->timestamp('report_expires_at')->nullable();
            $table->uuid('visitor_uuid')->nullable()->index();
            $table->string('stage', 20);
            $table->string('last_step', 20)->nullable();
            // Why the team marked it perdido (F5, R18): required by that move, cleared when it leaves.
            $table->string('lost_reason', 300)->nullable();
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
            // The sub-brand the diagnostic was started under (rnc-3 R4): null is the main brand. Fixed at
            // creation like `channel`. The `firm_brands` row is the Firm module's, so no foreign key.
            $table->unsignedBigInteger('brand_id')->nullable()->index();
            // The portfolio client a `carteira` lead stands for (rnc-2 F10, R27, D13): one lead per
            // client and firm, Portfolio's id with no foreign key across modules (rnc-1 W25).
            $table->unsignedBigInteger('client_id')->nullable();
            $table->timestamps();

            $table->index(['firm_id', 'stage']);
            $table->unique(['firm_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
