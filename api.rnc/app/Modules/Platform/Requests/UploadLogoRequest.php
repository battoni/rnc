<?php

namespace App\Modules\Platform\Requests;

use App\Modules\Platform\Rules\LogoImage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A firm's logo from the console (`POST /api/platform/firms/{id}/logo`, rnc-4 R27, D22): one file,
 * `logo`. The content decides first (`LogoImage`: PNG, JPEG or WebP sniffed with `finfo`, header
 * parsed), then Laravel's own sniffed `mimetypes`, the size (≤ 512 KB) and the sides (≤ 1024 px).
 * Each refusal names one key the console translates.
 */
class UploadLogoRequest extends FormRequest
{
    public const MAX_KILOBYTES = 512;

    public const MAX_SIDE_PIXELS = 1024;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'logo' => [
                'bail',
                'required',
                'file',
                new LogoImage,
                'mimetypes:image/png,image/jpeg,image/webp',
                'max:'.self::MAX_KILOBYTES,
                'dimensions:max_width='.self::MAX_SIDE_PIXELS.',max_height='.self::MAX_SIDE_PIXELS,
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'logo.required' => 'platform.logoRequired',
            'logo.uploaded' => 'platform.logoTooLarge',
            'logo.file' => LogoImage::MESSAGE,
            'logo.mimetypes' => LogoImage::MESSAGE,
            'logo.max' => 'platform.logoTooLarge',
            'logo.dimensions' => 'platform.logoTooWide',
        ];
    }
}
