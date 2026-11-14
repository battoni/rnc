<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use Closure;

/**
 * The firm every firm-scoped query of this request (or this command run) belongs to.
 *
 * A container binding (scoped, so it never leaks from one request to the next), set by
 * `ResolveCurrentFirm` from the signed-in person or the `X-Firm` header, and read by `FirmScope`.
 * Nothing else decides which firm a query sees: a service that wants another firm's rows is a bug,
 * and a console command walks the firms with `forEachFirm()` (plan AM6).
 */
class CurrentFirm
{
    private ?Firm $firm = null;

    public function get(): ?Firm
    {
        return $this->firm;
    }

    public function id(): ?int
    {
        return $this->firm?->id;
    }

    public function set(?Firm $firm): void
    {
        $this->firm = $firm;
    }

    /** Whether the current firm has the module on (rnc-4 R16); no firm has none. */
    public function hasModule(string $module): bool
    {
        return $this->firm !== null && FirmModules::isEnabled($this->firm, $module);
    }

    /**
     * Run a callback as one firm and put back whatever was current before — a command's step, a
     * seeder's rows. The previous firm comes back even when the callback throws.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function runAs(Firm $firm, Closure $callback): mixed
    {
        $previous = $this->firm;
        $this->firm = $firm;

        try {
            return $callback();
        } finally {
            $this->firm = $previous;
        }
    }

    /**
     * What a scheduled command does: the same work once per firm, each run seeing only its rows. An
     * offboarded firm (rnc-4 F9a) is skipped: no reminder, briefing or digest goes out for it.
     *
     * @param  Closure(Firm): void  $callback
     */
    public function forEachFirm(Closure $callback): void
    {
        Firm::query()->whereNull('offboarded_at')->orderBy('id')->each(fn (Firm $firm) => $this->runAs($firm, fn () => $callback($firm)));
    }

    /**
     * `forEachFirm()` over the firms with the module on (rnc-4 R17): scheduled work of a module
     * skips a firm that has it off.
     *
     * @param  Closure(Firm): void  $callback
     */
    public function forEachFirmWithModule(string $module, Closure $callback): void
    {
        $this->forEachFirm(function (Firm $firm) use ($module, $callback) {
            if (FirmModules::isEnabled($firm, $module)) {
                $callback($firm);
            }
        });
    }
}
