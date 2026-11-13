<?php

namespace App\Console\Commands;

use App\Modules\Assistant\Services\NoteExpiryService;
use Illuminate\Console\Command;

/**
 * `assistant:notify-expiring` (rnc-3 R11, AM19), scheduled daily at 07:00 São Paulo in
 * `routes/console.php`: rings each reviewer whose note's review runs out within 7 days, once per
 * review. The work, and the walk over the firms, is `NoteExpiryService`'s.
 */
class NotifyExpiringNotes extends Command
{
    protected $signature = 'assistant:notify-expiring';

    protected $description = 'Ring the reviewer of every note whose review runs out within 7 days (once per review)';

    public function handle(NoteExpiryService $noteExpiryService): int
    {
        $rung = $noteExpiryService->notifyExpiring();

        $this->info("{$rung} aviso(s) de revisão enviado(s).");

        return self::SUCCESS;
    }
}
