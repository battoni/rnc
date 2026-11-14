<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The audit of "Acessar como suporte" (rnc-4 R26, D20): one row per time the platform operator enters
 * a firm — who (`user_id`), which firm, from which address (`ip_address`), when it started and ended,
 * and why it ended (`end_reason`: sair | logout | expirou). An open row (`ended_at` null) is what a
 * session's view-as key points at; closing the row ends the view-as wherever it was. Not firm-scoped:
 * the table is the platform's. Audited at start and end, not per action (D20).
 * The audit outlives its people (verify P2-C4): a deleted operator or firm leaves the row with a null
 * `user_id` / `firm_id` (`nullOnDelete`), never takes it along.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_view_as_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('firm_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 16)->nullable();

            $table->index(['user_id', 'ended_at']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_view_as_sessions');
    }
};
