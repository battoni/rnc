<?php

namespace App\Modules\Fiscal\DTOs;

use App\Modules\Fiscal\Enums\NfeRefusal;

/**
 * One file a source hands the importer: its base name and its bytes, or — when the source already
 * knows it will not be read (over 1 MB) — no bytes and the refusal.
 */
final readonly class NfeFile
{
    public function __construct(
        public string $name,
        public ?string $content,
        public ?NfeRefusal $refusal = null,
    ) {}
}
