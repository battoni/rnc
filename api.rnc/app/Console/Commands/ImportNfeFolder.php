<?php

namespace App\Console\Commands;

use App\Modules\Firm\Repositories\FirmRepository;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Fiscal\Enums\NfeImportSource;
use App\Modules\Fiscal\Models\NfeImport;
use App\Modules\Fiscal\Services\NfeImporter;
use App\Modules\Fiscal\Sources\FolderNfeSource;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * `nfe:import <folder> --firm=<slug>` (rnc-2 R15): a folder of NF-e XMLs imported into one firm by
 * the same `NfeImporter` as the upload — a batch of source `folder` with no user. The folder rules
 * (realpath, a directory, no symbolic link) are `FolderNfeSource`'s; the work is the importer's.
 */
class ImportNfeFolder extends Command
{
    protected $signature = 'nfe:import {folder : A pasta com os XMLs} {--firm= : O slug da firma (ex.: alvorada)}';

    protected $description = 'Import the NF-e XMLs of a folder into one firm (one batch, refusals by file)';

    public function handle(FirmRepository $firmRepository, CurrentFirm $currentFirm, NfeImporter $nfeImporter): int
    {
        $slug = (string) $this->option('firm');
        $firm = $slug === '' ? null : $firmRepository->findFirmBySlug($slug);

        if ($firm === null) {
            $this->error("Firma desconhecida: informe --firm=<slug> de uma firma existente ('{$slug}').");

            return self::FAILURE;
        }

        try {
            $source = FolderNfeSource::open((string) $this->argument('folder'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        /** @var NfeImport $import */
        $import = $currentFirm->runAs($firm, fn () => $nfeImporter->import($source, NfeImportSource::Folder, null));

        $this->info("{$import->files} arquivo(s) lido(s), {$import->stored} nota(s) guardada(s), ".count($import->refused).' recusa(s).');

        if ($import->refused !== []) {
            $this->table(['Arquivo', 'Motivo'], array_map(fn (array $refusal) => [$refusal['file'], $refusal['code']], $import->refused));
        }

        return self::SUCCESS;
    }
}
