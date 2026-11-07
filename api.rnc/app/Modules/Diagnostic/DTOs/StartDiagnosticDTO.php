<?php

namespace App\Modules\Diagnostic\DTOs;

use Spatie\LaravelData\Data;

class StartDiagnosticDTO extends Data
{
    public function __construct(
        public string $path,
        public string $source,
        public ?string $visitor = null,
        public ?string $sector = null,
        public ?string $medium = null,
        public string $channel = 'formulario',
    ) {}
}
