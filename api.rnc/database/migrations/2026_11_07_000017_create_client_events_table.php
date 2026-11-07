<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A portfolio client's timeline (rnc-2 R20, R22, D12): the team's "Registrar aviso" (`nfe.aviso`) and
 * "Propor revisão" (`fornecedores.revisao`), each with who and when. Owned by Portfolio, written only
 * by `ClientService::recordEvent`. `type` is a plain string the service writes (no enum column —
 * AM11); `at` is UTC. `client_id` is Portfolio's own table; `user_id` is Auth's, with no foreign key
 * (rnc-1 W25), like `partner_access_events`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('type', 40);
            $table->json('data')->nullable();
            $table->timestamp('at');

            $table->index(['firm_id', 'client_id', 'at']);
            $table->index(['firm_id', 'type', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_events');
    }
};
