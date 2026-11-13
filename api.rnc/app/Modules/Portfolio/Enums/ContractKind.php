<?php

namespace App\Modules\Portfolio\Enums;

use Illuminate\Support\Str;

/** A client contract's kind (rnc-3 R17) — the demo's five (`seed.js` `CONTRACT_KINDS`). */
enum ContractKind: string
{
    case Fornecimento = 'fornecimento';
    case PrestacaoServicos = 'prestacao_servicos';
    case Locacao = 'locacao';
    case Distribuicao = 'distribuicao';
    case Manutencao = 'manutencao';

    /** Words a person writes in a label that the slug leaves out ("Prestação DE serviços"). */
    private const FILLER_WORDS = ['de', 'do', 'da'];

    /** The kind in pt-BR, as Franco and the partner read it (the demo's `CONTRACT_KINDS`). */
    public function label(): string
    {
        return match ($this) {
            self::Fornecimento => 'Fornecimento',
            self::PrestacaoServicos => 'Prestação de serviços',
            self::Locacao => 'Locação',
            self::Distribuicao => 'Distribuição',
            self::Manutencao => 'Manutenção',
        };
    }

    /**
     * The kind a CSV cell names, by its slug or its label in any case and accent ("Prestação de
     * serviços", "PRESTACAO_SERVICOS", "locação"); null when it names none.
     */
    public static function fromInput(string $value): ?self
    {
        $words = preg_split('/[\s_-]+/', Str::lower(Str::ascii(trim($value)))) ?: [];
        $slug = implode('_', array_values(array_diff($words, self::FILLER_WORDS, [''])));

        return self::tryFrom($slug);
    }
}
