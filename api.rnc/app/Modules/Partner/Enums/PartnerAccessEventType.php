<?php

namespace App\Modules\Partner\Enums;

/** What happened to a partner's portal login (`partner_access_events.type`, plan AM9). */
enum PartnerAccessEventType: string
{
    case Granted = 'granted';
    case Revoked = 'revoked';
}
