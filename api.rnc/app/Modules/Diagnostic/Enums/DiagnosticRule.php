<?php

namespace App\Modules\Diagnostic\Enums;

use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;

/**
 * The Reforma rules (rnc-1 R12): the demo's `diagnose()` table, ported line by line. Each case cites
 * its line of the facts.md §2 statement table (`citation()`), applies to a profile (`appliesTo()`)
 * and becomes an R$ range as a share of the band's revenue (`share()`): always a range, always an
 * estimate. The order of the cases is the order of the result.
 *
 * `detail()` and `citation()` are for the team (the lead detail, F5) and are never sent by a public
 * route: the detail is the reason for the meeting (R13).
 */
enum DiagnosticRule: string
{
    private const B2B_THRESHOLD = 50;

    case SimplesRegular = 'simples-regular';
    case SimplesCredito = 'simples-credito';
    case SimplesB2c = 'simples-b2c';
    case ErpNfe = 'erp-nfe';
    case PisCofinsFim = 'pis-cofins-fim';
    case Servicos30 = 'servicos-30';
    case Reducao60 = 'reducao-60';
    case Agro60 = 'agro-60';
    case AgroInNatura = 'agro-in-natura';
    case IcmsIncentivos = 'icms-incentivos';
    case Exportacao = 'exportacao';
    case Contratos = 'contratos';
    case SplitPayment = 'split-payment';

    public function appliesTo(DiagnosticProfileDTO $profile): bool
    {
        $regime = Regime::tryFrom((string) $profile->regime);
        $sector = Sector::tryFrom((string) $profile->sector);
        // An unanswered share counts as B2C, as the demo's `null < 50` did.
        $sellsToCompanies = ($profile->b2b ?? 0) >= self::B2B_THRESHOLD;
        $isRegularRegime = $regime === Regime::Presumido || $regime === Regime::Real;

        return match ($this) {
            self::SimplesRegular, self::SimplesCredito => $regime === Regime::Simples && $sellsToCompanies,
            self::SimplesB2c => $regime === Regime::Simples && ! $sellsToCompanies,
            self::ErpNfe, self::PisCofinsFim => $isRegularRegime,
            self::Servicos30 => $sector === Sector::Servicos,
            self::Reducao60 => $sector?->hasReducedRate() ?? false,
            self::Agro60, self::AgroInNatura => $sector === Sector::Agro,
            self::IcmsIncentivos => $profile->incentivos,
            self::Exportacao => $profile->exporta,
            self::Contratos, self::SplitPayment => true,
        };
    }

    public function kind(): DiagnosticKind
    {
        return match ($this) {
            self::SimplesRegular, self::PisCofinsFim, self::Servicos30, self::Reducao60, self::Agro60,
            self::Exportacao, self::Contratos => DiagnosticKind::Opportunity,
            self::SimplesCredito, self::SimplesB2c, self::ErpNfe, self::AgroInNatura, self::IcmsIncentivos,
            self::SplitPayment => DiagnosticKind::Risk,
        };
    }

    /** @return array{0: float, 1: float} the share of the band's revenue, low and high */
    public function share(): array
    {
        return match ($this) {
            self::SimplesRegular => [0.01, 0.03],
            self::SimplesCredito => [0.02, 0.05],
            self::SimplesB2c => [0.001, 0.004],
            self::ErpNfe => [0.002, 0.006],
            self::PisCofinsFim => [0.005, 0.015],
            self::Servicos30 => [0.01, 0.025],
            self::Reducao60 => [0.02, 0.05],
            self::Agro60 => [0.015, 0.04],
            self::AgroInNatura => [0.003, 0.01],
            self::IcmsIncentivos => [0.01, 0.03],
            self::Exportacao => [0.01, 0.03],
            self::Contratos => [0.005, 0.015],
            self::SplitPayment => [0.008, 0.02],
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::SimplesRegular => 'Opção pelo regime regular do IBS/CBS',
            self::SimplesCredito => 'Clientes PJ podem preferir fornecedores que geram crédito',
            self::SimplesB2c => 'Nova obrigação de nota com IBS/CBS em 2027',
            self::ErpNfe => 'NF-e com campos de IBS/CBS já obrigatórios',
            self::PisCofinsFim => 'Transição de PIS/Cofins para a CBS em 2027',
            self::Servicos30 => 'Redução de 30% para profissão regulamentada',
            self::Reducao60 => 'Redução de 60% na alíquota de IBS/CBS',
            self::Agro60 => 'Itens com alíquota reduzida em 60% ou zerada',
            self::AgroInNatura => 'Regras de suspensão e crédito na venda in natura',
            self::IcmsIncentivos => 'Fim gradual dos benefícios de ICMS até 2032',
            self::Exportacao => 'Exportação desonerada com crédito mantido',
            self::Contratos => 'Revisar contratos e preços antes de 2027',
            self::SplitPayment => 'Fluxo de caixa exposto ao split payment',
        };
    }

    /** The line of the facts.md §2 statement table the item rests on. */
    public function citation(): string
    {
        return match ($this) {
            self::SimplesRegular => 'Simples com vendas B2B: avaliar a opção pelo regime regular do IBS/CBS (LC 214/2025)',
            self::SimplesCredito => 'Simples com vendas B2B: o cliente pode preferir o fornecedor que passa crédito',
            self::SimplesB2c => 'Simples com vendas B2C: pouca mudança em 2026–27; atenção à obrigação de nota de 2027',
            self::ErpNfe => 'Presumido ou Real: campos de IBS/CBS na NF-e obrigatórios desde 03/08/2026; checar o ERP',
            self::PisCofinsFim => 'Presumido ou Real: PIS/Cofins acabam em 2027 e a CBS entra cheia',
            self::Servicos30 => 'Serviços de profissão regulamentada: pode ter redução de 30% (art. 127); checar a estrutura da empresa',
            self::Reducao60 => 'Saúde ou educação: redução de 60%, que baixa a carga efetiva',
            self::Agro60 => 'Agro ou alimentos: muitos itens com redução de 60% ou alíquota zero',
            self::AgroInNatura => 'Agro ou alimentos: suspensão e regras de crédito na venda in natura',
            self::IcmsIncentivos => 'Comércio com incentivos: benefícios de ICMS compensados até 2032; planejar a saída',
            self::Exportacao => 'Exportadores: exportação não tributada, com manutenção do crédito (LC 214/2025; artigos a confirmar)',
            self::Contratos => 'Todos: contratos longos entre 2027 e 2033 precisam de cláusula de preço',
            self::SplitPayment => 'Todos: o split payment recolhe o imposto na liquidação e mexe no caixa',
        };
    }

    public function detail(): string
    {
        return match ($this) {
            self::SimplesRegular => 'Clientes PJ tomam crédito integral de quem está no regime regular. Optar por ele pode segurar ou ganhar contratos B2B; a conta compara a carga extra com o preço que o cliente aceita pagar.',
            self::SimplesCredito => 'No Simples o crédito transferido é limitado. Um comprador no regime regular paga menos imposto comprando de quem gera crédito cheio: risco de perder pedido para um concorrente.',
            self::SimplesB2c => 'A carga muda pouco no curto prazo, mas a emissão de documentos com os novos campos passa a valer. Sistema de vendas e cadastro de produtos precisam estar prontos.',
            self::ErpNfe => 'Nota rejeitada para faturamento. O ERP precisa destacar CBS e IBS por item, com a classificação certa; vale um teste de emissão com a equipe fiscal.',
            self::PisCofinsFim => 'A CBS tem crédito amplo sobre insumos. Mapear hoje as compras que passam a gerar crédito e os saldos de PIS/Cofins a aproveitar antes da extinção.',
            self::Servicos30 => 'A redução depende de a atividade ser de profissão regulamentada e de como a sociedade está montada. Uma revisão societária pode ser o caminho para ela valer.',
            self::Reducao60 => 'Os serviços enquadrados pagam 40% da alíquota padrão. Conferir quais receitas entram na lista e precificar com a carga nova.',
            self::Agro60 => 'Cesta básica e insumos agropecuários têm tratamento próprio. Classificar o portfólio item a item mostra onde a carga cai.',
            self::AgroInNatura => 'A venda in natura pode ter suspensão do imposto e crédito presumido para o comprador; errar o enquadramento trava crédito na cadeia.',
            self::IcmsIncentivos => 'O incentivo some junto com o ICMS. A compensação federal tem regras e prazos; o planejamento define preço e margem ano a ano até 2033.',
            self::Exportacao => 'Os créditos das compras ligadas à exportação ficam com a empresa. Controlar e pedir o ressarcimento vira caixa.',
            self::Contratos => 'Contrato que atravessa a transição sem cláusula de reequilíbrio fica com o preço errado. Revisar agora evita margem perdida ou disputa com o cliente.',
            self::SplitPayment => 'O imposto sai no momento do pagamento, antes de a empresa usar o dinheiro. O capital de giro precisa cobrir o prazo que antes vinha do recolhimento mensal.',
        };
    }
}
