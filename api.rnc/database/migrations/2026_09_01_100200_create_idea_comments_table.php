<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A comment on an idea. Flat, never threaded: a feedback board is a queue the maintainer
     * answers, not a forum, and a reply tree is how the answer stops being findable.
     *
     * is_official marks the maintainer's own reply so the interface can lift it out of the
     * chronology. It is a stored flag rather than a check on the author's role at render time
     * because the role can change — someone who answers as maintainer today and loses the
     * permission tomorrow still answered as maintainer.
     *
     * Written in phase 2; the table ships with phase 1 for the reason given on `ideas`.
     */
    public function up(): void
    {
        Schema::create('idea_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_official')->default(false);
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->index('idea_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_comments');
    }
};
