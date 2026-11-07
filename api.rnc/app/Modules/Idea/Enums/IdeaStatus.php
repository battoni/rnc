<?php

namespace App\Modules\Idea\Enums;

/**
 * Where an idea stands. The board's trust lives in this column: a suggestion box that never
 * answers is a suggestion box nobody writes to twice.
 *
 * NOT_NOW exists so the maintainer can decline out loud. It is a real answer and the interface
 * never paints it as an error — the product's own brief asks for a system that does not judge,
 * and a refusal shown in danger red is a judgement of whoever wrote the idea.
 *
 * DELIVERED is the only status that stamps a timestamp (`delivered_at`), because it is the only
 * one Novidades orders by.
 */
enum IdeaStatus: string
{
    case RECEIVED = 'received';
    case UNDER_REVIEW = 'under_review';
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case DELIVERED = 'delivered';
    case NOT_NOW = 'not_now';

    /**
     * An idea is still its author's private draft only while nobody has acted on it. Once it
     * has been triaged, editing the text underneath the maintainer's answer would make that
     * answer describe something that is no longer there.
     */
    public function isAuthorEditable(): bool
    {
        return $this === self::RECEIVED;
    }

    public function isDelivered(): bool
    {
        return $this === self::DELIVERED;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
