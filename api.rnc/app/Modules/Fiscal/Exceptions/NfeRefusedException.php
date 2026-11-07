<?php

namespace App\Modules\Fiscal\Exceptions;

use App\Modules\Fiscal\Enums\NfeRefusal;
use RuntimeException;

/**
 * One file the parser will not store, with its refusal code. Internal to an import: `NfeImporter`
 * catches it and writes the code into the batch, so it never reaches HTTP and never renders. The
 * message is the code itself — never the file's content, so nothing an entity or a payload put in the
 * bytes can travel through it.
 */
class NfeRefusedException extends RuntimeException
{
    public function __construct(public readonly NfeRefusal $refusal)
    {
        parent::__construct($refusal->value);
    }
}
