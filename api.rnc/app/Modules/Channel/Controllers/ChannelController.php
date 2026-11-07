<?php

namespace App\Modules\Channel\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Channel\DTOs\ChannelFirmDTO;
use App\Modules\Channel\DTOs\UpdateChannelFirmDTO;
use App\Modules\Channel\Requests\AdvanceChannelFirmRequest;
use App\Modules\Channel\Requests\StoreChannelFirmRequest;
use App\Modules\Channel\Requests\UpdateChannelFirmRequest;
use App\Modules\Channel\Resources\ChannelResource;
use App\Modules\Channel\Services\ChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The RNC channel (rnc-3 R6–R9): the board behind `channel.read` (Reinaldo and the operator) and the
 * writes behind `channel.manage` (the operator). Every answer is the whole board, so the hero and
 * the stage counts follow a move. The price shows only to `channel.manage` (AM15). Another firm's
 * channel firm is a 404, never a 403.
 */
class ChannelController extends Controller
{
    public const MANAGE_KEY = 'channel.manage';

    private const NOT_FOUND = 'channel.notFound';

    public function __construct(
        private ChannelService $channelService,
        private PermissionService $permissionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(data: (new ChannelResource($this->channelService->board()))->withPrices($this->canManage($request)));
    }

    public function store(StoreChannelFirmRequest $request): JsonResponse
    {
        $this->channelService->create(ChannelFirmDTO::from($request));

        return ApiResponse::created(data: $this->managerBoard());
    }

    public function update(UpdateChannelFirmRequest $request, int $id): JsonResponse
    {
        $firm = $this->channelService->update($id, UpdateChannelFirmDTO::from($request));

        return ! $firm ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: $this->managerBoard());
    }

    public function advance(AdvanceChannelFirmRequest $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $firm = $this->channelService->advance($id, $request->stage(), $user);

        return ! $firm ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: $this->managerBoard());
    }

    private function canManage(Request $request): bool
    {
        /** @var User $user */
        $user = $request->user();

        return in_array(self::MANAGE_KEY, $this->permissionService->forUser($user), true);
    }

    /** Only `channel.manage` reaches a write, so its answer carries the prices. */
    private function managerBoard(): ChannelResource
    {
        return (new ChannelResource($this->channelService->board()))->withPrices();
    }
}
