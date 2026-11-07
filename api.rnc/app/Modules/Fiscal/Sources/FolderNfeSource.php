<?php

namespace App\Modules\Fiscal\Sources;

use App\Modules\Fiscal\Contracts\NfeDocumentSource;
use App\Modules\Fiscal\DTOs\NfeFile;
use App\Modules\Fiscal\Enums\NfeRefusal;
use InvalidArgumentException;

/**
 * `php artisan nfe:import <folder>` (R15): the `.xml` files directly inside one folder, in name
 * order. Folder safety (plan AM3): the path is resolved with `realpath` and must be a directory that
 * is not itself a symbolic link; inside it, a symbolic link is never followed, subfolders and other
 * names are skipped (not counted), and each file is read through its stream at most 1 MB + 1 byte —
 * more is `too_large`, whatever the file system says its size is. The batch names a file by its base
 * name only.
 */
final class FolderNfeSource implements NfeDocumentSource
{
    private const XML_EXTENSION = '.xml';

    private function __construct(private string $root) {}

    /** @throws InvalidArgumentException when `$folder` is no directory, or is a symbolic link */
    public static function open(string $folder): self
    {
        $trimmed = rtrim($folder, '/');

        if ($trimmed !== '' && is_link($trimmed)) {
            throw new InvalidArgumentException("{$folder} é um link simbólico: informe a pasta real.");
        }

        $root = realpath($folder);

        if ($root === false || ! is_dir($root)) {
            throw new InvalidArgumentException("{$folder} não é uma pasta.");
        }

        return new self($root);
    }

    /** @return iterable<NfeFile> */
    public function files(): iterable
    {
        foreach ((array) scandir($this->root) as $entry) {
            $path = $this->root.'/'.$entry;
            $isXmlFile = ! is_link($path) && is_file($path) && str_ends_with(strtolower((string) $entry), self::XML_EXTENSION);

            if (! $isXmlFile) {
                continue;
            }

            yield $this->fileAt((string) $entry, $path);
        }
    }

    private function fileAt(string $name, string $path): NfeFile
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return new NfeFile($name, null, NfeRefusal::NotXml);
        }

        $content = stream_get_contents($handle, self::MAX_XML_BYTES + 1);
        fclose($handle);

        return match (true) {
            $content === false => new NfeFile($name, null, NfeRefusal::NotXml),
            strlen($content) > self::MAX_XML_BYTES => new NfeFile($name, null, NfeRefusal::TooLarge),
            default => new NfeFile($name, $content),
        };
    }
}
