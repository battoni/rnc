<?php

namespace App\Modules\Fiscal\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Fiscal\Enums\NfeImportSource;
use App\Modules\Fiscal\Requests\ListNfeImportsRequest;
use App\Modules\Fiscal\Requests\StoreNfeImportRequest;
use App\Modules\Fiscal\Resources\NfeImportResource;
use App\Modules\Fiscal\Services\NfeImporter;
use App\Modules\Fiscal\Services\NfeImportService;
use App\Modules\Fiscal\Sources\UploadedNfeSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * The NF-e upload and its batches (rnc-2 R15, R17), behind `fiscal.import` (gestao, operador): the upload
 * answers 201 with the batch, the list pages the firm's batches newest first.
 */
class NfeImportController extends Controller
{
    public function __construct(private NfeImporter $nfeImporter, private NfeImportService $nfeImportService) {}

    public function index(ListNfeImportsRequest $request): JsonResponse
    {
        return ApiResponse::paginated($this->nfeImportService->batches($request->perPage()), NfeImportResource::class);
    }

    public function store(StoreNfeImportRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $import = $this->nfeImporter->import(new UploadedNfeSource($file), NfeImportSource::Upload, $request->user()?->id);

        return ApiResponse::created(data: new NfeImportResource($this->nfeImportService->batch($import)));
    }
}
