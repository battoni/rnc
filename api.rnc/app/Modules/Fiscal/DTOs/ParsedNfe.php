<?php

namespace App\Modules\Fiscal\DTOs;

use App\Modules\Fiscal\Enums\NfeRecipientKind;
use Carbon\CarbonImmutable;

/**
 * What `NfeXmlParser` read out of one NF-e (R16). Item checks are counts — each item under the
 * first check it fails — plus up to five item numbers (`nItem`) per check in `sample`.
 */
final readonly class ParsedNfe
{
    /**
     * @param  array{missing_group: list<int>, missing_codes: list<int>, without_test_values: list<int>}  $sample
     */
    public function __construct(
        public string $accessKey,
        public int $number,
        public int $series,
        public CarbonImmutable $issuedAt,
        public string $emitterCnpj,
        public ?int $emitterCrt,
        public ?string $recipientCnpj,
        public NfeRecipientKind $recipientKind,
        public int $totalCents,
        public ?string $system,
        public int $items,
        public int $itemsMissingGroup,
        public int $itemsMissingCodes,
        public int $itemsWithoutTestValues,
        public array $sample,
    ) {}
}
