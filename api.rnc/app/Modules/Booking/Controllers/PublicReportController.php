<?php

namespace App\Modules\Booking\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Booking\Resources\PublicReportResource;
use App\Modules\Booking\Services\ReportService;
use Illuminate\Http\JsonResponse;

/** The "Relatório de Impacto" the confirmation e-mail links (rnc-1 R17, plan AM4, AM15). */
class PublicReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function show(string $reportToken): JsonResponse
    {
        $report = $this->reportService->report($reportToken);

        return ! $report ? ApiResponse::notFound(message: 'booking.reportNotFound') : ApiResponse::success(data: new PublicReportResource($report));
    }
}
