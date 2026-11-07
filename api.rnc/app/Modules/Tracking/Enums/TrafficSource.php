<?php

namespace App\Modules\Tracking\Enums;

/**
 * Where a visit came from, as the site derives it from `utm_source` / `utm_medium` / the referrer
 * (rnc-1 R7). Only this word reaches the API; the raw referrer never does (AM2).
 */
enum TrafficSource: string
{
    case Organic = 'organico';
    case Paid = 'pago';
    case Partner = 'parceiro';
    case Direct = 'direto';
}
