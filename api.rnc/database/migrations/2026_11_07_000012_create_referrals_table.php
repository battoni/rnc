<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A referral (rnc-1 R22–R23): Franco recommends one lead to one partner, once (unique pair). What
 * the lead agreed to is copied onto the row at that moment (`consent_*`, arcus "What was agreed is
 * copied onto the row"), with the score and the items it covered, the message and who sent it.
 * `status` starts `enviado`; Phase 2's partner portal moves it. The partner is restricted on
 * delete: the trail must keep it.
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
            $table->timestamps();

            $table->unique(['lead_id', 'partner_id']);
            $table->index(['firm_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
