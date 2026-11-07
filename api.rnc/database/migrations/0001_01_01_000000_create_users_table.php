<?php

use App\Modules\Auth\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('last_name');
            $table->string('username')->unique();
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('profile_type')->nullable();
            // Unique but nullable: two accounts must not share a document, and an account an
            // administrator creates from the Users screen has no document at all.
            $table->string('cpf')->nullable()->unique();
            $table->date('birth_date')->nullable();
            // `roles` is created first (its migration sorts before this one) so the key lives here,
            // in the table's only description, rather than in a later ALTER.
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', UserStatus::values())->default(UserStatus::ACTIVE->value);
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
