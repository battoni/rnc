<?php

namespace App\Modules\Portfolio\Enums;

/**
 * The lines of a client's timeline (rnc-2 D12). Fiscal writes two, through `ClientService`:
 * the readiness notice (R20) and the supplier review (R22); the contract alert writes "Cláusula
 * incluída" (rnc-3 R22). Nothing is sent to the client.
 */
enum ClientEventType: string
{
    case NfeNotice = 'nfe.aviso';
    case SupplierReview = 'fornecedores.revisao';
    case ContractClause = 'contrato.clausula';
}
