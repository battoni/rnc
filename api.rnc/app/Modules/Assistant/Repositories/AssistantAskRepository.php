<?php

namespace App\Modules\Assistant\Repositories;

use App\Modules\Assistant\Models\AssistantAsk;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** The current firm's ask log (R15). `BelongsToFirm` scopes and stamps the firm. */
class AssistantAskRepository
{
    private const GAP_LIMIT = 50;

    /** @param  array{user_id: int, question: string, normalised: string, note_id: int|null, at: CarbonImmutable}  $attributes */
    public function createAsk(array $attributes): AssistantAsk
    {
        return AssistantAsk::query()->create($attributes);
    }

    /**
     * "Perguntas sem nota": the asks since `$since` that no note answered, one line per normalised
     * text — most asked first, then the latest — in one grouped query (plan P13). `question` is one
     * of the line's own wordings (the greatest, so the answer is stable).
     *
     * @return list<array{normalised: string, question: string, count: int, last_asked_at: CarbonImmutable}>
     */
    public function findAskGapsSince(CarbonImmutable $since): array
    {
        return AssistantAsk::query()
            ->toBase()
            ->whereNull('note_id')
            ->where('at', '>=', $since->utc()->format('Y-m-d H:i:s'))
            ->select('normalised')
            ->selectRaw('COUNT(*) AS ask_count')
            ->selectRaw('MAX(question) AS sample_question')
            ->selectRaw('MAX(at) AS last_asked_at')
            ->groupBy('normalised')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderByDesc(DB::raw('MAX(at)'))
            ->orderBy('normalised')
            ->limit(self::GAP_LIMIT)
            ->get()
            ->map(fn (object $row) => [
                'normalised' => (string) $row->normalised,
                'question' => (string) $row->sample_question,
                'count' => (int) $row->ask_count,
                'last_asked_at' => CarbonImmutable::parse((string) $row->last_asked_at, 'UTC'),
            ])
            ->values()
            ->all();
    }
}
