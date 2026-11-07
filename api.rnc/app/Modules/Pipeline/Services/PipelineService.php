<?php

namespace App\Modules\Pipeline\Services;

use App\Models\User;
use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Repositories\AppointmentRepository;
use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\Services\RulesEngine;
use App\Modules\Lead\Enums\LeadStage;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Lead\Repositories\LeadRepository;
use App\Modules\Lead\Services\LeadService;
use App\Modules\Pipeline\DTOs\LeadBoardFilterDTO;
use App\Modules\Pipeline\DTOs\MoveLeadStageDTO;
use Illuminate\Database\Eloquent\Collection;

/**
 * The team's pipeline (rnc-1 R18–R19): the board, the lead detail with the diagnostic the visitor
 * does not see, and the team's moves and notes. Reads the Lead and Booking tables; every write goes
 * through `LeadService`, the only writer of the lead tables.
 */
class PipelineService
{
    /** A column shows at most this many cards; its count is always the whole. */
    public const COLUMN_LIMIT = 60;

    public function __construct(
        private LeadRepository $leadRepository,
        private LeadService $leadService,
        private AppointmentRepository $appointmentRepository,
        private RulesEngine $rulesEngine,
    ) {}

    /**
     * One column per stage in the board's order, each with its count and its newest cards.
     *
     * @return list<array{stage: string, count: int, items: list<array{lead: Lead, opportunities: int, risks: int, meeting: Appointment|null}>}>
     */
    public function board(LeadBoardFilterDTO $filter): array
    {
        $counts = $this->leadRepository->countLeadsByStage($filter->search);
        $stages = $filter->stage !== null ? [LeadStage::from($filter->stage)] : LeadStage::boardOrder();
        $columns = [];

        foreach ($stages as $stage) {
            $leads = $this->leadRepository->findLeadsOfStage($stage, $filter->search, self::COLUMN_LIMIT);

            $columns[] = ['stage' => $stage->value, 'count' => $counts[$stage->value] ?? 0, 'items' => $this->cards($leads)];
        }

        return $columns;
    }

    /**
     * The lead detail (R19): the lead, the full diagnostic (details and cited rules unlocked for
     * staff), its meeting and its timeline. Null for an unknown or another firm's lead.
     *
     * @return array{lead: Lead, diagnosis: array<string, mixed>, meeting: Appointment|null, timeline: Collection<int, LeadEvent>}|null
     */
    public function detail(int $id): ?array
    {
        $lead = $this->leadRepository->findLeadById($id);

        if (! $lead) {
            return null;
        }

        return [
            'lead' => $lead,
            'diagnosis' => $this->rulesEngine->diagnose(DiagnosticProfileDTO::from($lead->profile)),
            'meeting' => $this->appointmentRepository->findAppointmentOfLead($lead),
            'timeline' => $this->leadRepository->findLeadEvents($lead),
        ];
    }

    /**
     * A card's move (R18): the card as it is now and the timeline line it wrote.
     *
     * @return array{card: array{lead: Lead, opportunities: int, risks: int, meeting: Appointment|null}, event: LeadEvent}|null
     */
    public function move(int $id, MoveLeadStageDTO $move, User $by): ?array
    {
        $lead = $this->leadRepository->findLeadById($id);

        if (! $lead) {
            return null;
        }

        $moved = $this->leadService->moveStage($lead, LeadStage::from($move->stage), $move->reason, $move->via, $by);

        return ['card' => $this->cards(new Collection([$moved['lead']]))[0], 'event' => $moved['event']];
    }

    public function addNote(int $id, string $text, User $by): ?LeadEvent
    {
        $lead = $this->leadRepository->findLeadById($id);

        return ! $lead ? null : $this->leadService->addNote($lead, $text, $by);
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return list<array{lead: Lead, opportunities: int, risks: int, meeting: Appointment|null}>
     */
    private function cards(Collection $leads): array
    {
        $meetings = $this->appointmentRepository->findAppointmentsOfLeads($leads->modelKeys());

        return $leads->map(function (Lead $lead) use ($meetings) {
            $diagnosis = $this->rulesEngine->diagnose(DiagnosticProfileDTO::from($lead->profile));

            return ['lead' => $lead, 'opportunities' => $diagnosis['opportunities'], 'risks' => $diagnosis['risks'], 'meeting' => $meetings->get($lead->id)];
        })->values()->all();
    }
}
