<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The RNC channel (rnc-3 R6, D5, D6): the Rede Nacional de Contabilidade firms Franco brings to the
 * platform, as records of Franco's pipeline — no login, no tenant, no people's data. `stage` is a
 * `ChannelStage` value (a string, no `enum`: plan P2); `stage_at` is UTC. The fee and the share are
 * stored per firm (integer cents, basis points), defaulted from `config/channel.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_firms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('city', 80);
            $table->string('stage', 20);
            $table->dateTime('stage_at');
            $table->unsignedInteger('monthly_fee_cents');
            $table->unsignedSmallInteger('share_bp');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['firm_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_firms');
    }
};
