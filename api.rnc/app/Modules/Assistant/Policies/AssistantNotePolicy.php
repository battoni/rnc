<?php

namespace App\Modules\Assistant\Policies;

use App\Models\User;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Auth\Services\PermissionService;

/**
 * Who writes and who reviews the knowledge base (rnc-3 R10, R11, AM4, W10). The routes already ask
 * for `assistant.ask`; on top of it, reading and writing notes is `assistant.manage` (Reinaldo, the
 * operator) or a specialist, and reviewing one is a specialist who did not write its last change —
 * four eyes, so Reinaldo writes and never reviews (he is not a specialist).
 */
class AssistantNotePolicy
{
    public const MANAGE_KEY = 'assistant.manage';

    public function __construct(private PermissionService $permissionService) {}

    public function write(User $user): bool
    {
        return (bool) $user->is_specialist || in_array(self::MANAGE_KEY, $this->permissionService->forUser($user), true);
    }

    public function review(User $user, AssistantNote $note): bool
    {
        return (bool) $user->is_specialist && $note->last_edited_by !== $user->id;
    }
}
