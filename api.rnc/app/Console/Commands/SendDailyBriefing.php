<?php

namespace App\Console\Commands;

use App\Modules\Briefing\Services\DailyBriefingSender;
use Illuminate\Console\Command;

/**
 * `briefing:send` (rnc-3 R27, AM6), scheduled daily at 07:00 São Paulo in `routes/console.php`:
 * Reinaldo's briefing by e-mail to every active ceo of each firm, on business days only. The work,
 * the day-off check and the walk over the firms are `DailyBriefingSender`'s.
 */
class SendDailyBriefing extends Command
{
    protected $signature = 'briefing:send';

    protected $description = 'Queue the daily briefing to every active ceo of each firm (business days only)';

    public function handle(DailyBriefingSender $dailyBriefingSender): int
    {
        $queued = $dailyBriefingSender->send();

        $this->info("{$queued} briefing(s) enviado(s).");

        return self::SUCCESS;
    }
}
