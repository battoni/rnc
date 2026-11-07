<?php

namespace App\Modules\Portfolio\Enums;

/**
 * The lines of a client's timeline (rnc-2 D12). Fiscal writes both, through `ClientService`:
 * the readiness notice (R20) and the supplier review (R22). Nothing is sent to the client.
 */
enum ClientEventType: string
{
    case NfeNotice = 'nfe.aviso';
    case SupplierReview = 'fornecedores.revisao';
}
