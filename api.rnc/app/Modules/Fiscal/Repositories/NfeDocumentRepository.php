<?php

namespace App\Modules\Fiscal\Repositories;

use App\Modules\Fiscal\Models\NfeDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The firm's NF-e documents and their raw files. The row and the file are written together: a file
 * that cannot be written rolls the row back, so no row points at nothing. The file lives on the
 * private `local` disk, never `public` (R16, D7, A15).
 */
class NfeDocumentRepository
{
    public const DISK = 'local';

    private const DIRECTORY = 'nfe';

    private const EXTENSION = '.xml';

    public function existsNfeDocument(string $accessKey, int $clientId): bool
    {
        return NfeDocument::query()->where('access_key', $accessKey)->where('client_id', $clientId)->exists();
    }

    /**
     * The path comes only from integers and the validated 44-digit key — never from the uploaded
     * file's name (plan AM3).
     */
    public static function nfeDocumentPath(int $firmId, int $clientId, string $accessKey): string
    {
        return self::DIRECTORY.'/'.$firmId.'/'.$clientId.'/'.$accessKey.self::EXTENSION;
    }

    /**
     * Throws `UniqueConstraintViolationException` when the client already holds the key — a race the
     * importer answers as `duplicate`.
     *
     * @param  array<string, mixed>  $attributes  every column but `path`
     */
    public function createNfeDocument(array $attributes, string $path, string $content): NfeDocument
    {
        return DB::transaction(function () use ($attributes, $path, $content) {
            $document = NfeDocument::query()->create([...$attributes, 'path' => $path]);

            if (! Storage::disk(self::DISK)->put($path, $content)) {
                throw new RuntimeException("The NF-e file {$path} could not be written.");
            }

            return $document;
        });
    }
}
