<?php

namespace App\Modules\Tip\Enums;

enum TipEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Restored = 'restored';
    case Archived = 'archived';
    case Unarchived = 'unarchived';
}
