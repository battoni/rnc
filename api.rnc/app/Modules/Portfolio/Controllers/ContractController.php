<?php

namespace App\Modules\Portfolio\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Portfolio\Requests\ImportContractsRequest;
use App\Modules\Portfolio\Resources\ContractAlertResource;
use App\Modules\Portfolio\Resources\ContractClauseResource;
use App\Modules\Portfolio\Services\ContractImportService;
use App\Modules\Portfolio\Services\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The contract alert (rnc-3 R18, R19, R22), behind `contracts.manage`: the list and its KPIs, the CSV
 * import and its template, and "Cláusula incluída". Another firm's contract is a 404, never a 403.
 */
class ContractController extends Controller
{
    private const NOT_FOUND = 'portfolio.contractNotFound';

    private const TEMPLATE_NAME = 'modelo-contratos.csv';

    private const CSV_CONTENT_TYPE = 'text/csv; charset=UTF-8';

    public function __construct(
        private ContractService $contractService,
        private ContractImportService $contractImportService,
    ) {}

    public function index(): JsonResponse
    {
        $alert = $this->contractService->alert();

        return ApiResponse::success(data: [
            'summary' => $alert['summary'],
            'contracts' => $alert['contracts']->map(fn ($contract) => new ContractAlertResource($contract, $alert['referred_to'][$contract->id] ?? [])),
        ]);
    }

    public function import(ImportContractsRequest $request): JsonResponse
    {
        $file = $request->file('file');

        return ApiResponse::success(data: $this->contractImportService->import($file instanceof UploadedFile ? (string) $file->get() : ''));
    }

    public function template(): StreamedResponse
    {
        $content = $this->contractImportService->template();

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, self::TEMPLATE_NAME, ['Content-Type' => self::CSV_CONTENT_TYPE]);
    }

    public function clause(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $contract = $this->contractService->markClause($id, $user);

        return ! $contract ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new ContractClauseResource($contract));
    }
}
