<?php

namespace App\Modules\Fiscal\Sources;

use App\Modules\Fiscal\Contracts\NfeDocumentSource;
use App\Modules\Fiscal\DTOs\NfeFile;
use App\Modules\Fiscal\Enums\NfeRefusal;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * The screen's upload (R15): one XML, or a ZIP of XMLs read entry by entry in memory — nothing is
 * extracted to disk. A name kept is the base name (the upload's, or the entry's), shown in the batch
 * only; it never becomes a path (plan AM3).
 *
 * ZIP safety (plan AM3): the sizes an archive declares (`statIndex`) are never trusted — each entry
 * is read through its stream, at most 1 MB + 1 byte (more → `too_large`), and the whole archive at
 * most 20 MB (past it every later entry is `too_large`, unread). Directories, nested ZIPs, any name
 * not ending `.xml` and macOS's `__MACOSX/` / `._*` shadows are skipped, not counted. An archive of
 * more than 2000 entries is refused whole as `too_large`; one that cannot be opened is `not_xml`.
 */
final class UploadedNfeSource implements NfeDocumentSource
{
    /** The most an archive's entries add up to, read (plan Defaults: the import's 20 MB). */
    public const MAX_ZIP_READ_BYTES = 20 * 1_048_576;

    /** The most entries an archive may list (plan Defaults: ≤ 2000 files). */
    public const MAX_ZIP_ENTRIES = 2000;

    private const MAX_NAME_LENGTH = 200;

    private const XML_EXTENSION = '.xml';

    private const ZIP_EXTENSION = 'zip';

    private const MAC_FOLDER = '__MACOSX/';

    private const MAC_SHADOW_PREFIX = '._';

    public function __construct(private UploadedFile $file) {}

    /** @return iterable<NfeFile> */
    public function files(): iterable
    {
        $name = self::baseName($this->file->getClientOriginalName());

        if (strtolower($this->file->getClientOriginalExtension()) === self::ZIP_EXTENSION) {
            yield from $this->zipEntries($name);

            return;
        }

        if ((int) $this->file->getSize() > self::MAX_XML_BYTES) {
            yield new NfeFile($name, null, NfeRefusal::TooLarge);

            return;
        }

        yield new NfeFile($name, (string) $this->file->get());
    }

    /** @return iterable<NfeFile> */
    private function zipEntries(string $archiveName): iterable
    {
        $zip = new ZipArchive;
        $path = $this->file->getRealPath();

        if ($path === false || $zip->open($path, ZipArchive::RDONLY) !== true) {
            yield new NfeFile($archiveName, null, NfeRefusal::NotXml);

            return;
        }

        try {
            if ($zip->count() > self::MAX_ZIP_ENTRIES) {
                yield new NfeFile($archiveName, null, NfeRefusal::TooLarge);

                return;
            }

            $read = 0;

            for ($index = 0; $index < $zip->count(); $index++) {
                $entryName = (string) $zip->getNameIndex($index);

                if (! self::isXmlEntry($entryName)) {
                    continue;
                }

                if ($read > self::MAX_ZIP_READ_BYTES) {
                    yield new NfeFile(self::baseName($entryName), null, NfeRefusal::TooLarge);

                    continue;
                }

                $content = $this->readEntry($zip, $index);
                $read += strlen($content ?? '');

                yield $this->entryFile(self::baseName($entryName), $content, $read);
            }
        } finally {
            $zip->close();
        }
    }

    /** At most 1 MB + 1 byte of the entry, whatever its header says; null when it cannot be read. */
    private function readEntry(ZipArchive $zip, int $index): ?string
    {
        $stream = $zip->getStreamIndex($index);

        if ($stream === false) {
            return null;
        }

        $content = stream_get_contents($stream, self::MAX_XML_BYTES + 1);
        fclose($stream);

        return $content === false ? null : $content;
    }

    private function entryFile(string $name, ?string $content, int $read): NfeFile
    {
        $isUnreadable = $content === null;
        $isTooLarge = strlen($content ?? '') > self::MAX_XML_BYTES || $read > self::MAX_ZIP_READ_BYTES;

        return match (true) {
            $isUnreadable => new NfeFile($name, null, NfeRefusal::NotXml),
            $isTooLarge => new NfeFile($name, null, NfeRefusal::TooLarge),
            default => new NfeFile($name, $content),
        };
    }

    private static function isXmlEntry(string $entryName): bool
    {
        $base = self::baseName($entryName);
        $isDirectory = str_ends_with($entryName, '/');
        $isMacShadow = str_starts_with($entryName, self::MAC_FOLDER) || str_starts_with($base, self::MAC_SHADOW_PREFIX);

        return ! $isDirectory && ! $isMacShadow && str_ends_with(strtolower($base), self::XML_EXTENSION);
    }

    private static function baseName(string $name): string
    {
        return mb_substr(basename(str_replace('\\', '/', $name)), 0, self::MAX_NAME_LENGTH);
    }
}
