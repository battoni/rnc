<?php

namespace App\Modules\Assistant\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assistant\DTOs\AskDTO;
use App\Modules\Assistant\DTOs\QuestionDTO;
use App\Modules\Assistant\Requests\AnswerQuestionRequest;
use App\Modules\Assistant\Requests\AskRequest;
use App\Modules\Assistant\Requests\StoreQuestionRequest;
use App\Modules\Assistant\Resources\AnswerResource;
use App\Modules\Assistant\Resources\GapResource;
use App\Modules\Assistant\Resources\QuestionResource;
use App\Modules\Assistant\Resources\SuggestionResource;
use App\Modules\Assistant\Services\AskService;
use App\Modules\Assistant\Services\QuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Assistente da Reforma" (rnc-3 R12, R13, R15): ask, the chips and the hand-off behind
 * `assistant.ask` (the team and Reinaldo); "Perguntas sem nota" behind `assistant.manage`.
 */
class AssistantController extends Controller
{
    public function __construct(
        private AskService $askService,
        private QuestionService $questionService,
    ) {}

    public function ask(AskRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(data: new AnswerResource($this->askService->ask($user, AskDTO::from($request))));
    }

    public function suggestions(): JsonResponse
    {
        return ApiResponse::success(data: SuggestionResource::collection($this->askService->suggestions()));
    }

    public function handOff(StoreQuestionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::created(data: new QuestionResource($this->questionService->handOff($user, QuestionDTO::from($request))));
    }

    public function questions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(data: QuestionResource::collection($this->questionService->inbox($user)));
    }

    public function answer(AnswerQuestionRequest $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(data: new QuestionResource($this->questionService->answer($user, $id, $request->string('answer')->toString())));
    }

    public function gaps(): JsonResponse
    {
        return ApiResponse::success(data: GapResource::collection($this->askService->gaps()));
    }
}
