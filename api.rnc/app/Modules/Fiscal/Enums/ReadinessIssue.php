<?php

namespace App\Modules\Fiscal\Enums;

/**
 * What an incomplete invoice misses (rnc-2 R19, the parser's three item checks — Fiscal RULES.md
 * "Item checks"), in the detail's order. An invoice counts once, under the worst issue any of its
 * items has, so the lines add up to the incomplete invoices like the demo's `nfeIssues`.
 */
enum ReadinessIssue: string
{
    /** No `IBSCBS` group: rejection 1115 once validated — the invoice is AT RISK. */
    case MissingGroup = 'missing_group';
    /** `IBSCBS/CST` or `cClassTrib` blank. */
    case MissingCodes = 'missing_codes';
    /** The 2026 test rates (CBS 0,9 % · IBS 0,1 %) not shown. */
    case WithoutTestValues = 'without_test_values';

    public function isRisk(): bool
    {
        return $this === self::MissingGroup;
    }

    /** The `nfe_documents` column counting the items that fail this check first. */
    public function column(): string
    {
        return 'items_'.$this->value;
    }
}
