<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The tenants (R1, D1). Sorted before `users` so `users.firm_id` can carry its key in the users
 * table's own create. Business hours are a list of `{weekdays, from, to}` (ISO weekdays, 1 = Monday)
 * — a json column, the same on MySQL and SQLite (AM11).
 *
 * rnc-2 F4 (R11, D10) folds the network's money model in (D15): one model for every partner of the
 * firm — `comissao` or `assinatura`, a string the Firm module checks (no enum column, AM11) — with
 * the commission rate in basis points (1000 = 10 %) and the monthly fee in cents (R$ 490).
 * `FirmSettingsService` is the only writer.
 *
 * rnc-4 F1a (D3): `is_platform` marks the one firm the root host and `www` serve — the platform's
 * own product site, where the operator (no firm) works. Never reachable by `X-Firm` (plan AM9).
 *
 * rnc-4 F2a (R8, D7, D15): the firm's own look beside `logo_text` — `short_name` (40), `tagline`
 * (160), `proof` (four `{value, label}`, json), `logo_path` (a file on the `public` disk, F4c) — and
 * `modules`, the list of opt-in module keys (core is code, never stored). `modules` has no database
 * default: MySQL refuses a literal default on a json column, so the model's `$attributes` gives `[]`.
 *
 * rnc-4 F5a (R30, W31): `credentials`, the public footer's credentials line (certifications, a
 * network), the firm's own and edited by its manager in the settings; null shows no line.
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
            $table->string('short_name', 40);
            $table->string('tagline', 160)->nullable();
            $table->json('proof')->nullable();
            $table->string('credentials')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('brand_primary', 7);
            $table->string('brand_accent', 7);
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mailbox')->nullable();
            $table->json('business_hours');
            $table->string('partner_money_model', 20)->default('comissao');
            $table->unsignedSmallInteger('partner_commission_rate_bp')->default(1000);
            $table->unsignedInteger('partner_monthly_fee_cents')->default(49000);
            $table->boolean('is_platform')->default(false);
            $table->json('modules');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firms');
    }
};
