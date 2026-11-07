<?php

namespace App\Modules\Fiscal\Sources;

use App\Modules\Fiscal\Contracts\NfeDocumentSource;
use App\Modules\Fiscal\DTOs\NfeFile;
use App\Modules\Fiscal\Enums\NfeRefusal;
use Illuminate\Http\UploadedFile;

/**
 * The screen's upload (R15): one XML, read in memory. A file over 1 MB is refused `too_large` from
 * its size, without reading it. The name kept is the client's base name, shown in the batch only —
 * it never becomes a path (plan AM3). The ZIP arrives in F6b.
 */
final class UploadedNfeSource implements NfeDocumentSource
{
    private const MAX_NAME_LENGTH = 200;

    public function __construct(private UploadedFile $file) {}

    /** @return iterable<NfeFile> */
    public function files(): iterable
    {
        $name = mb_substr(basename(str_replace('\\', '/', $this->file->getClientOriginalName())), 0, self::MAX_NAME_LENGTH);

        if ((int) $this->file->getSize() > self::MAX_XML_BYTES) {
            yield new NfeFile($name, null, NfeRefusal::TooLarge);

            return;
        }

        yield new NfeFile($name, (string) $this->file->get());
    }
}
