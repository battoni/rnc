<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Assistant\Enums\NoteStatus;
use App\Modules\Assistant\Enums\QuestionStatus;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Assistant\Models\AssistantQuestion;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Franco's Reforma knowledge base (rnc-3 R10, R29; AM2, AM10, AM19, W12), outside production only:
 * the demo's ten notes (`demos/franco-reforma-hub/src/views/fiscal.js` 408–509), word for word, each
 * citing the `specs/rnc-3-reforma-hub-phase-3/facts.md` §2 lines it restates — the source and the
 * day it was read (05/11/2026, facts.md line 3) are taken from there.
 *
 * - Reviewers are the demo's invented specialists (sp1 Mariana, sp2 Carlos, sp3 Juliana, sp4
 *   Rafael); `reviewed_at` is relative to now — the demo's 27–31/10 become 5 to 1 days back — so a
 *   fresh seed is always inside the 90 days (AM19). Reinaldo wrote them all (`last_edited_by`), so
 *   every review is four-eyes.
 * - `credito-simples` is a rascunho: §2 line 138 marks that rule unverified (W12).
 * - Two open questions from Patrícia, to Mariana and to Carlos (AM2), no bell (a seed rings nobody).
 *
 * Every person is looked up among the seeded staff (`LocalUsersSeeder`); one missing stops the seed
 * rather than writing a note nobody reviewed (AM10). Runs inside `CurrentFirm::runAs` and writes
 * `firm_id` itself (`DatabaseSeeder` runs without model events). Re-running rewrites the same rows.
 */
class AssistantNoteSeeder extends Seeder
{
    private const AS_OF = '2026-11-05';

    private const EDITOR = 'reinaldo';

    private const BARBIERI = ['Barbieri Advogados — LC 214/2025 comentada', 'https://www.barbieriadvogados.com/lc-214/'];

    private const PLANALTO = ['Planalto — Lei Complementar nº 214/2025', 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm'];

    private const FAZENDA = ['Ministério da Fazenda — Reforma Tributária', 'https://www.gov.br/fazenda/pt-br/acesso-a-informacao/acoes-e-programas/reforma-tributaria'];

    private const JETTAX = ['Jettax — Simples Nacional e MEI: IBS e CBS na nota fiscal em 2026', 'https://www.jettax.com.br/blog/simples-nacional-e-mei-ibs-e-cbs-na-nota-fiscal-em-2026-entenda-as-regras/'];

    private const TAXCEL = ['Taxcel — IBS/CBS na nota fiscal em 2026 e a rejeição', 'https://taxcel.com.br/ibs-cbs-nota-fiscal-2026-obrigatorio-rejeicao'];

    /**
     * slug, question, answer, rule, source, reviewer's username (null: rascunho), days since the
     * review, strong keys, weak keys. The comment above each names the facts.md §2 lines it restates.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: array{0: string, 1: string}, 5: string|null, 6: int, 7: list<string>, 8: list<string>}>
     */
    private const NOTES = [
        // facts.md 101–106 (Timeline 2026, 2027) and 114 (the 2028 postponement request, unverified).
        ['cbs', 'Quando a CBS começa a ser cobrada?', 'Em 2026 é o ano de teste: CBS de 0,9% e IBS de 0,1% destacados na nota, compensáveis com PIS/Cofins, e quem cumpre as obrigações acessórias fica dispensado de recolher. Em 2027 a CBS passa a ser cobrada de verdade e PIS/Cofins acabam. Há pedido de entidades para adiar a cobrança para 2028; até aqui, o calendário segue.', 'LC 214/2025, arts. 343 a 349 (transição)', self::BARBIERI, 'mariana.toledo', 2, ['cbs'], ['cobr', 'comec', 'quando', '2027', 'pis', 'cofins']],
        // facts.md 107–110 (Timeline 2029–2033; ICMS benefits compensated until 2032).
        ['icms', 'Como fica o ICMS e o ISS até 2033?', 'Seguem como hoje até 2028. De 2029 a 2032 caem para 9/10, 8/10, 7/10 e 6/10 das alíquotas atuais, enquanto o IBS sobe. Em 2033 ICMS e ISS deixam de existir. Benefícios de ICMS são compensados até 2032.', 'EC 132/2023; LC 214/2025, arts. 343 a 349', self::BARBIERI, 'carlos.prado', 5, ['icms', 'iss'], ['2033', 'transic', 'extin', 'benefic']],
        // facts.md 134–137 (Simples Nacional: kept, the regular-regime option, the 2027 invoice obligation).
        ['simples', 'O que muda para cliente do Simples Nacional?', 'O Simples continua. A empresa do Simples pode optar por recolher IBS e CBS pelo regime regular, fora do DAS, o que interessa quando os clientes dela são empresas que querem crédito. Em 2026 o Simples está dispensado de destacar IBS/CBS na nota; a obrigação começa em janeiro de 2027, mas o código do regime já precisa estar certo em 2026.', 'EC 132/2023; LC 214/2025 (Simples Nacional)', self::JETTAX, 'juliana.reis', 3, ['simples'], ['nacional', 'muda', 'optar', 'regular']],
        // facts.md 124 (credit only on tax effectively paid) and 138 — "Unverified": seeded as a rascunho (W12).
        ['credito-simples', 'Comprar de fornecedor do Simples dá crédito?', 'Dá, mas só sobre o IBS/CBS que o fornecedor efetivamente recolhe dentro do DAS, bem menos que a alíquota cheia. Por isso a tela Crédito de fornecedores estima o que cada cliente deixa na mesa. Confirmar caso a caso antes de recomendar a troca de fornecedor.', 'LC 214/2025 (crédito financeiro; aquisições do Simples Nacional)', self::PLANALTO, null, 0, ['credit', 'fornecedor'], ['simples', 'compr', 'perd']],
        // facts.md 123–126 (split payment: arts. 31–33, LC 227; tax taken at settlement).
        ['split', 'Como funciona o split payment?', 'Na liquidação do pagamento, o banco ou a credenciadora separa o IBS/CBS e manda direto ao fisco; a empresa recebe o valor líquido. O método padrão se liga à nota eletrônica; o simplificado, por percentual fixo, ficou opcional depois da LC 227/2026. Na prática, o imposto sai do caixa no recebimento, não no vencimento da guia.', 'LC 214/2025, arts. 31 a 33 (alterada pela LC 227/2026)', self::PLANALTO, 'rafael.antunes', 1, ['split', 'payment'], ['pagamento', 'dividido', 'caixa', 'liquida', 'retenc']],
        // facts.md 140–144 (NT 2025.002, mandatory since 03/08/2026, rejection 1115 postponed).
        ['rejeicao', 'O que é a rejeição 1115 da NF-e?', 'É a rejeição da nota emitida sem o grupo do IBS/CBS criado pela NT 2025.002. O preenchimento é obrigatório nos fatos tributáveis desde 03/08/2026; a rejeição foi adiada e segue ativa só em homologação. Quando entrar em produção, a nota sem o grupo volta. A tela Prontidão da NF-e mostra quem está exposto.', 'NT 2025.002 v1.52; Ato Conjunto RFB/CGIBS nº 4/2026', self::TAXCEL, 'carlos.prado', 5, ['1115', 'rejei'], ['nota', 'nfe', 'grupo', 'preench']],
        // facts.md 116–118 (zero rate and the 60 % reduction).
        ['reducao-60', 'Quem tem a redução de 60%?', 'Saúde, educação, alimentos, produtos de higiene e limpeza, produtos e insumos agropecuários, produção cultural, esporte, medicamentos e dispositivos médicos e de acessibilidade. É redução das alíquotas de IBS e CBS, não isenção; a cesta básica e alguns medicamentos têm alíquota zero.', 'LC 214/2025, regimes diferenciados (redução de 60% e alíquota zero)', self::BARBIERI, 'mariana.toledo', 4, ['60'], ['reduc', 'saude', 'educac', 'aliment', 'agro', 'zero', 'cesta']],
        // facts.md 119–121 (the 30 % reduction, art. 127, and its conditions).
        ['reducao-30', 'Escritório de profissão regulamentada tem redução?', 'Pode ter 30% de redução: vale para 18 profissões intelectuais regulamentadas (advogados, contadores, arquitetos, engenheiros, administradores, economistas, veterinários e outras). Exige sócios habilitados, nenhum sócio pessoa jurídica e o serviço prestado pessoalmente pelos sócios. Médicos ficam no grupo de 60%. Verificar a elegibilidade, nunca presumir.', 'LC 214/2025, art. 127', self::BARBIERI, 'rafael.antunes', 1, ['30', 'profiss', 'regulament'], ['advog', 'contador', 'engenh', 'arquitet', 'escritori', 'reduc']],
        // facts.md 111–112 (TCU's 11,3 % for 2027; the ~26,5 % estimate and art. 475 §§ 10–11).
        ['aliquota', 'Qual a alíquota de referência do IBS e da CBS?', 'A estimativa é de cerca de 26,5% somando IBS e CBS. Se a projeção para 2033 passar disso, o Executivo precisa propor reduções. Para 2027, o TCU calculou a alíquota de referência da CBS em 11,3% (28/10/2026); o Senado fixa por resolução.', 'LC 214/2025, art. 475, §§ 10 e 11', self::BARBIERI, 'mariana.toledo', 2, ['aliquot'], ['referenc', 'ibs', 'cbs', '26']],
        // facts.md 113 (cashback from 2027 CBS / 2029 IBS) and 128–132 (who and how much).
        ['cashback', 'Como funciona o cashback?', 'Devolução de IBS/CBS a famílias do CadÚnico com renda por pessoa de até meio salário mínimo: pelo menos 100% da CBS e 20% do IBS em botijão de gás, energia, água, esgoto, gás canalizado e telecom, e pelo menos 20% dos dois no resto. Começa em janeiro de 2027 para a CBS e em 2029 para o IBS. Para o cliente, é tema de preço de quem vende ao consumidor final.', 'LC 214/2025, arts. 112 a 124', self::FAZENDA, 'juliana.reis', 4, ['cashback', 'cadunico'], ['devoluc', 'familia', 'consumidor']],
    ];

    /** @var list<array{0: string, 1: string, 2: string}> asker, specialist, question — none a served note answers */
    private const QUESTIONS = [
        ['patricia', 'mariana.toledo', 'A exportação de serviços mantém o crédito?'],
        ['patricia', 'carlos.prado', 'Qual ERP já emite a nota com os campos novos?'],
    ];

    public function run(CurrentFirm $currentFirm): void
    {
        if (app()->environment('production')) {
            return;
        }

        $firm = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->first();

        if (! $firm) {
            return;
        }

        $now = CarbonImmutable::now();

        $currentFirm->runAs($firm, function () use ($firm, $now) {
            $editorId = $this->staffId($firm, self::EDITOR);

            foreach (self::NOTES as [$slug, $question, $answer, $rule, [$sourceLabel, $sourceUrl], $reviewer, $days, $strong, $weak]) {
                $row = AssistantNote::query()->firstOrNew(['slug' => $slug]);
                $row->forceFill([
                    'firm_id' => $firm->id,
                    'slug' => $slug,
                    'question' => $question,
                    'answer' => $answer,
                    'rule' => $rule,
                    'source_label' => $sourceLabel,
                    'source_url' => $sourceUrl,
                    'as_of' => self::AS_OF,
                    'keywords_strong' => $strong,
                    'keywords_weak' => $weak,
                    'status' => $reviewer === null ? NoteStatus::Rascunho->value : NoteStatus::Revisada->value,
                    'reviewer_id' => $reviewer === null ? null : $this->staffId($firm, $reviewer),
                    'reviewed_at' => $reviewer === null ? null : $now->subDays($days),
                    'last_edited_by' => $editorId,
                ])->save();
            }

            foreach (self::QUESTIONS as [$asker, $specialist, $question]) {
                $row = AssistantQuestion::query()->firstOrNew(['question' => $question]);
                $row->forceFill([
                    'firm_id' => $firm->id,
                    'asked_by' => $this->staffId($firm, $asker),
                    'specialist_id' => $this->staffId($firm, $specialist),
                    'question' => $question,
                    'status' => QuestionStatus::Aberta->value,
                    'answer' => null,
                    'answered_at' => null,
                ])->save();
            }
        });
    }

    /** A seeded staff member of the firm, or the seed stops: no note names anybody else (AM10). */
    private function staffId(Firm $firm, string $username): int
    {
        $id = User::where('firm_id', $firm->id)->where('username', $username)->value('id');

        if ($id === null) {
            throw new LogicException("AssistantNoteSeeder names [{$username}], who is not on Franco's seeded staff.");
        }

        return (int) $id;
    }
}
