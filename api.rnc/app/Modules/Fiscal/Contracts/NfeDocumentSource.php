<?php

namespace App\Modules\Fiscal\Contracts;

use App\Modules\Fiscal\DTOs\NfeFile;

/**
 * Where an import's XMLs come from (rnc-2 D5). No container binding: the caller picks the driver —
 * `UploadedNfeSource` for the screen's upload, `FolderNfeSource` for `nfe:import` (F6b). A SEFAZ
 * DF-e driver would be a third implementation; none is written (Fiscal RULES → Known Gaps).
 */
interface NfeDocumentSource
{
    /** No XML is read past this: 1 MB (plan Defaults). */
    public const MAX_XML_BYTES = 1_048_576;

    /** @return iterable<NfeFile> */
    public function files(): iterable;
}
