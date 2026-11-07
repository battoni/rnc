<?php

namespace App\Modules\Notification\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Notification\Resources\NotificationResource;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The back office's bell (rnc-1 R17), polled every 30 s by the SPA. */
class NotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $inbox = $this->notificationService->inbox($this->person($request));

        return ApiResponse::success(data: ['unread_count' => $inbox['unread_count'], 'items' => NotificationResource::collection($inbox['items'])]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->notificationService->markRead($this->person($request), $id);

        return ! $notification ? ApiResponse::notFound(message: 'notifications.notFound') : ApiResponse::success(data: new NotificationResource($notification));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        return ApiResponse::success(data: ['unread_count' => $this->notificationService->markAllRead($this->person($request))]);
    }

    private function person(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
