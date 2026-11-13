<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Fiscal\Contracts\NfeDocumentSource;
use App\Modules\Fiscal\DTOs\NfeFile;
use App\Modules\Fiscal\DTOs\ParsedNfe;
use App\Modules\Fiscal\Enums\NfeDirection;
use App\Modules\Fiscal\Enums\NfeImportSource;
use App\Modules\Fiscal\Enums\NfeRefusal;
use App\Modules\Fiscal\Exceptions\NfeRefusedException;
use App\Modules\Fiscal\Models\NfeImport;
use App\Modules\Fiscal\Repositories\NfeDocumentRepository;
use App\Modules\Fiscal\Repositories\NfeImportRepository;
use App\Modules\Portfolio\Repositories\ClientRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

/**
 * The one code path of every NF-e import (rnc-2 R15–R17): the upload, the folder command and the
 * seeder (F6b) all hand a source here. Each file is parsed, matched by CNPJ to the CURRENT firm's
 * clients (Portfolio's repository, read only — D6) and stored once per client it belongs to:
 * the emitter's `saida`, the recipient's `entrada`, one `saida` when both are the same client. A
 * refusal is written into the batch with the file's name; one bad file never stops the others.
 * Every row is stamped with the firm here, not left to `BelongsToFirm`'s `creating` event: the
 * seeders run under `WithoutModelEvents`, and `NfeSeeder` imports through this same path (F6b).
 */
class NfeImporter
{
    private const ZONE = 'UTC';

    public function __construct(
        private NfeXmlParser $parser,
        private NfeImportRepository $nfeImportRepository,
        private NfeDocumentRepository $nfeDocumentRepository,
        private ClientRepository $clientRepository,
        private CurrentFirm $currentFirm,
    ) {}

    public function import(NfeDocumentSource $source, NfeImportSource $kind, ?int $userId): NfeImport
    {
        $firmId = $this->currentFirm->id();

        if ($firmId === null) {
            throw new LogicException('An NF-e import runs inside a firm (CurrentFirm::runAs).');
        }

        $import = $this->nfeImportRepository->createNfeImport($firmId, $kind, $userId, CarbonImmutable::now(self::ZONE));
        $files = 0;
        $stored = 0;
        $refused = [];

        foreach ($source->files() as $file) {
            $files++;
            $outcome = $this->importFile($file, $import, $firmId);
            $stored += $outcome['stored'];

            foreach ($outcome['refusals'] as $refusal) {
                $refused[] = ['file' => $file->name, 'code' => $refusal->value];
            }
        }

        return $this->nfeImportRepository->updateNfeImportTotals($import, $files, $stored, $refused);
    }

    /** @return array{stored: int, refusals: list<NfeRefusal>} */
    private function importFile(NfeFile $file, NfeImport $import, int $firmId): array
    {
        if ($file->refusal !== null || $file->content === null) {
            return ['stored' => 0, 'refusals' => [$file->refusal ?? NfeRefusal::NotXml]];
        }

        try {
            $document = $this->parser->parse($file->content);
        } catch (NfeRefusedException $exception) {
            return ['stored' => 0, 'refusals' => [$exception->refusal]];
        }

        $owners = $this->owners($document);

        if ($owners === []) {
            return ['stored' => 0, 'refusals' => [NfeRefusal::ClientUnknown]];
        }

        $stored = 0;
        $refusals = [];

        foreach ($owners as $clientId => $direction) {
            if ($this->storeFor($document, $file->content, $clientId, $direction, $import, $firmId)) {
                $stored++;
            } else {
                $refusals[] = NfeRefusal::Duplicate;
            }
        }

        return ['stored' => $stored, 'refusals' => $refusals];
    }

    /**
     * Which of the firm's clients the document belongs to, and as what (D6).
     *
     * @return array<int, NfeDirection> client id → direction, the emitter first
     */
    private function owners(ParsedNfe $document): array
    {
        $cnpjs = array_values(array_filter([$document->emitterCnpj, $document->recipientCnpj]));
        $clientIds = $this->clientRepository->findClientIdsByCnpjs($cnpjs);
        $owners = [];

        if (isset($clientIds[$document->emitterCnpj])) {
            $owners[$clientIds[$document->emitterCnpj]] = NfeDirection::Saida;
        }

        $recipientId = $document->recipientCnpj === null ? null : ($clientIds[$document->recipientCnpj] ?? null);

        if ($recipientId !== null && ! isset($owners[$recipientId])) {
            $owners[$recipientId] = NfeDirection::Entrada;
        }

        return $owners;
    }

    /** False when the client already holds the key — looked up first, and the unique index wins a race. */
    private function storeFor(ParsedNfe $document, string $content, int $clientId, NfeDirection $direction, NfeImport $import, int $firmId): bool
    {
        if ($this->nfeDocumentRepository->existsNfeDocument($document->accessKey, $clientId)) {
            return false;
        }

        try {
            $this->nfeDocumentRepository->createNfeDocument(
                $this->attributes($document, $clientId, $direction, $import, $firmId),
                NfeDocumentRepository::nfeDocumentPath($firmId, $clientId, $document->accessKey),
                $content,
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function attributes(ParsedNfe $document, int $clientId, NfeDirection $direction, NfeImport $import, int $firmId): array
    {
        return [
            'firm_id' => $firmId,
            'client_id' => $clientId,
            'import_id' => $import->id,
            'direction' => $direction->value,
            'access_key' => $document->accessKey,
            'number' => $document->number,
            'series' => $document->series,
            'issued_at' => $document->issuedAt,
            'emitter_cnpj' => $document->emitterCnpj,
            'emitter_crt' => $document->emitterCrt,
            'recipient_cnpj' => $document->recipientCnpj,
            'recipient_kind' => $document->recipientKind->value,
            'total_cents' => $document->totalCents,
            'system' => $document->system,
            'items' => $document->items,
            'items_missing_group' => $document->itemsMissingGroup,
            'items_missing_codes' => $document->itemsMissingCodes,
            'items_without_test_values' => $document->itemsWithoutTestValues,
            'sample' => $document->sample,
        ];
    }
}
