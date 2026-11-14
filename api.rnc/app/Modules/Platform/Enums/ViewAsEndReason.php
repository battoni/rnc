<?php

namespace App\Modules\Platform\Enums;

/**
 * Why a view-as session ended (rnc-4 R26, D20): the operator's "Sair", their logout, or — left open
 * when the session itself ended — closed at their next sign-in.
 */
enum ViewAsEndReason: string
{
    case Leave = 'sair';
    case Logout = 'logout';
    case Expired = 'expirou';
}
