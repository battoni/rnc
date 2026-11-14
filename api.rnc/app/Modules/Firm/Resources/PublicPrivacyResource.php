<?php

namespace App\Modules\Firm\Resources;

use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The data a firm's privacy notice fills its template with (rnc-4 F9a, R38; the page is F9b's
 * `/privacidade`). An allow-list — no id, no timestamps — and every list is a stable key the SPA
 * words, so the notice's prose lives in one place (the template) and the API only says WHICH:
 *
 * - `controller`: the firm, who decides what is done with the data (LGPD "controlador").
 * - `operator`: the platform, which processes it on the firm's behalf ("operador"; `platform.name`).
 * - `collected`: what the diagnostic, the booking and the site keep about a visitor.
 * - `retention`: how long each kind is kept — the lead's data until an anonymisation is asked for or
 *   the firm leaves (`firm:offboard` anonymises every lead), consent answers kept as their proof
 *   (the words read survive an anonymisation), visits pseudonymous (a random id, no IP).
 * - `sharing`: who else may receive it — the partner network, only when the firm has it on, and
 *   only for a referral the lead consented to.
 * - `requests`: where a data request goes — the firm's mailbox and phone.
 *
 * @mixin Firm
 */
class PublicPrivacyResource extends JsonResource
{
    public const COLLECTED = ['company', 'cnpj', 'contact_name', 'email', 'phone', 'diagnostic_answers', 'meeting', 'site_visits', 'consent_records'];

    public const RETENTION = [
        ['data' => 'lead_data', 'rule' => 'until_request_or_firm_exit'],
        ['data' => 'consent_records', 'rule' => 'kept_as_proof'],
        ['data' => 'site_visits', 'rule' => 'pseudonymous'],
    ];

    public const SHARING_PARTNER_NETWORK = 'partner_network';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'controller' => [
                'name' => $this->name,
                'address' => $this->address,
                'is_platform' => $this->is_platform,
            ],
            'operator' => ['name' => (string) config('platform.name')],
            'collected' => self::COLLECTED,
            'retention' => self::RETENTION,
            'sharing' => FirmModules::isEnabled($this->resource, FirmModules::PARTNERS) ? [self::SHARING_PARTNER_NETWORK] : [],
            'requests' => [
                'email' => $this->mailbox,
                'phone' => $this->phone,
            ],
        ];
    }
}
