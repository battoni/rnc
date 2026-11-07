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
    private const LIKE_ESCAPE = '!';

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

    /** The newest lead of this visitor still at `visitante` with a live token, in the current firm. */
    public function findOpenVisitanteOfVisitor(string $visitorUuid, Carbon $now): ?Lead
    {
        return Lead::query()
            ->where('visitor_uuid', $visitorUuid)
            ->where('stage', LeadStage::Visitante->value)
            ->where('token_expires_at', '>', $now)
            ->latest('id')
            ->first();
    }

    public function findLeadById(int $id): ?Lead
    {
        return Lead::query()->with(['latestConsent', 'brand'])->find($id);
    }

    /**
     * One column of the board: the most recently touched leads of a stage, up to a ceiling, with
     * their brand loaded once for the whole column (rnc-3 R4).
     *
     * @param  int|null  $brandId  a brand's id, `Lead::MAIN_BRAND` for the main brand, null for all
     * @return Collection<int, Lead>
     */
    public function findLeadsOfStage(LeadStage $stage, ?string $search, int $limit, ?int $brandId = null): Collection
    {
        return $this->branded($this->searched(Lead::query(), $search), $brandId)
            ->with('brand')
            ->where('stage', $stage->value)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, int> how many leads each stage holds, by its slug */
    public function countLeadsByStage(?string $search, ?int $brandId = null): array
    {
        return $this->branded($this->searched(Lead::query(), $search), $brandId)
            ->toBase()
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->map(fn (mixed $total) => (int) $total)
            ->all();
    }

    /**
     * How many leads each brand holds, in one grouped query (rnc-3 R5): the main brand's under
     * `Lead::MAIN_BRAND`, a sub-brand's under its id. A brand with no lead is absent.
     *
     * @return array<int, int>
     */
    public function countLeadsByBrand(): array
    {
        return Lead::query()
            ->toBase()
            ->selectRaw('brand_id, count(*) as total')
            ->groupBy('brand_id')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) ($row->brand_id ?? Lead::MAIN_BRAND) => (int) $row->total])
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
     * The board's brand filter (rnc-3 R4): null leaves every brand, `Lead::MAIN_BRAND` keeps the
     * leads with no sub-brand, an id keeps that brand's.
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    private function branded(Builder $query, ?int $brandId): Builder
    {
        return match ($brandId) {
            null => $query,
            Lead::MAIN_BRAND => $query->whereNull('brand_id'),
            default => $query->where('brand_id', $brandId),
        };
    }

    /**
     * The search box (R18): a piece of the company, the contact's name or e-mail, or of the CNPJ's
     * digits. `like` is portable SQL (AM11) and ignores the case of ASCII letters on both engines.
     * `%` and `_` typed by the person are literal: SQLite has no default LIKE escape and MySQL's is
     * a backslash that its string literals also eat, so the escape is `!`, named in an explicit
     * `ESCAPE` clause that SQLite, MySQL and PostgreSQL read the same way.
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

        $pattern = '%'.str_replace([self::LIKE_ESCAPE, '%', '_'], [self::LIKE_ESCAPE.self::LIKE_ESCAPE, self::LIKE_ESCAPE.'%', self::LIKE_ESCAPE.'_'], $term).'%';
        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $like = " like ? escape '".self::LIKE_ESCAPE."'";

        return $query->where(function (Builder $where) use ($pattern, $digits, $like) {
            $where->whereRaw('company'.$like, [$pattern])
                ->orWhereRaw('contact_name'.$like, [$pattern])
                ->orWhereRaw('email'.$like, [$pattern]);

            if ($digits !== '') {
                $where->orWhere('cnpj', 'like', '%'.$digits.'%');
            }
        });
    }
}
