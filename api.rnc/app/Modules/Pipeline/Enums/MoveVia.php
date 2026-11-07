<?php

namespace App\Modules\Pipeline\Enums;

/** How a card was moved (rnc-1 R18) — kept on the timeline line: the ◀ ▶ buttons, a drag, the lead detail. */
enum MoveVia: string
{
    case Button = 'botao';
    case Drag = 'arrastar';
    case Detail = 'ficha';
}
