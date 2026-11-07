<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tips', function (Blueprint $table) {
            $table->id();
            /*
             * The place in the UI this tip renders at. The catalogue of anchors lives in the
             * frontend (src/constants/tipAnchors.constant.ts), because that is where the
             * <ATip tip-key="..."> call sites physically are; here it is an opaque string.
             *
             * NOT unique at the database level: archiving frees the anchor for a new tip, and
             * MySQL has no partial unique index to express "unique among the non-archived".
             * TipService enforces it instead.
             */
            $table->string('anchor_key', 191);
            // mediumText: a tip accepts 20,000 characters, which can exceed TEXT's 65,535 bytes.
            $table->mediumText('content');
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['anchor_key', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tips');
    }
};
