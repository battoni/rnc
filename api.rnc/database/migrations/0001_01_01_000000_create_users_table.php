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
            // The firm the person works for (D1). Nullable only for the bootstrap's own tests and
            // self-registration; every seeded account of the product belongs to a firm, and a firm
            // with people cannot be deleted from under them.
            $table->foreignId('firm_id')->nullable()->constrained()->restrictOnDelete();
            // A team member a visitor can book a meeting with (R2, R15).
            $table->boolean('is_specialist')->default(false);
            // The partner whose portal login this is (rnc-2 R1, D1); null for every staff account.
            // No foreign key: `partners` belongs to the Partner module and is created later, and a
            // cross-module id carries none (rnc-1 W25). Unique: a partner has ONE login row — a
            // revoke deactivates it and a regrant reopens it — so the index is what answers two
            // grants racing (`PartnerAccessService`, 409). Many NULLs are allowed on every driver.
            $table->unsignedBigInteger('partner_id')->nullable()->unique();
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
