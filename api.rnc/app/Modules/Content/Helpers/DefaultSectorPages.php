<?php

namespace App\Modules\Content\Helpers;

/**
 * The six default sector pages of the public site (rnc-1 R6), ported from the approved demo
 * (battonidev-1 `views/site.js` SECTOR_CONTENT) with each dated statement split into its date, its
 * text and its source (facts.md §2), in date order. Every firm starts with them: the seeded firms
 * through `SectorPageSeeder`, a new one through `firm:create` (rnc-4 F3b1, R21) — both by
 * `SectorPageService::startFirm`. They name no firm.
 */
final class DefaultSectorPages
{
    private const LC_214_URL = 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm';

    private const LC_214 = ['label' => 'LC 214/2025', 'url' => self::LC_214_URL];

    private const LC_214_TRANSITION = ['label' => 'LC 214/2025, arts. 343 a 349 (transição)', 'url' => self::LC_214_URL];

    private const LC_214_ICMS_BENEFITS = ['label' => 'LC 214/2025, arts. 384 a 405', 'url' => self::LC_214_URL];

    private const LC_214_REDUCED_RATES = ['label' => 'LC 214/2025, arts. 125 a 143 (reduções e alíquota zero)', 'url' => self::LC_214_URL];

    private const LC_214_ART_127 = ['label' => 'LC 214/2025, art. 127', 'url' => self::LC_214_URL];

    private const NFE_FIELDS = ['label' => 'Ato Conjunto RFB/CGIBS nº 4/2026 e NT 2025.002', 'url' => 'https://taxcel.com.br/ibs-cbs-nota-fiscal-2026-obrigatorio-rejeicao'];

    private const TCU_RATE = ['label' => 'TCU, cálculo da alíquota de referência da CBS (28/10/2026)', 'url' => null];

    private const CBS_2027 = ['date' => '01/01/2027', 'text' => 'A CBS passa a ser cobrada integralmente e PIS/Cofins deixam de existir.', 'source' => self::LC_214_TRANSITION];

    private const TCU_2027 = ['date' => '28/10/2026', 'text' => 'O TCU calculou a alíquota de referência da CBS de 2027 em 11,3%; o Senado fixa por resolução.', 'source' => self::TCU_RATE];

    /** @var list<array{slug: string, label: string, title: string, lede: string, facts: list<array<string, mixed>>, seo: array{title: string, description: string}}> */
    public const PAGES = [
        [
            'slug' => 'comercio',
            'label' => 'Comércio',
            'title' => 'Reforma Tributária para o comércio',
            'lede' => 'Preço, margem e crédito mudam de lugar. Quem compra de você vai olhar o crédito que sua nota gera.',
            'facts' => [
                ['date' => '2026', 'text' => 'Ano-teste com CBS 0,9% e IBS 0,1% destacados na nota fiscal, compensáveis com PIS/Cofins.', 'source' => self::LC_214_TRANSITION],
                self::CBS_2027,
                ['date' => '2029 a 2032', 'text' => 'O ICMS cai para 9/10, 8/10, 7/10 e 6/10; benefícios de ICMS são compensados até 2032.', 'source' => self::LC_214_ICMS_BENEFITS],
                ['date' => '2033', 'text' => 'ICMS extinto, IBS e CBS integrais. Cesta básica com alíquota zero (Anexo I).', 'source' => self::LC_214_REDUCED_RATES],
            ],
            'seo' => [
                'title' => 'Reforma Tributária no comércio: datas, crédito e preço',
                'description' => 'O que a CBS e o IBS mudam no comércio de 2026 a 2033: ano-teste, fim do PIS/Cofins em 2027, queda do ICMS e crédito na nota. Faça o diagnóstico gratuito.',
            ],
        ],
        [
            'slug' => 'industria',
            'label' => 'Indústria',
            'title' => 'Reforma Tributária para a indústria',
            'lede' => 'Crédito amplo sobre insumos, fim gradual dos incentivos de ICMS e a nota fiscal já com os novos campos.',
            'facts' => [
                ['date' => '03/08/2026', 'text' => 'Campos de IBS/CBS na NF-e obrigatórios para quem está no Presumido ou no Real.', 'source' => self::NFE_FIELDS],
                ['date' => '2027', 'text' => 'O Imposto Seletivo começa sobre bens prejudiciais à saúde ou ao meio ambiente.', 'source' => self::LC_214],
                self::CBS_2027,
                ['date' => '2029 a 2032', 'text' => 'Incentivos de ICMS compensados enquanto o imposto cai a 6/10; extinto em 2033.', 'source' => self::LC_214_ICMS_BENEFITS],
            ],
            'seo' => [
                'title' => 'Reforma Tributária na indústria: crédito, NF-e e incentivos',
                'description' => 'Campos de IBS/CBS na NF-e desde 03/08/2026, Imposto Seletivo em 2027 e o fim gradual dos incentivos de ICMS até 2033. Faça o diagnóstico gratuito.',
            ],
        ],
        [
            'slug' => 'servicos',
            'label' => 'Serviços',
            'title' => 'Reforma Tributária para serviços',
            'lede' => 'O ISS sai, o IBS entra, e profissões regulamentadas podem ter redução de 30%: depende de como a empresa está montada.',
            'facts' => [
                ['date' => '2026', 'text' => 'Ano-teste com CBS 0,9% e IBS 0,1%, sem cobrança para quem cumpre as obrigações acessórias.', 'source' => self::LC_214_TRANSITION],
                self::CBS_2027,
                ['date' => '2027', 'text' => 'Redução de 30% para 18 profissões regulamentadas, com sócios habilitados e sem sócio pessoa jurídica.', 'source' => self::LC_214_ART_127],
                ['date' => '2029 a 2032', 'text' => 'O ISS cai para 9/10, 8/10, 7/10 e 6/10; em 2033 deixa de existir.', 'source' => self::LC_214_TRANSITION],
            ],
            'seo' => [
                'title' => 'Reforma Tributária em serviços: ISS, IBS e a redução de 30%',
                'description' => 'Como a troca do ISS pelo IBS e da PIS/Cofins pela CBS afeta empresas de serviços, e quem tem direito à redução de 30%. Faça o diagnóstico gratuito.',
            ],
        ],
        [
            'slug' => 'saude',
            'label' => 'Saúde',
            'title' => 'Reforma Tributária para a saúde',
            'lede' => 'Serviços de saúde têm redução de 60%: a carga pode cair, desde que cada receita esteja bem enquadrada.',
            'facts' => [
                ['date' => '2026', 'text' => 'Ano-teste da CBS e do IBS, compensável com PIS/Cofins.', 'source' => self::LC_214_TRANSITION],
                self::TCU_2027,
                ['date' => '2027', 'text' => 'Redução de 60% da alíquota de IBS/CBS para serviços de saúde; medicamentos listados com alíquota zero.', 'source' => self::LC_214_REDUCED_RATES],
                ['date' => '2033', 'text' => 'ISS e ICMS extintos; a saúde paga 40% da alíquota padrão de IBS/CBS.', 'source' => self::LC_214_REDUCED_RATES],
            ],
            'seo' => [
                'title' => 'Reforma Tributária na saúde: a redução de 60% e o enquadramento',
                'description' => 'Clínicas e serviços de saúde têm redução de 60% no IBS e na CBS a partir de 2027. Veja as datas e faça o diagnóstico gratuito.',
            ],
        ],
        [
            'slug' => 'educacao',
            'label' => 'Educação',
            'title' => 'Reforma Tributária para a educação',
            'lede' => 'Educação também está na lista de 60% de redução. O que muda é a conta de preço da mensalidade.',
            'facts' => [
                ['date' => '2026', 'text' => 'Ano-teste com CBS 0,9% e IBS 0,1% destacados no documento fiscal.', 'source' => self::LC_214_TRANSITION],
                self::TCU_2027,
                ['date' => '2027', 'text' => 'Redução de 60% da alíquota de IBS/CBS para serviços de educação.', 'source' => self::LC_214_REDUCED_RATES],
                ['date' => '2029 a 2032', 'text' => 'O ISS cai ano a ano enquanto o IBS sobe; extinto em 2033.', 'source' => self::LC_214_TRANSITION],
            ],
            'seo' => [
                'title' => 'Reforma Tributária na educação: redução de 60% e a mensalidade',
                'description' => 'Escolas e cursos têm redução de 60% no IBS e na CBS. Veja o que muda de 2026 a 2033 e faça o diagnóstico gratuito.',
            ],
        ],
        [
            'slug' => 'agro',
            'label' => 'Agro',
            'title' => 'Reforma Tributária para o agro',
            'lede' => 'Muitos itens com alíquota zero ou redução de 60%, e regras próprias de suspensão e crédito na venda in natura.',
            'facts' => [
                ['date' => '2026', 'text' => 'Ano-teste da CBS e do IBS para quem não é do Simples.', 'source' => self::LC_214_TRANSITION],
                ['date' => '2027', 'text' => 'Produtos agropecuários e insumos com redução de 60%; cesta básica com alíquota zero.', 'source' => self::LC_214_REDUCED_RATES],
                ['date' => '2027', 'text' => 'Suspensão e crédito presumido na venda in natura passam a valer com a CBS.', 'source' => self::LC_214],
                ['date' => '2033', 'text' => 'ICMS extinto; incentivos de ICMS compensados só até 2032.', 'source' => self::LC_214_ICMS_BENEFITS],
            ],
            'seo' => [
                'title' => 'Reforma Tributária no agro: alíquota zero, redução de 60% e crédito',
                'description' => 'Produtos agropecuários e insumos com redução de 60%, cesta básica com alíquota zero e regras próprias de crédito. Faça o diagnóstico gratuito.',
            ],
        ],
    ];
}
