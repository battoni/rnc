<?php

namespace App\Modules\Idea\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Where an uploaded image is written, and the rules that keep writing one safe. ONE copy of those
 * rules, for every feature that stores an image.
 *
 * It started as `IdeaAttachmentService`, and stayed there until Novidades needed a cover. Copying
 * it would have created two places that must agree about path traversal and about which
 * extensions may reach a directory nginx serves — and two copies of a security rule are one copy
 * plus a future divergence. The feature services are thin wrappers around this now.
 */
class ImageAttachmentService
{
    /**
     * The extension written to disk, chosen from the sniffed MIME type — never from the name the
     * client sent and never from `$file->extension()` directly.
     *
     * Validation already restricts uploads to these four formats, so this map should be
     * unreachable. It is here because the consequence of being wrong is specific and severe: the
     * default disk is `public`, nginx serves it, and a file that ends in `.php` on a path nginx
     * serves is remote code execution rather than a broken image. A whitelist that never fires
     * costs nothing; the guess that fires once costs the server.
     */
    private const EXTENSION_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const FALLBACK_EXTENSION = 'jpg';

    /**
     * Replace whatever the owner has on `$relation` with this file. One image per owner is the
     * caller's rule to keep — it is a product decision, and this service holds only the ones that
     * are about safety.
     *
     * `$attachment` is the owner's relation (`$idea->attachment()`) and `$relation` its name; the
     * relation carries the row's type back to the caller, the name is what the eager-load cache
     * is keyed by.
     *
     * @template TAttachment of Model
     *
     * @param  HasOne<TAttachment, covariant Model>  $attachment
     * @return TAttachment
     */
    public function replace(HasOne $attachment, string $relation, string $directory, UploadedFile $file): Model
    {
        $this->delete($attachment->getParent(), $relation);

        $disk = config('ideas.attachment_disk');

        /*
         * The stored name is generated and the extension comes from the sniffed MIME, never from
         * what the client called the file. A user-supplied name is a path traversal and a
         * collision waiting to happen, and it carries nothing the interface ever shows.
         */
        $path = $file->storeAs(
            $directory,
            Str::uuid()->toString().'.'.$this->safeExtension($file),
            ['disk' => $disk]
        );

        /*
         * `storeAs` returns false on a full disk or a permissions error. Storing that gives a row
         * whose `path` is `false`, an API answering 201, and a cover URL that 404s for good — a
         * silent bad row instead of a loud failure.
         */
        if ($path === false) {
            throw new RuntimeException('Could not store the uploaded image.');
        }

        /*
         * The caller writes the owner and its image in one transaction. If that transaction
         * rolls back, the row pointing at this file never existed, so the file goes too. Outside
         * a transaction nothing is registered.
         */
        DB::afterRollBack(fn () => Storage::disk($disk)->delete($path));

        return $attachment->create([
            'disk' => $disk,
            'path' => $path,
            // The SNIFFED type, not the client's header — the same reason the extension is sniffed
            // three lines above. Trusting the header here and not there was a contradiction.
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * The file goes with the row. An orphaned blob on a 24 GB droplet is nobody's friend.
     *
     * The blob is removed only once the row's removal is COMMITTED: inside a transaction that
     * later rolls back, deleting it at once would leave a row pointing at nothing. Outside a
     * transaction `afterCommit` runs immediately.
     */
    public function delete(Model $owner, string $relation): void
    {
        $owner->loadMissing($relation);

        $existing = $owner->getRelation($relation);

        if (! $existing) {
            return;
        }

        $disk = $existing->disk;
        $path = $existing->path;

        $existing->delete();
        $owner->unsetRelation($relation);

        DB::afterCommit(fn () => Storage::disk($disk)->delete($path));
    }

    private function safeExtension(UploadedFile $file): string
    {
        return self::EXTENSION_BY_MIME[$file->getMimeType()] ?? self::FALLBACK_EXTENSION;
    }
}
