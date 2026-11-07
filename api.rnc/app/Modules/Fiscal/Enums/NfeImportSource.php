<?php

namespace App\Modules\Fiscal\Enums;

/** How a batch came in (R15): the screen's upload, or `php artisan nfe:import <folder>` (F6b). */
enum NfeImportSource: string
{
    case Upload = 'upload';
    case Folder = 'folder';
}
