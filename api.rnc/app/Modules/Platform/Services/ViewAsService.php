<?php

namespace App\Modules\Platform\Services;

use App\Http\Middleware\EnsurePlatformOperator;
use App\Models\User;
use App\Modules\Platform\Enums\ViewAsEndReason;
use App\Modules\Platform\Exceptions\ViewAsException;
use App\Modules\Platform\Models\ViewAsSession;
use App\Modules\Platform\Repositories\PlatformFirmRepository;
use App\Modules\Platform\Repositories\ViewAsSessionRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * "Acessar como suporte" (rnc-4 R26, D20): the firm-less operator enters ONE firm, and every request
 * of that session then works in it (`ResolveCurrentFirm` → Firm's `ViewAsFirm` → `current()`).
 *
 * The session holds only the id of an open audit row (`SESSION_KEY`); the row is the truth. A key is
 * honoured only for the firm-less `operador` whose row it is, while the row is open and its firm is a
 * tenant — so a firm account, an operator placed in a firm, another operator or a guest carrying the
 * key rides nothing, and closing the row (Sair, logout, the next sign-in) ends it wherever it was.
 * It changes the firm, never the permissions: the viewed firm's modules still gate its routes.
 */
class ViewAsService
{
    public const SESSION_KEY = 'platform.view_as';

    private const RESOLVED_ATTRIBUTE = 'platform.view_as.resolved';

    private const LOG_PAGE_SIZE = 25;

    public function __construct(
        private ViewAsSessionRepository $viewAsSessionRepository,
        private PlatformFirmRepository $platformFirmRepository,
    ) {}

    /** The open view-as of this request's session, or null. Read once per request. */
    public function current(Request $request): ?ViewAsSession
    {
        if ($request->attributes->has(self::RESOLVED_ATTRIBUTE)) {
            return $request->attributes->get(self::RESOLVED_ATTRIBUTE);
        }

        $session = $this->find($request);
        $request->attributes->set(self::RESOLVED_ATTRIBUTE, $session);

        return $session;
    }

    /**
     * Enter the firm `$firmId` names: a row (who, which firm, from where, when) and the session key.
     * Null when there is no such firm.
     *
     * @throws ViewAsException already viewing (409), or the platform firm (422)
     */
    public function start(Request $request, User $operator, int $firmId): ?ViewAsSession
    {
        if ($this->current($request) !== null) {
            throw ViewAsException::alreadyActive();
        }

        $firm = $this->platformFirmRepository->findFirmById($firmId);

        if (! $firm) {
            return null;
        }

        if ($firm->is_platform) {
            throw ViewAsException::platformFirm();
        }

        $session = $this->viewAsSessionRepository->createViewAsSession($operator->id, $firm->id, $request->ip());
        $session->setRelation('firm', $firm)->setRelation('operator', $operator);
        $request->session()->put(self::SESSION_KEY, $session->id);
        $request->attributes->set(self::RESOLVED_ATTRIBUTE, $session);

        return $session;
    }

    /** End this session's view-as with `$reason`; the closed row, or null when none was active. */
    public function end(Request $request, ViewAsEndReason $reason): ?ViewAsSession
    {
        $session = $this->current($request);

        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_KEY);
        }

        $request->attributes->set(self::RESOLVED_ATTRIBUTE, null);

        return $session ? $this->viewAsSessionRepository->closeViewAsSession($session, $reason) : null;
    }

    /**
     * A sign-in starts clean: every row this person left open (the session that held it ended
     * without Sair or logout) closes as `expirou`, and the new session carries no key (D20).
     */
    public function closeLeftOpen(User $user, Request $request): void
    {
        $this->viewAsSessionRepository->closeOpenViewAsSessionsOfOperator($user->id, ViewAsEndReason::Expired);

        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_KEY);
        }

        $request->attributes->set(self::RESOLVED_ATTRIBUTE, null);
    }

    /** @return LengthAwarePaginator<int, ViewAsSession> */
    public function log(): LengthAwarePaginator
    {
        return $this->viewAsSessionRepository->paginateViewAsSessions(self::LOG_PAGE_SIZE);
    }

    private function find(Request $request): ?ViewAsSession
    {
        $user = $request->user();

        if (! $user instanceof User || ! EnsurePlatformOperator::isPlatformOperator($user) || ! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_KEY);

        if (! is_int($id)) {
            return null;
        }

        $session = $this->viewAsSessionRepository->findOpenViewAsSessionOfOperator($id, $user->id);

        return $session?->firm && ! $session->firm->is_platform ? $session : null;
    }
}
