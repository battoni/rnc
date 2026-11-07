<?php

namespace App\Modules\Tracking\Enums;

/**
 * The funnel steps a visitor's browser may report (rnc-1 R7, plan AM2). F7's funnel reads them in
 * this order.
 */
enum EventType: string
{
    case Visit = 'visit';
    case Cta = 'cta';
    case DiagnosticStart = 'diag.start';
    case DiagnosticContact = 'diag.contact';
    case DiagnosticResult = 'diag.result';
    case Booking = 'booking';
}
