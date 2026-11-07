<?php

namespace Tests\Support;

use App\Modules\Fiscal\Helpers\AccessKey;

/**
 * Hand-written NF-e XMLs for the Fiscal tests (rnc-2 F6a; plan F6 "F6a's tests may start with
 * hand-written fixtures, F6b moves them onto the builder"). Every document is invented: the CNPJs
 * are test numbers, nothing here was ever issued. The layout follows `facts.md` §5 (marked to
 * confirm): `nfeProc` > `NFe` > `infNFe Id="NFe<key>"` with `ide`, `emit`, `dest`, `det`, `total`.
 *
 * An item is `['group' => bool, 'cst' => '000', 'class' => '000001', 'cbs' => '0.9000',
 * 'ibs_state' => '0.1000', 'ibs_city' => '0.0000']`; the defaults are a complete 2026 item.
 */
final class NfeFixture
{
    public const NAMESPACE = 'http://www.portalfiscal.inf.br/nfe';

    private const STATE_CODE = '35';

    private const COMPLETE_ITEM = [
        'group' => true,
        'cst' => '000',
        'class' => '000001',
        'cbs' => '0.9000',
        'ibs_state' => '0.1000',
        'ibs_city' => '0.0000',
    ];

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

    /** The same key with its check digit changed. */
    public static function brokenKey(string $key): string
    {
        $digit = ((int) substr($key, -1) + 1) % 10;

        return substr($key, 0, 43).$digit;
    }

    /**
     * A document whose recipient name is an external entity pointing at `$target`
     * (`file:///etc/hostname` in plan AM3).
     */
    public static function withExternalEntity(string $target): string
    {
        $document = self::nfe();
        $document = str_replace('Destinatario exemplo Ltda', '&leak;', $document);

        return str_replace(
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE nfeProc [<!ENTITY leak SYSTEM "'.$target.'">]>',
            $document,
        );
    }

    /** The billion-laughs document: ten levels of ten references each. */
    public static function billionLaughs(): string
    {
        $entities = '<!ENTITY lol0 "lol">';

        for ($level = 1; $level <= 9; $level++) {
            $previous = '&lol'.($level - 1).';';
            $entities .= '<!ENTITY lol'.$level.' "'.str_repeat($previous, 10).'">';
        }

        $document = str_replace('Destinatario exemplo Ltda', '&lol9;', self::nfe());

        return str_replace(
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE nfeProc ['.$entities.']>',
            $document,
        );
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
