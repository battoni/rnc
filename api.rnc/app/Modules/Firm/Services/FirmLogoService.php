<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Repositories\FirmRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The only writer of a firm's logo file (rnc-4 R27, D22). The platform console's request already
 * judged the bytes (PNG, JPEG or WebP by content, ≤ 512 KB, ≤ 1024 px); this stores them on the
 * `public` disk as `firms/<id>/logo-<40 random>.<ext>` — the name and the extension are the server's,
 * the extension from the sniffed type, never from the client's file name — points `logo_path` at it,
 * and deletes the file it replaced. Not re-encoded (D22).
 */
class FirmLogoService
{
    public const DISK = 'public';

    /** The sniffed MIME types a logo may have, and the extension each is stored under. */
    public const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private const RANDOM_NAME_LENGTH = 40;

    public function __construct(private FirmRepository $firmRepository) {}

    /** The MIME type of the file's bytes (`finfo`), whatever its name or the client's claim. */
    public static function sniff(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        $mime = $path !== false ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path) : false;

        return is_string($mime) ? $mime : null;
    }

    public function replace(Firm $firm, UploadedFile $file): Firm
    {
        $extension = self::EXTENSIONS[self::sniff($file) ?? ''] ?? throw new RuntimeException('A logo reached the store unsniffed.');
        $disk = Storage::disk(self::DISK);
        $previous = $firm->logo_path;
        $name = 'logo-'.Str::random(self::RANDOM_NAME_LENGTH).'.'.$extension;
        $path = $disk->putFileAs($this->folderOf($firm), $file, $name);

        if ($path === false) {
            throw new RuntimeException('The logo could not be stored.');
        }

        try {
            $firm = $this->firmRepository->updateFirm($firm, ['logo_path' => $path]);
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        $this->deleteOwnFile($firm, $previous);

        return $firm;
    }

    public function remove(Firm $firm): Firm
    {
        $previous = $firm->logo_path;
        $firm = $this->firmRepository->updateFirm($firm, ['logo_path' => null]);
        $this->deleteOwnFile($firm, $previous);

        return $firm;
    }

    private function folderOf(Firm $firm): string
    {
        return 'firms/'.$firm->id;
    }

    /** Only a file under this firm's own folder is ever deleted. */
    private function deleteOwnFile(Firm $firm, ?string $path): void
    {
        if ($path !== null && str_starts_with($path, $this->folderOf($firm).'/')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
