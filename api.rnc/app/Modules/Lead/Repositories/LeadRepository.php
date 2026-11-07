<?php

namespace App\Modules\Lead\Repositories;

use App\Modules\Lead\Enums\LeadStage;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Models\LeadEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/** Every read is firm-scoped by `BelongsToFirm`: another firm's token is simply not found. */
class LeadRepository
{
    /** @param  array<string, mixed>  $attributes */
    public function createLead(array $attributes): Lead
    {
        return Lead::query()->create($attributes);
    }

    /** The lead of a token that has not expired, in the current firm. */
    public function findLeadByLiveToken(string $token, Carbon $now): ?Lead
    {
        return Lead::query()->where('token', $token)->where('token_expires_at', '>', $now)->first();
    }

    /** The lead whose "Relatório de Impacto" link has not expired, in the current firm (AM4). */
    public function findLeadByLiveReportToken(string $reportToken, Carbon $now): ?Lead
    {
        return Lead::query()->where('report_token', $reportToken)->where('report_expires_at', '>', $now)->first();
    }

    public function findLeadById(int $id): ?Lead
    {
        return Lead::query()->with('latestConsent')->find($id);
    }

    /**
     * One column of the board: the most recently touched leads of a stage, up to a ceiling.
     *
     * @return Collection<int, Lead>
     */
    public function findLeadsOfStage(LeadStage $stage, ?string $search, int $limit): Collection
    {
        return $this->searched(Lead::query(), $search)
            ->where('stage', $stage->value)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, int> how many leads each stage holds, by its slug */
    public function countLeadsByStage(?string $search): array
    {
        return $this->searched(Lead::query(), $search)
            ->toBase()
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->map(fn (mixed $total) => (int) $total)
            ->all();
    }

    /** @return Collection<int, LeadEvent> the timeline, newest first, with who did each line */
    public function findLeadEvents(Lead $lead): Collection
    {
        return $lead->events()->with('user')->orderByDesc('at')->orderByDesc('id')->get();
    }

    /** @param  array<string, mixed>  $attributes */
    public function updateLead(Lead $lead, array $attributes): Lead
    {
        $lead->update($attributes);

        return $lead;
    }

    /** @param  array<string, mixed>  $attributes */
    public function createLeadConsent(Lead $lead, array $attributes): LeadConsent
    {
        return $lead->consents()->create($attributes);
    }

    /** @param  array<string, mixed>|null  $data */
    public function createLeadEvent(Lead $lead, string $type, ?array $data, Carbon $at, ?int $userId = null): LeadEvent
    {
        return $lead->events()->create(['type' => $type, 'data' => $data, 'at' => $at, 'user_id' => $userId]);
    }

    public function hasLeadEvent(Lead $lead, string $type): bool
    {
        return $lead->events()->where('type', $type)->exists();
    }

    /**
     * The search box (R18): a piece of the company, the contact's name or e-mail, or of the CNPJ's
     * digits. `like` is portable SQL (AM11) and ignores the case of ASCII letters on both engines.
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    private function searched(Builder $query, ?string $search): Builder
    {
        $term = trim((string) $search);

        if ($term === '') {
            return $query;
        }

        $pattern = '%'.addcslashes($term, '%_\\').'%';
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        return $query->where(function (Builder $where) use ($pattern, $digits) {
            $where->where('company', 'like', $pattern)
                ->orWhere('contact_name', 'like', $pattern)
                ->orWhere('email', 'like', $pattern);

            if ($digits !== '') {
                $where->orWhere('cnpj', 'like', '%'.$digits.'%');
            }
        });
    }
}
