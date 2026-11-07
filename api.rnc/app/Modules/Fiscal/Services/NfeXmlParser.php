<?php

namespace App\Modules\Fiscal\Services;

use App\Helpers\Brazil\Cnpj;
use App\Modules\Fiscal\Contracts\NfeDocumentSource;
use App\Modules\Fiscal\DTOs\ParsedNfe;
use App\Modules\Fiscal\Enums\NfeRecipientKind;
use App\Modules\Fiscal\Enums\NfeRefusal;
use App\Modules\Fiscal\Exceptions\NfeRefusedException;
use App\Modules\Fiscal\Helpers\AccessKey;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Reads one NF-e XML into `ParsedNfe` or refuses it with a code (rnc-2 R15–R17; plan AM3).
 *
 * Safety comes before parsing: over 1 MB is `too_large` unread; any `<!DOCTYPE` — case aside — is
 * `not_nfe` by a string check BEFORE `loadXML`, so no entity, external or nested, can be declared at
 * all; bytes that are not UTF-8 or hold a NUL (a UTF-16 file, where a byte search would miss the
 * DOCTYPE) are `not_xml`. The parser runs with `LIBXML_NONET`, never `LIBXML_NOENT`,
 * `LIBXML_DTDLOAD` or `LIBXML_PARSEHUGE`, and a parsed tree that still has a doctype is refused.
 *
 * Tags are read by local name, namespace or not, and defensively: `facts.md` §5 is general knowledge
 * marked to confirm (NT 2025.002's `IBSCBS` paths vary between versions). Pure: no container, no I/O.
 */
final class NfeXmlParser
{
    private const DOCTYPE = '<!DOCTYPE';

    private const NUL = "\0";

    private const ENCODING = 'UTF-8';

    private const LIBXML_OPTIONS = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;

    private const INFNFE_PATHS = ['/nfeProc/NFe/infNFe', '/NFe/infNFe'];

    private const KEY_PREFIX = 'NFe';

    private const MODEL_NFE = '55';

    private const ISSUED_FORMAT = 'Y-m-d\TH:i:sP';

    private const ISSUED_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/';

    private const NUMBER_PATTERN = '/^\d{1,9}$/';

    private const SERIES_PATTERN = '/^\d{1,3}$/';

    private const MONEY_PATTERN = '/^(\d{1,13})(?:\.(\d{1,2}))?$/';

    /** A rate in percent with up to four decimals, read as ten-thousandths of a percent. */
    private const RATE_PATTERN = '/^(\d{1,3})(?:\.(\d{1,4}))?$/';

    private const RATE_DECIMALS = 4;

    private const MONEY_DECIMALS = 2;

    /** 2026's test rates (`facts.md` §2): CBS 0,9 %, IBS 0,1 % (state + city), in ten-thousandths of a percent. */
    private const TEST_CBS_RATE = 9000;

    private const TEST_IBS_RATE = 1000;

    private const CRT_CODES = [1, 2, 3, 4];

    private const SYSTEM_MAX_LENGTH = 60;

    private const SAMPLE_SIZE = 5;

    private const CHECK_MISSING_GROUP = 'missing_group';

    private const CHECK_MISSING_CODES = 'missing_codes';

    private const CHECK_WITHOUT_TEST_VALUES = 'without_test_values';

    public function parse(string $content): ParsedNfe
    {
        $this->refuseUnsafe($content);

        $xpath = new DOMXPath($this->load($content));
        $invoice = $this->invoice($xpath);

        if ($this->text($xpath, $invoice, 'ide/mod') !== self::MODEL_NFE) {
            throw new NfeRefusedException(NfeRefusal::NotNfe);
        }

        $emitterCnpj = Cnpj::normalize($this->text($xpath, $invoice, 'emit/CNPJ'));

        if ($emitterCnpj === null) {
            throw new NfeRefusedException(NfeRefusal::NotNfe);
        }

        $accessKey = $this->accessKey($invoice, $emitterCnpj);
        $items = $this->itemChecks($xpath, $invoice);
        $recipientCnpj = Cnpj::normalize($this->text($xpath, $invoice, 'dest/CNPJ'));

        return new ParsedNfe(
            accessKey: $accessKey,
            number: (int) $this->required($xpath, $invoice, 'ide/nNF', self::NUMBER_PATTERN),
            series: (int) $this->required($xpath, $invoice, 'ide/serie', self::SERIES_PATTERN),
            issuedAt: $this->issuedAt($xpath, $invoice),
            emitterCnpj: $emitterCnpj,
            emitterCrt: $this->crt($xpath, $invoice),
            recipientCnpj: $recipientCnpj,
            recipientKind: $recipientCnpj === null ? NfeRecipientKind::Consumidor : NfeRecipientKind::Cnpj,
            totalCents: $this->cents($this->required($xpath, $invoice, 'total/ICMSTot/vNF', self::MONEY_PATTERN)),
            system: $this->system($xpath, $invoice),
            items: $items['items'],
            itemsMissingGroup: count($items[self::CHECK_MISSING_GROUP]),
            itemsMissingCodes: count($items[self::CHECK_MISSING_CODES]),
            itemsWithoutTestValues: count($items[self::CHECK_WITHOUT_TEST_VALUES]),
            sample: [
                self::CHECK_MISSING_GROUP => array_slice($items[self::CHECK_MISSING_GROUP], 0, self::SAMPLE_SIZE),
                self::CHECK_MISSING_CODES => array_slice($items[self::CHECK_MISSING_CODES], 0, self::SAMPLE_SIZE),
                self::CHECK_WITHOUT_TEST_VALUES => array_slice($items[self::CHECK_WITHOUT_TEST_VALUES], 0, self::SAMPLE_SIZE),
            ],
        );
    }

    /** Everything refused before a byte reaches libxml (plan AM3). */
    private function refuseUnsafe(string $content): void
    {
        if (strlen($content) > NfeDocumentSource::MAX_XML_BYTES) {
            throw new NfeRefusedException(NfeRefusal::TooLarge);
        }

        if (stripos($content, self::DOCTYPE) !== false) {
            throw new NfeRefusedException(NfeRefusal::NotNfe);
        }

        $unreadable = $content === '' || str_contains($content, self::NUL) || ! mb_check_encoding($content, self::ENCODING);

        if ($unreadable) {
            throw new NfeRefusedException(NfeRefusal::NotXml);
        }
    }

    private function load(string $content): DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($content, self::LIBXML_OPTIONS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($loaded === false) {
            throw new NfeRefusedException(NfeRefusal::NotXml);
        }

        if ($document->doctype !== null) {
            throw new NfeRefusedException(NfeRefusal::NotNfe);
        }

        return $document;
    }

    private function invoice(DOMXPath $xpath): DOMElement
    {
        foreach (self::INFNFE_PATHS as $path) {
            $node = $this->first($xpath, $path, null);

            if ($node instanceof DOMElement) {
                return $node;
            }
        }

        throw new NfeRefusedException(NfeRefusal::NotNfe);
    }

    private function accessKey(DOMElement $invoice, string $emitterCnpj): string
    {
        $id = $invoice->getAttribute('Id');
        $key = str_starts_with($id, self::KEY_PREFIX) ? substr($id, strlen(self::KEY_PREFIX)) : '';

        $matchesDocument = AccessKey::isValid($key)
            && AccessKey::model($key) === self::MODEL_NFE
            && AccessKey::emitterCnpj($key) === $emitterCnpj;

        if (! $matchesDocument) {
            throw new NfeRefusedException(NfeRefusal::InvalidKey);
        }

        return $key;
    }

    private function issuedAt(DOMXPath $xpath, DOMElement $invoice): CarbonImmutable
    {
        $issued = $this->required($xpath, $invoice, 'ide/dhEmi', self::ISSUED_PATTERN);
        $moment = CarbonImmutable::createFromFormat(self::ISSUED_FORMAT, $issued);

        if (! $moment instanceof CarbonImmutable) {
            throw new NfeRefusedException(NfeRefusal::NotNfe);
        }

        return $moment->utc();
    }

    private function crt(DOMXPath $xpath, DOMElement $invoice): ?int
    {
        $crt = $this->text($xpath, $invoice, 'emit/CRT');

        return $crt !== null && ctype_digit($crt) && in_array((int) $crt, self::CRT_CODES, true) ? (int) $crt : null;
    }

    private function system(DOMXPath $xpath, DOMElement $invoice): ?string
    {
        $system = $this->text($xpath, $invoice, 'ide/verProc');

        return $system === null ? null : mb_substr($system, 0, self::SYSTEM_MAX_LENGTH);
    }

    /**
     * Each item under the FIRST check it fails, in order: no `IBSCBS` group (rejection 1115 once
     * validated), then a blank CST or cClassTrib, then the 2026 test rates not shown.
     *
     * @return array{items: int, missing_group: list<int>, missing_codes: list<int>, without_test_values: list<int>}
     */
    private function itemChecks(DOMXPath $xpath, DOMElement $invoice): array
    {
        $checks = ['items' => 0, self::CHECK_MISSING_GROUP => [], self::CHECK_MISSING_CODES => [], self::CHECK_WITHOUT_TEST_VALUES => []];
        $items = $xpath->query($this->localPath('det'), $invoice);

        foreach ($items === false ? [] : $items as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $checks['items']++;
            $number = ctype_digit($item->getAttribute('nItem')) ? (int) $item->getAttribute('nItem') : $checks['items'];
            $failed = $this->failedCheck($xpath, $item);

            if ($failed !== null) {
                $checks[$failed][] = $number;
            }
        }

        if ($checks['items'] === 0) {
            throw new NfeRefusedException(NfeRefusal::NotNfe);
        }

        return $checks;
    }

    private function failedCheck(DOMXPath $xpath, DOMElement $item): ?string
    {
        $group = $this->first($xpath, 'imposto/IBSCBS', $item);

        if (! $group instanceof DOMElement) {
            return self::CHECK_MISSING_GROUP;
        }

        $codesBlank = $this->text($xpath, $group, 'CST') === null || $this->text($xpath, $group, 'cClassTrib') === null;

        if ($codesBlank) {
            return self::CHECK_MISSING_CODES;
        }

        $cbs = $this->rate($this->text($xpath, $group, 'gIBSCBS/gCBS/pCBS'));
        $ibsState = $this->rate($this->text($xpath, $group, 'gIBSCBS/gIBSUF/pIBSUF'));
        $ibsCity = $this->rate($this->text($xpath, $group, 'gIBSCBS/gIBSMun/pIBSMun'));

        $showsTestValues = $cbs === self::TEST_CBS_RATE && $ibsState !== null && $ibsCity !== null && $ibsState + $ibsCity === self::TEST_IBS_RATE;

        return $showsTestValues ? null : self::CHECK_WITHOUT_TEST_VALUES;
    }

    private function required(DOMXPath $xpath, DOMNode $context, string $path, string $pattern): string
    {
        $value = $this->text($xpath, $context, $path);

        if ($value === null || preg_match($pattern, $value) !== 1) {
            throw new NfeRefusedException(NfeRefusal::NotNfe);
        }

        return $value;
    }

    /** The trimmed text of the first node at `$path` under `$context`, or null when absent or blank. */
    private function text(DOMXPath $xpath, DOMNode $context, string $path): ?string
    {
        $node = $this->first($xpath, $path, $context);
        $value = $node === null ? '' : trim((string) $node->textContent);

        return $value === '' ? null : $value;
    }

    private function first(DOMXPath $xpath, string $path, ?DOMNode $context): ?DOMNode
    {
        $nodes = $xpath->query($this->localPath($path), $context);

        return $nodes === false ? null : $nodes->item(0);
    }

    /** `ide/mod` → `*[local-name()='ide']/*[local-name()='mod']`, so a missing namespace reads the same. */
    private function localPath(string $path): string
    {
        $absolute = str_starts_with($path, '/');
        $steps = array_map(fn (string $tag) => "*[local-name()='{$tag}']", explode('/', trim($path, '/')));

        return ($absolute ? '/' : '').implode('/', $steps);
    }

    /** `1234.5` → 123450, never through a float. */
    private function cents(string $amount): int
    {
        preg_match(self::MONEY_PATTERN, $amount, $parts);

        return (int) $parts[1] * 10 ** self::MONEY_DECIMALS + (int) str_pad($parts[2] ?? '', self::MONEY_DECIMALS, '0');
    }

    /** `0.9000` → 9000 ten-thousandths of a percent; null when absent or not a rate. */
    private function rate(?string $rate): ?int
    {
        if ($rate === null || preg_match(self::RATE_PATTERN, $rate, $parts) !== 1) {
            return null;
        }

        return (int) $parts[1] * 10 ** self::RATE_DECIMALS + (int) str_pad($parts[2] ?? '', self::RATE_DECIMALS, '0');
    }
}
