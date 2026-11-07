<?php

namespace Database\Seeders\Support;

use App\Modules\Fiscal\Helpers\AccessKey;

/**
 * Valid, invented NF-e XMLs (rnc-2 F6b): the one builder the Fiscal tests and `NfeSeeder` share, so
 * a seeded document and a tested one have the same shape. Nothing it writes was ever issued: the
 * CNPJs and CPFs come from `inventedCnpj`/`inventedCpf` (roots starting `99`, a pattern a reader spots
 * at once) or from the tests' own numbers. The layout follows `facts.md` §5 (marked to confirm):
 * `nfeProc` > `NFe` > `infNFe Id="NFe<key>"` with `ide`, `emit`, `dest`, `det`, `total`.
 *
 * An item is `['group' => bool, 'cst' => '000', 'class' => '000001', 'cbs' => '0.9000',
 * 'ibs_state' => '0.1000', 'ibs_city' => '0.0000']`; the defaults are a complete 2026 item.
 */
final class NfeXmlBuilder
{
    public const NAMESPACE = 'http://www.portalfiscal.inf.br/nfe';

    public const COMPLETE_ITEM = [
        'group' => true,
        'cst' => '000',
        'class' => '000001',
        'cbs' => '0.9000',
        'ibs_state' => '0.1000',
        'ibs_city' => '0.0000',
    ];

    private const STATE_CODE = '35';

    private const CNPJ_WEIGHTS = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];

    /**
     * @param  array{emitter?: string, crt?: int|null, recipient?: string|null, recipient_cpf?: string|null, number?: int, series?: int, issued?: string, system?: string|null, total?: string, model?: string, items?: list<array<string, mixed>>, key?: string, wrapped?: bool, emitter_cpf?: string}  $options
     */
    public static function nfe(array $options = []): string
    {
        $emitter = $options['emitter'] ?? '11222333000181';
        $number = $options['number'] ?? 123;
        $series = $options['series'] ?? 1;
        $issued = $options['issued'] ?? '2026-10-15T10:30:00-03:00';
        $model = $options['model'] ?? '55';
        $key = $options['key'] ?? self::key($emitter, $number, $series, $issued, $model);
        $items = $options['items'] ?? [self::COMPLETE_ITEM];
        $total = $options['total'] ?? '1234.56';

        $crt = array_key_exists('crt', $options) ? $options['crt'] : 3;
        $crtTag = $crt === null ? '' : "<CRT>{$crt}</CRT>";
        $emitterDocument = isset($options['emitter_cpf']) ? "<CPF>{$options['emitter_cpf']}</CPF>" : "<CNPJ>{$emitter}</CNPJ>";
        $system = array_key_exists('system', $options) ? $options['system'] : 'ERP Exemplo 4.2';
        $systemTag = $system === null ? '' : "<verProc>{$system}</verProc>";

        $recipient = array_key_exists('recipient', $options) ? $options['recipient'] : '22333444000181';
        $recipientCpf = $options['recipient_cpf'] ?? null;
        $recipientTag = match (true) {
            $recipientCpf !== null => "<dest><CPF>{$recipientCpf}</CPF><xNome>Consumidor exemplo</xNome></dest>",
            $recipient !== null => "<dest><CNPJ>{$recipient}</CNPJ><xNome>Destinatario exemplo Ltda</xNome></dest>",
            default => '',
        };

        $details = implode('', array_map(
            fn (array $item, int $index) => self::item($index + 1, array_merge(self::COMPLETE_ITEM, $item)),
            $items,
            array_keys($items),
        ));

        $nfe = '<NFe xmlns="'.self::NAMESPACE.'"><infNFe versao="4.00" Id="NFe'.$key.'">'
            .'<ide><cUF>'.self::STATE_CODE.'</cUF><mod>'.$model.'</mod><serie>'.$series.'</serie><nNF>'.$number.'</nNF>'
            .'<dhEmi>'.$issued.'</dhEmi><tpNF>1</tpNF><tpEmis>1</tpEmis>'.$systemTag.'</ide>'
            .'<emit>'.$emitterDocument.'<xNome>Emitente exemplo Ltda</xNome>'.$crtTag.'</emit>'
            .$recipientTag
            .$details
            .'<total><ICMSTot><vProd>'.$total.'</vProd><vNF>'.$total.'</vNF></ICMSTot></total>'
            .'</infNFe></NFe>';

        if (($options['wrapped'] ?? true) === false) {
            return '<?xml version="1.0" encoding="UTF-8"?>'.$nfe;
        }

        return '<?xml version="1.0" encoding="UTF-8"?><nfeProc xmlns="'.self::NAMESPACE.'" versao="4.00">'.$nfe
            .'<protNFe versao="4.00"><infProt><chNFe>'.$key.'</chNFe><cStat>100</cStat></infProt></protNFe></nfeProc>';
    }

    /** The 44-digit access key of a document, with a valid check digit (`facts.md` §5). */
    public static function key(string $emitter, int $number, int $series = 1, string $issued = '2026-10-15T10:30:00-03:00', string $model = '55'): string
    {
        $yearMonth = substr($issued, 2, 2).substr($issued, 5, 2);
        $first43 = self::STATE_CODE.$yearMonth.$emitter.$model
            .str_pad((string) $series, 3, '0', STR_PAD_LEFT)
            .str_pad((string) $number, 9, '0', STR_PAD_LEFT)
            .'1'
            .str_pad((string) $number, 8, '0', STR_PAD_LEFT);

        return $first43.AccessKey::checkDigit($first43);
    }

    /** A plainly invented CNPJ: the 3-digit `$root` + `$number` on five digits, branch `0001`, check digits. */
    public static function inventedCnpj(string $root, int $number): string
    {
        $base = $root.str_pad((string) $number, 5, '0', STR_PAD_LEFT).'0001';

        foreach (self::CNPJ_WEIGHTS as $weights) {
            $sum = 0;

            foreach ($weights as $position => $weight) {
                $sum += (int) $base[$position] * $weight;
            }

            $rest = $sum % 11;
            $base .= $rest < 2 ? '0' : (string) (11 - $rest);
        }

        return $base;
    }

    /** A plainly invented CPF: root `999` + `$number` on six digits, and the two check digits. */
    public static function inventedCpf(int $number): string
    {
        $base = '999'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);

        for ($position = 9; $position < 11; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $base[$index] * (($position + 1) - $index);
            }

            $base .= (string) (((10 * $sum) % 11) % 10);
        }

        return $base;
    }

    /** @param  array<string, mixed>  $item */
    private static function item(int $number, array $item): string
    {
        $group = '';

        if ($item['group'] === true) {
            $group = '<IBSCBS><CST>'.$item['cst'].'</CST><cClassTrib>'.$item['class'].'</cClassTrib>'
                .'<gIBSCBS><vBC>100.00</vBC>'
                .'<gIBSUF><pIBSUF>'.$item['ibs_state'].'</pIBSUF><vIBSUF>0.10</vIBSUF></gIBSUF>'
                .'<gIBSMun><pIBSMun>'.$item['ibs_city'].'</pIBSMun><vIBSMun>0.00</vIBSMun></gIBSMun>'
                .'<vIBS>0.10</vIBS>'
                .'<gCBS><pCBS>'.$item['cbs'].'</pCBS><vCBS>0.90</vCBS></gCBS>'
                .'</gIBSCBS></IBSCBS>';
        }

        return '<det nItem="'.$number.'"><prod><cProd>'.$number.'</cProd><xProd>Item exemplo '.$number.'</xProd>'
            .'<NCM>00000000</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>1.0000</qCom><vUnCom>100.00</vUnCom><vProd>100.00</vProd></prod>'
            .'<imposto><ICMS><ICMS00><orig>0</orig><CST>00</CST></ICMS00></ICMS>'.$group.'</imposto></det>';
    }
}
