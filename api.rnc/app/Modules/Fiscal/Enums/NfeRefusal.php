<?php

namespace App\Modules\Fiscal\Enums;

/**
 * Why a file of an import stored no document (rnc-2 R17). Each refusal is listed in its batch with
 * the file's name; one broken file never stops the others.
 */
enum NfeRefusal: string
{
    /** Empty, not UTF-8, a NUL byte, or bytes the XML parser cannot read. */
    case NotXml = 'not_xml';

    /** XML, but not a model-55 NF-e — or it carries a `<!DOCTYPE` (plan AM3). */
    case NotNfe = 'not_nfe';

    /** The `infNFe` key is not 44 digits, fails its mod-11, or names another emitter or model. */
    case InvalidKey = 'invalid_key';

    /** Neither the emitter nor the recipient is a client of the importing firm (D6). */
    case ClientUnknown = 'client_unknown';

    /** That client already holds this key (unique per firm + key + client — spec C1). */
    case Duplicate = 'duplicate';

    /** Over 1 MB: never read. */
    case TooLarge = 'too_large';
}
