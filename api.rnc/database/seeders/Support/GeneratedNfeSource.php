<?php

namespace Database\Seeders\Support;

use App\Modules\Fiscal\Contracts\NfeDocumentSource;
use App\Modules\Fiscal\DTOs\NfeFile;

/**
 * XMLs built in memory by `NfeSeeder`, handed to `NfeImporter` like any other source (R15's one code
 * path): nothing is written to a folder first. Local seeding only — no route or command uses it.
 */
final class GeneratedNfeSource implements NfeDocumentSource
{
    /** @param  iterable<string, string>  $documents  file name → XML */
    public function __construct(private iterable $documents) {}

    /** @return iterable<NfeFile> */
    public function files(): iterable
    {
        foreach ($this->documents as $name => $xml) {
            yield new NfeFile($name, $xml);
        }
    }
}
