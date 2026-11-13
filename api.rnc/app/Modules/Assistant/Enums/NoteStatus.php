<?php

namespace App\Modules\Assistant\Enums;

/**
 * A note's life (rnc-3 R10, R11, D7): written as rascunho, revisada by a second specialist, back to
 * rascunho on a content change, aposentada for good. A revisada note can be reviewed again (that is
 * how an expired review is renewed); an aposentada one moves nowhere (W55).
 */
enum NoteStatus: string
{
    case Rascunho = 'rascunho';
    case Revisada = 'revisada';
    case Aposentada = 'aposentada';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Rascunho => [self::Revisada, self::Aposentada],
            self::Revisada => [self::Revisada, self::Rascunho, self::Aposentada],
            self::Aposentada => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
