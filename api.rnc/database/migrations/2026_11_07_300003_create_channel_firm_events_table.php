<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One stage move of an RNC channel firm (rnc-3 R7): who, from, to, at (UTC). Written only by
 * `ChannelService::advance`, never edited. `user_id` carries no foreign key — `users` is another
 * module's table (rnc-1 W25).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_firm_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_firm_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('from', 20);
            $table->string('to', 20);
            $table->dateTime('at');

            $table->index('channel_firm_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_firm_events');
    }
};
