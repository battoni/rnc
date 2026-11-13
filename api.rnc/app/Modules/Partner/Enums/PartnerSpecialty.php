<?php

namespace App\Modules\Partner\Enums;

use App\Modules\Diagnostic\Enums\DiagnosticRule;

/**
 * The eight specialties around an accounting firm (rnc-1 R21, facts.md §3), and which diagnostic
 * items each one solves — the demo's `PARTNER_RULES`, ported. A partner's match score grows with
 * the items of this lead's diagnostic its specialty covers (`PartnerMatcher`).
 */
enum PartnerSpecialty: string
{
    case AdvocaciaTributaria = 'advocacia_tributaria';
    case RecuperacaoCreditos = 'recuperacao_creditos';
    case ErpNfe = 'erp_nfe';
    case CreditoCapitalGiro = 'credito_capital_giro';
    case SegurosBeneficios = 'seguros_beneficios';
    case SocietarioSucessao = 'societario_sucessao';
    case PrecificacaoControladoria = 'precificacao_controladoria';
    case CertificadoDigital = 'certificado_digital';

    /** @return list<DiagnosticRule> */
    public function covers(): array
    {
        return match ($this) {
            self::AdvocaciaTributaria => [DiagnosticRule::SimplesRegular, DiagnosticRule::IcmsIncentivos, DiagnosticRule::Reducao60, DiagnosticRule::Servicos30, DiagnosticRule::AgroInNatura],
            self::RecuperacaoCreditos => [DiagnosticRule::PisCofinsFim, DiagnosticRule::Exportacao, DiagnosticRule::Agro60],
            self::ErpNfe => [DiagnosticRule::ErpNfe, DiagnosticRule::SimplesB2c],
            self::CreditoCapitalGiro => [DiagnosticRule::SplitPayment, DiagnosticRule::SimplesCredito],
            self::SegurosBeneficios => [],
            self::SocietarioSucessao => [DiagnosticRule::SimplesRegular],
            self::PrecificacaoControladoria => [DiagnosticRule::Contratos, DiagnosticRule::SimplesCredito, DiagnosticRule::Servicos30],
            self::CertificadoDigital => [DiagnosticRule::ErpNfe],
        };
    }

    /** The name the partner's e-mail prints (pt-BR; the SPA has its own copy in the locales). */
    public function label(): string
    {
        return match ($this) {
            self::AdvocaciaTributaria => 'Advocacia tributária',
            self::RecuperacaoCreditos => 'Recuperação de créditos',
            self::ErpNfe => 'ERP/NF-e',
            self::CreditoCapitalGiro => 'Crédito e capital de giro',
            self::SegurosBeneficios => 'Seguros e benefícios',
            self::SocietarioSucessao => 'Societário e sucessão',
            self::PrecificacaoControladoria => 'Precificação e controladoria',
            self::CertificadoDigital => 'Certificado digital',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
