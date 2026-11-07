<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Every grant and revoke of a partner's portal login (rnc-2 R2, plan AM9), one row per change,
 * written by `PartnerAccessService`. `partners.access_*_at` hold only the current state, and a
 * revoke followed by a regrant would lose the first period; the network ledger (F4) bills the
 * subscription months from these rows. `type` is `granted` or `revoked` (a string the service
 * writes, no enum column — AM11); `at` is UTC. `user_id` is the login, a cross-module id with no
 * foreign key (rnc-1 W25).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type', 10);
            $table->timestamp('at');

            $table->index(['firm_id', 'partner_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_access_events');
    }
};
