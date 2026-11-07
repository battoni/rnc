<?php

namespace App\Modules\Channel\Enums;

/**
 * Where an RNC firm stands in Franco's channel (rnc-3 R6, R7; the demo's `RNC_STAGES`): contato →
 * demo → implantacao → ativo, one step at a time and forward only. `ativo` is terminal: the firm
 * pays the platform and Franco earns its share.
 */
enum ChannelStage: string
{
    case Contato = 'contato';
    case Demo = 'demo';
    case Implantacao = 'implantacao';
    case Ativo = 'ativo';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $stage) => $stage->value, self::cases());
    }

    /** The one stage this firm may move to, or null once active. */
    public function next(): ?self
    {
        return match ($this) {
            self::Contato => self::Demo,
            self::Demo => self::Implantacao,
            self::Implantacao => self::Ativo,
            self::Ativo => null,
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        $next = $this->next();

        return $next ? [$next] : [];
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
