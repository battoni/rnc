<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One entry in Novidades — the product's changelog, read by everyone.
     *
     * It is its own table rather than a view over delivered ideas, and the reason is honesty
     * about where the work comes from. Most of what ships was never proposed on the board; a
     * changelog that could only show delivered ideas would report a fraction of the pace and
     * would sit empty in the weeks nobody happened to write in. idea_id is therefore NULLABLE
     * and optional: when an entry does answer someone's idea, it credits them by name, and
     * marking that idea delivered is what offers to write this row in the first place.
     *
     * published_at nullable is the draft state. An entry is written when the work is done and
     * published when it is worth announcing, and those are not the same moment — the reader
     * should never watch a changelog assemble itself.
     *
     * user_id records who wrote the entry, nullOnDelete for the same reason as everywhere else
     * in this module: the account can go, the published record cannot.
     */
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title', 140);

            // The sentence under the title on the card. Optional: a short entry earns none.
            $table->string('subtitle', 200)->nullable();

            // mediumText, not text: the request accepts 60,000 characters and TEXT holds 65,535
            // BYTES — accented prose passes that long before the character limit.
            $table->mediumText('body');

            // Where the entry sends the reader — a release, a doc, a video. Optional. 2048 is the
            // length the request accepts; the default 255 turned a valid long URL into a 500.
            $table->string('link', 2048)->nullable();

            $table->foreignId('idea_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // A date is when it went out; the flag is whether it is out. An entry can carry a
            // date and still be a draft, and pulling one back is not the same as erasing when
            // it first shipped.
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);

            $table->timestamps();

            $table->index('published_at');
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
