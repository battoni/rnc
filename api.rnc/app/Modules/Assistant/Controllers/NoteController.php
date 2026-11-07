<?php

namespace App\Modules\Assistant\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assistant\DTOs\NoteDTO;
use App\Modules\Assistant\DTOs\UpdateNoteDTO;
use App\Modules\Assistant\Requests\StoreNoteRequest;
use App\Modules\Assistant\Requests\UpdateNoteRequest;
use App\Modules\Assistant\Resources\NoteResource;
use App\Modules\Assistant\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Base da Reforma" (rnc-3 R10, R11, AM4): list, write and review behind `assistant.ask` plus
 * `AssistantNotePolicy` (write: `assistant.manage` or a specialist — 403 `assistant.notWriter`;
 * review: a specialist who is not the last editor — 403 `assistant.notReviewer`); retire behind
 * `assistant.manage`. Another firm's note is 404 `assistant.notFound`, never 403.
 */
class NoteController extends Controller
{
    private const NOT_FOUND = 'assistant.notFound';

    public function __construct(private NoteService $noteService) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(data: NoteResource::collection($this->noteService->list($this->user($request))));
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        return ApiResponse::created(data: new NoteResource($this->noteService->create($this->user($request), NoteDTO::from($request))));
    }

    public function update(UpdateNoteRequest $request, int $id): JsonResponse
    {
        $note = $this->noteService->update($this->user($request), $id, UpdateNoteDTO::from($request));

        return ! $note ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new NoteResource($note));
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $note = $this->noteService->review($this->user($request), $id);

        return ! $note ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new NoteResource($note));
    }

    public function retire(int $id): JsonResponse
    {
        $note = $this->noteService->retire($id);

        return ! $note ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new NoteResource($note));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
