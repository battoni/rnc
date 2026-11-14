<?php

namespace App\Modules\Lead\Services;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Firm\Services\MailBrand;
use App\Modules\Lead\DTOs\BetaSignupDTO;
use App\Modules\Lead\DTOs\BetaSignupMailDTO;
use App\Modules\Lead\Mail\BetaSignupMail;
use App\Modules\Lead\Models\Lead;
use App\Modules\Notification\Notifications\BetaSignupNotification;
use App\Modules\Notification\Services\TeamNotifier;

/**
 * The platform's beta signup (rnc-4 R29, D29): the product is firm zero, so a firm signing up on the
 * root site becomes a `beta` lead on the platform firm's own pipeline — and nowhere else. Every
 * method answers null when the current firm is not the platform firm (the controller's 404; the
 * route's `platform.firm` middleware refuses first). The lead is written by `LeadService`; the
 * platform firm's team hears it by bell and e-mail, under the platform firm's brand.
 */
class BetaSignupService
{
    public function __construct(
        private ConsentTextService $consentTextService,
        private CurrentFirm $currentFirm,
        private LeadService $leadService,
        private TeamNotifier $teamNotifier,
    ) {}

    public function signUp(BetaSignupDTO $signup): ?Lead
    {
        $firm = $this->platformFirm();

        if (! $firm) {
            return null;
        }

        $lead = $this->leadService->recordBetaSignup($signup);

        $this->teamNotifier->notifyTeam(new BetaSignupNotification($lead->id, $signup->company, $signup->city, $signup->uf));
        $this->teamNotifier->mailTeam(new BetaSignupMail(BetaSignupMailDTO::fromSignup($lead->id, $signup, MailBrand::fromFirm($firm))));

        return $lead;
    }

    /**
     * The words the signup form asks consent under: the platform firm's current version.
     *
     * @return array{text: string, version: int}|null
     */
    public function consent(): ?array
    {
        $firm = $this->platformFirm();

        return $firm ? $this->consentTextService->forFirm($firm) : null;
    }

    private function platformFirm(): ?Firm
    {
        $firm = $this->currentFirm->get();

        return $firm?->is_platform ? $firm : null;
    }
}
