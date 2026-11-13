<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The clients' contracts (rnc-3 R17, D10), owned by Portfolio: what the contract alert ranks. `kind`
 * is a `ContractKind` value (a string, no `enum`: plan P2); the value is per year in integer cents
 * (P5). One row per client, kind, start and end — a re-import updates it (R18). `clause_marked_at`
 * (UTC) and `clause_marked_by` record "Cláusula incluída" (R22); once set, an import never takes the
 * clause away (AM16). `client_id` is Portfolio's own table; `clause_marked_by` is Auth's `users`, with
 * no foreign key (rnc-1 W25). Firm-scoped, never public.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->unsignedBigInteger('annual_value_cents');
            $table->unsignedSmallInteger('start_year');
            $table->unsignedSmallInteger('end_year');
            $table->boolean('has_reforma_clause')->default(false);
            $table->timestamp('clause_marked_at')->nullable();
            $table->unsignedBigInteger('clause_marked_by')->nullable();
            $table->timestamps();

            $table->unique(['firm_id', 'client_id', 'kind', 'start_year', 'end_year']);
            $table->index(['firm_id', 'has_reforma_clause', 'end_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contracts');
    }
};
