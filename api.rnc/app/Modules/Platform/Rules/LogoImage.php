<?php

namespace App\Modules\Platform\Rules;

use App\Modules\Firm\Services\FirmLogoService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * A logo is judged by its bytes (rnc-4 R27, D22): `finfo` must read PNG, JPEG or WebP — so an SVG,
 * an SVG or an HTML page renamed `.png`, a GIF are refused whatever the name or the MIME the client
 * sent — and the image header must parse as that same type, so a valid signature over junk is
 * refused too. `platform.logoType`.
 */
class LogoImage implements ValidationRule
{
    public const MESSAGE = 'platform.logoType';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $this->isAllowedImage($value)) {
            $fail(self::MESSAGE);
        }
    }

    private function isAllowedImage(UploadedFile $file): bool
    {
        $sniffed = FirmLogoService::sniff($file);
        $path = $file->getRealPath();
        $isAllowedType = $sniffed !== null && array_key_exists($sniffed, FirmLogoService::EXTENSIONS);

        if (! $isAllowedType || $path === false) {
            return false;
        }

        $header = @getimagesize($path);
        $headerParses = is_array($header) && $header[0] > 0 && $header[1] > 0;

        return $headerParses && $header['mime'] === $sniffed;
    }
}
