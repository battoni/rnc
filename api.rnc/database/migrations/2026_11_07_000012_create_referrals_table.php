<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A referral (rnc-1 R22–R23): Franco recommends one lead to one partner, once (unique pair). What
 * the lead agreed to is copied onto the row at that moment (`consent_*`, arcus "What was agreed is
 * copied onto the row"), with the score and the items it covered, the message and who sent it.
 * `status` starts `enviado`; the partner's portal moves it (rnc-2 R7, folded here — D15): who moved
 * it last and when (`status_by`, `status_at`), the first contact (`contacted_at`, the SLA's stop),
 * the meeting, and the first-year contract value with the closing moment. The moments are UTC
 * (`UtcDateTime`, AM9); `status_by` is the partner's login (Auth's `users`) with no foreign key —
 * a cross-module id (rnc-1 W25), as in `partner_access_events`. The partner is restricted on
 * delete: the trail must keep it. `(firm_id, closed_at)` serves the ledger's month of closings (F4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('referred_by')->constrained('users')->restrictOnDelete();
            $table->text('message');
            $table->unsignedTinyInteger('score');
            $table->json('covers');
            $table->text('consent_text');
            $table->unsignedSmallInteger('consent_version');
            $table->timestamp('consent_given_at');
            $table->string('status', 20)->default('enviado');
            $table->timestamp('status_at')->nullable();
            $table->unsignedBigInteger('status_by')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('meeting_at')->nullable();
            $table->unsignedBigInteger('deal_value_cents')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'partner_id']);
            $table->index(['firm_id', 'created_at']);
            $table->index(['partner_id', 'status']);
            $table->index(['firm_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
