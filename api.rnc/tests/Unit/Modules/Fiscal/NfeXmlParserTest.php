<?php

use App\Modules\Fiscal\Enums\NfeRecipientKind;
use App\Modules\Fiscal\Enums\NfeRefusal;
use App\Modules\Fiscal\Exceptions\NfeRefusedException;
use App\Modules\Fiscal\Services\NfeXmlParser;
use Tests\Support\NfeFixture;

/*
 * Reading one NF-e XML (rnc-2 R15–R17, A13, A14's XML half; plan AM3): model 55, `nfeProc` or a
 * bare `NFe`, tags read by local name (`facts.md` §5, marked to confirm). Any `<!DOCTYPE` is refused
 * `not_nfe` before the parser sees the bytes, so no entity — external or nested — is ever expanded.
 */
function nfeParse(string $content): mixed
{
    return (new NfeXmlParser)->parse($content);
}

function nfeRefusalOf(string $content): ?NfeRefusal
{
    try {
        nfeParse($content);
    } catch (NfeRefusedException $exception) {
        return $exception->refusal;
    }

    return null;
}

describe('a sale (A13)', function () {
    it('reads the key, the header, the emitter, the recipient and the total', function () {
        $document = nfeParse(NfeFixture::nfe(['number' => 4521, 'series' => 2, 'crt' => 1, 'total' => '15890.07', 'system' => 'Bling 3.1']));

        expect($document->accessKey)->toBe(NfeFixture::key('11222333000181', 4521, 2))
            ->and($document->number)->toBe(4521)
            ->and($document->series)->toBe(2)
            ->and($document->issuedAt->toIso8601String())->toBe('2026-10-15T13:30:00+00:00')
            ->and($document->emitterCnpj)->toBe('11222333000181')
            ->and($document->emitterCrt)->toBe(1)
            ->and($document->recipientCnpj)->toBe('22333444000181')
            ->and($document->recipientKind)->toBe(NfeRecipientKind::Cnpj)
            ->and($document->totalCents)->toBe(1_589_007)
            ->and($document->system)->toBe('Bling 3.1');
    });

    it('reads a bare NFe as well as an nfeProc', function () {
        expect(nfeParse(NfeFixture::nfe(['wrapped' => false]))->accessKey)->toBe(NfeFixture::key('11222333000181', 123));
    });

    it('reads a CPF recipient or none as consumidor', function () {
        $toPerson = nfeParse(NfeFixture::nfe(['recipient_cpf' => '99900000191']));
        $toNobody = nfeParse(NfeFixture::nfe(['recipient' => null]));

        expect($toPerson->recipientKind)->toBe(NfeRecipientKind::Consumidor)
            ->and($toPerson->recipientCnpj)->toBeNull()
            ->and($toNobody->recipientKind)->toBe(NfeRecipientKind::Consumidor)
            ->and($toNobody->recipientCnpj)->toBeNull();
    });

    it('keeps a missing CRT or system as null instead of failing', function () {
        $document = nfeParse(NfeFixture::nfe(['crt' => null, 'system' => null]));

        expect($document->emitterCrt)->toBeNull()
            ->and($document->system)->toBeNull();
    });
});

describe('the item checks (R16)', function () {
    it('counts each item under the first check it fails, with up to five item numbers each', function () {
        $items = [
            [],
            ['group' => false],
            ['cst' => ''],
            ['class' => '  '],
            ['cbs' => '8.8000'],
            ['ibs_state' => '0.0500', 'ibs_city' => '0.0500'],
            ['ibs_state' => '17.7000'],
            ['group' => false],
        ];

        $document = nfeParse(NfeFixture::nfe(['items' => $items]));

        expect($document->items)->toBe(8)
            ->and($document->itemsMissingGroup)->toBe(2)
            ->and($document->itemsMissingCodes)->toBe(2)
            ->and($document->itemsWithoutTestValues)->toBe(2)
            ->and($document->sample)->toBe([
                'missing_group' => [2, 8],
                'missing_codes' => [3, 4],
                'without_test_values' => [5, 7],
            ]);
    });

    it('reads the CST inside the IBS/CBS group only, not the ICMS one', function () {
        expect(nfeParse(NfeFixture::nfe(['items' => [['cst' => '']]]))->itemsMissingCodes)->toBe(1);
    });

    it('caps each sample at five item numbers', function () {
        $document = nfeParse(NfeFixture::nfe(['items' => array_fill(0, 7, ['group' => false])]));

        expect($document->itemsMissingGroup)->toBe(7)
            ->and($document->sample['missing_group'])->toBe([1, 2, 3, 4, 5]);
    });
});

describe('what is refused', function () {
    it('refuses what is not XML as not_xml', function (string $content) {
        expect(nfeRefusalOf($content))->toBe(NfeRefusal::NotXml);
    })->with([
        'empty' => [''],
        'text' => ['nota fiscal 123'],
        'broken xml' => ['<?xml version="1.0"?><nfeProc><NFe>'],
        'not utf-8' => ["<?xml version=\"1.0\"?><a>\xff\xfe</a>"],
        'a NUL byte' => ["<a>\0</a>"],
    ]);

    it('refuses XML that is not a model-55 NF-e as not_nfe', function (string $content) {
        expect(nfeRefusalOf($content))->toBe(NfeRefusal::NotNfe);
    })->with([
        'another document' => ['<?xml version="1.0"?><cteProc><CTe/></cteProc>'],
        'an NFC-e (model 65)' => [fn () => NfeFixture::nfe(['model' => '65'])],
        'no items' => [fn () => NfeFixture::nfe(['items' => []])],
        'no total' => [fn () => str_replace('<vNF>1234.56</vNF>', '', NfeFixture::nfe())],
        'a CPF emitter' => [fn () => NfeFixture::nfe(['emitter_cpf' => '99900000191'])],
    ]);

    it('refuses a bad check digit as invalid_key (A13)', function () {
        $key = NfeFixture::key('11222333000181', 123);

        expect(nfeRefusalOf(NfeFixture::nfe(['key' => NfeFixture::brokenKey($key)])))->toBe(NfeRefusal::InvalidKey);
    });

    it('refuses a key whose emitter is not the document\'s as invalid_key', function () {
        expect(nfeRefusalOf(NfeFixture::nfe(['key' => NfeFixture::key('22333444000181', 123)])))->toBe(NfeRefusal::InvalidKey);
    });

    it('refuses a file over 1 MB as too_large without reading it', function () {
        $padded = str_replace('</nfeProc>', str_repeat(' ', 1_048_576).'</nfeProc>', NfeFixture::nfe());

        expect(nfeRefusalOf($padded))->toBe(NfeRefusal::TooLarge);
    });
});

describe('external entities (A14, plan AM3)', function () {
    it('refuses the file:///etc/hostname entity as not_nfe', function () {
        expect(nfeRefusalOf(NfeFixture::withExternalEntity('file:///etc/hostname')))->toBe(NfeRefusal::NotNfe);
    });

    it('never reads the entity\'s target: its content is in no answer or message', function () {
        $target = tempnam(sys_get_temp_dir(), 'nfe-xxe');
        file_put_contents($target, 'CANARY-XXE-7f3a91');

        try {
            nfeParse(NfeFixture::withExternalEntity('file://'.$target));
            $outcome = 'stored';
        } catch (NfeRefusedException $exception) {
            $outcome = $exception->refusal->value.' '.$exception->getMessage();
        } finally {
            unlink($target);
        }

        expect($outcome)->toStartWith('not_nfe')
            ->and($outcome)->not->toContain('CANARY-XXE-7f3a91');
    });

    it('refuses the billion-laughs file as not_nfe', function () {
        expect(nfeRefusalOf(NfeFixture::billionLaughs()))->toBe(NfeRefusal::NotNfe);
    });

    it('refuses a DOCTYPE in any case', function () {
        $lower = str_replace('<!DOCTYPE', '<!doctype', NfeFixture::withExternalEntity('file:///etc/hostname'));

        expect(nfeRefusalOf($lower))->toBe(NfeRefusal::NotNfe);
    });

    it('refuses a UTF-16 file as not_xml, where the DOCTYPE would hide from a byte search', function (string $byteOrderMark) {
        $utf16 = $byteOrderMark.mb_convert_encoding(str_replace('encoding="UTF-8"', 'encoding="UTF-16"', NfeFixture::withExternalEntity('file:///etc/hostname')), 'UTF-16LE', 'UTF-8');

        expect(stripos($utf16, '<!DOCTYPE'))->toBeFalse()
            ->and(nfeRefusalOf($utf16))->toBe(NfeRefusal::NotXml);
    })->with([
        'with a byte-order mark' => ["\xFF\xFE"],
        'without one (every byte is also valid UTF-8)' => [''],
    ]);
});
