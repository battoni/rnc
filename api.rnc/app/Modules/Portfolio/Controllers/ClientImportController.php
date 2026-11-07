<?php

namespace App\Modules\Portfolio\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Portfolio\Requests\ImportClientsRequest;
use App\Modules\Portfolio\Services\ClientImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The portfolio's CSV import and its template (rnc-1 R25, A13). */
class ClientImportController extends Controller
{
    private const TEMPLATE_NAME = 'modelo-clientes.csv';

    private const CSV_CONTENT_TYPE = 'text/csv; charset=UTF-8';

    public function __construct(private ClientImportService $clientImportService) {}

    public function store(ImportClientsRequest $request): JsonResponse
    {
        $file = $request->file('file');

        return ApiResponse::success(data: $this->clientImportService->import($file instanceof UploadedFile ? (string) $file->get() : ''));
    }

    public function template(): StreamedResponse
    {
        $content = $this->clientImportService->template();

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, self::TEMPLATE_NAME, ['Content-Type' => self::CSV_CONTENT_TYPE]);
    }
}
