<?php

namespace App\Modules\Assistant\Services;

use App\Modules\Assistant\Repositories\AssistantNoteRepository;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Notification\Notifications\AssistantNoteExpiringNotification;
use App\Modules\Notification\Services\TeamNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The reviewer hears that a review runs out (rnc-3 R11, AM19): every firm with the `assistant` module (rnc-4 R17) in turn, each served note
 * in its last seven days (day 83 to 90) rings its reviewer once per review — the claim on
 * `expiry_notified_at` keeps a second run quiet, and a newer review makes the old claim stale. A
 * missed day 83 rings on the next run; a reviewer who left (inactive) or none at all rings nobody,
 * and the base's counter still shows the note (W63).
 */
class NoteExpiryService
{
    public function __construct(
        private AssistantNoteRepository $noteRepository,
        private CurrentFirm $currentFirm,
        private TeamNotifier $teamNotifier,
    ) {}

    /** @return int the bells rung */
    public function notifyExpiring(): int
    {
        $rung = 0;
        $now = CarbonImmutable::now();

        $this->currentFirm->forEachFirmWithModule(FirmModules::ASSISTANT, function () use ($now, &$rung) {
            foreach ($this->noteRepository->findNotesToWarn($now) as $note) {
                $reviewer = $note->reviewer;

                if (! $reviewer || $reviewer->status !== UserStatus::ACTIVE) {
                    continue;
                }

                // The claim and the bell commit together: a failed bell leaves the review unclaimed.
                $rung += DB::transaction(function () use ($note, $now, $reviewer) {
                    if (! $this->noteRepository->claimExpiryNotice($note, $now)) {
                        return 0;
                    }

                    $this->teamNotifier->notifyUser($reviewer, new AssistantNoteExpiringNotification(
                        noteId: $note->id,
                        question: $note->question,
                        expiresOn: (string) $note->expiresOn()?->toDateString(),
                    ));

                    return 1;
                });
            }
        });

        return $rung;
    }
}
