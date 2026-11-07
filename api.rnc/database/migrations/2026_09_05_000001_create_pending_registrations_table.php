<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Someone who asked for an account but has not proved the e-mail yet.
 *
 * They are NOT a user. Writing a `users` row first and cleaning it up later would mean the
 * unique e-mail is taken by an address nobody has proved they own — which is how you lock a
 * person out of their own address by typing it wrong once. The row here is promoted to a
 * user when the code checks out, and deleted in the same transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('password');
            $table->string('profile_type')->nullable();
            $table->string('cpf')->nullable();
            $table->date('birth_date')->nullable();
            // Only the hash. A code readable in the database is a code an operator can use.
            $table->string('code_hash');
            // Wrong guesses at the current code; the fifth burns it (EmailVerificationService).
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
