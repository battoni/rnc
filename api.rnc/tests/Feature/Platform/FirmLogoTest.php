<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\MailBrand;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * rnc-4 F4c — A41 (R27, D22). The operator uploads a firm's logo from the console: PNG, JPEG or
 * WebP, ≤ 512 KB, ≤ 1024 px a side, judged by the file's CONTENT (`finfo` and the image header), never
 * by its name or the MIME the client claims. SVG (script-capable) and anything else is 422. The file is
 * stored on the `public` disk under a name the server makes up, a new upload replaces the old file
 * (deleted), and `public/firm.logo_url` serves it. Security-sensitive (P6).
 *
 * Fixtures are generated here and handed over as REAL uploads (`UploadedFile` on a temp file), so the
 * MIME Laravel sees is sniffed from the bytes — the testing `File` fake would answer by extension.
 */
beforeEach(function () {
    Storage::fake('public');
    config(['firms.default_slug' => PlatformFirmSeeder::PLATFORM_SLUG]);

    $this->seed([FirmSeeder::class, PlatformFirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->firstOrFail();
    $this->operator = User::factory()->create(['firm_id' => null, 'role_id' => Role::where('slug', 'operador')->value('id')]);
});

/** A real upload of the given bytes, named and typed as the client claims. */
function logoUpload(string $bytes, string $clientName, string $clientMime): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'logo');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $clientName, $clientMime, null, true);
}

/** A PNG of the given size, padded with a text chunk to about `$kilobytes` (a valid image either way). */
function logoPng(int $width, int $height, int $kilobytes = 0): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 15, 92, 77));
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();

    if ($kilobytes === 0) {
        return $png;
    }

    $padding = str_repeat('a', max(0, $kilobytes * 1024 - strlen($png) - 12));
    $chunk = pack('N', strlen($padding)).'tEXt'.$padding.pack('N', crc32('tEXt'.$padding));

    return substr($png, 0, -12).$chunk.substr($png, -12);
}

function logoJpeg(int $width, int $height, int $kilobytes = 0): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    // COM segments after SOI pad the file without breaking it (each holds at most 65 533 bytes).
    $segments = '';
    $left = $kilobytes * 1024 - strlen($jpeg);
    while ($left > 0) {
        $padding = str_repeat('b', min(65533, $left));
        $segments .= "\xFF\xFE".pack('n', strlen($padding) + 2).$padding;
        $left -= strlen($padding) + 4;
    }

    return substr($jpeg, 0, 2).$segments.substr($jpeg, 2);
}

function logoWebp(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagewebp($image);

    return (string) ob_get_clean();
}

const LOGO_SVG = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>';

function logoPost(Firm $firm, UploadedFile $file): TestResponse
{
    return test()->post("/api/platform/firms/{$firm->id}/logo", ['logo' => $file], ['Accept' => 'application/json']);
}

describe('POST platform/firms/{id}/logo', function () {
    it('takes a 300 KB PNG, stores it under a server-made name and serves it as logo_url', function () {
        $png = logoPng(400, 200, 300);
        expect(strlen($png))->toBeGreaterThan(290 * 1024)->toBeLessThan(512 * 1024);

        $answer = $this->actingAs($this->operator)
            ->post("/api/platform/firms/{$this->franco->id}/logo", ['logo' => logoUpload($png, 'meu logo.png', 'image/png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data');

        $path = (string) $this->franco->fresh()->logo_path;
        expect($path)->toMatch('#^firms/'.$this->franco->id.'/logo-[A-Za-z0-9]{40}\.png$#')
            ->and($path)->not->toContain('meu logo');
        Storage::disk('public')->assertExists($path);
        expect(Storage::disk('public')->get($path))->toBe($png)
            ->and($answer['logo_url'])->toBe(Storage::disk('public')->url($path));

        auth()->forgetGuards();
        $this->withHeader('X-Firm', 'franco')->getJson('/api/public/firm')
            ->assertOk()
            ->assertJsonPath('data.logo_url', Storage::disk('public')->url($path));
        expect(MailBrand::fromFirm($this->franco->fresh())->logoPath)->toBe(Storage::disk('public')->path($path));
    });

    it('takes a JPEG and a WebP, naming the file by its content', function (string $bytes, string $extension) {
        $this->actingAs($this->operator);
        logoPost($this->franco, logoUpload($bytes, 'logo.bin', 'application/octet-stream'))->assertOk();

        expect($this->franco->fresh()->logo_path)->toEndWith('.'.$extension);
    })->with([
        'jpeg' => fn () => [logoJpeg(300, 300), 'jpg'],
        'webp' => fn () => [logoWebp(300, 300), 'webp'],
    ]);

    it('replaces the old file and deletes it', function () {
        $this->actingAs($this->operator);
        logoPost($this->franco, logoUpload(logoPng(100, 100), 'a.png', 'image/png'))->assertOk();
        $old = (string) $this->franco->fresh()->logo_path;

        logoPost($this->franco, logoUpload(logoPng(120, 120), 'b.png', 'image/png'))->assertOk();
        $new = (string) $this->franco->fresh()->logo_path;

        expect($new)->not->toBe($old);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing($old);
    });

    it('refuses by content, keeping the current logo untouched', function (Closure $file, string $error) {
        $this->actingAs($this->operator);
        logoPost($this->franco, logoUpload(logoPng(100, 100), 'a.png', 'image/png'))->assertOk();
        $kept = (string) $this->franco->fresh()->logo_path;

        logoPost($this->franco, $file())
            ->assertUnprocessable()
            ->assertJsonPath('errors.logo.0', $error);

        expect($this->franco->fresh()->logo_path)->toBe($kept)
            ->and(Storage::disk('public')->allFiles())->toBe([$kept]);
    })->with([
        'an SVG' => [fn () => logoUpload(LOGO_SVG, 'logo.svg', 'image/svg+xml'), 'platform.logoType'],
        'an SVG renamed .png, claimed image/png' => [fn () => logoUpload(LOGO_SVG, 'logo.png', 'image/png'), 'platform.logoType'],
        'an HTML page renamed .png' => [fn () => logoUpload('<!doctype html><html><body><script>alert(1)</script></body></html>', 'logo.png', 'image/png'), 'platform.logoType'],
        'a GIF' => [fn () => logoUpload(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 'logo.png', 'image/png'), 'platform.logoType'],
        'a PNG signature over junk' => [fn () => logoUpload("\x89PNG\r\n\x1a\n".random_bytes(2048), 'logo.png', 'image/png'), 'platform.logoType'],
        'a 600 KB JPEG' => [fn () => logoUpload(logoJpeg(300, 300, 600), 'logo.jpg', 'image/jpeg'), 'platform.logoTooLarge'],
        'a 2000 px PNG' => [fn () => logoUpload(logoPng(2000, 100), 'logo.png', 'image/png'), 'platform.logoTooWide'],
    ]);

    it('asks for a file', function () {
        $this->actingAs($this->operator)->postJson("/api/platform/firms/{$this->franco->id}/logo", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.logo.0', 'platform.logoRequired');
    });

    it('answers 404 for an unknown firm, storing nothing', function () {
        $this->actingAs($this->operator);
        test()->post('/api/platform/firms/999999/logo', ['logo' => logoUpload(logoPng(10, 10), 'a.png', 'image/png')], ['Accept' => 'application/json'])
            ->assertNotFound();

        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('refuses anyone but the firm-less operator (403), storing nothing', function (string $role, bool $inFirm) {
        $person = User::factory()->create(['firm_id' => $inFirm ? $this->franco->id : null, 'role_id' => Role::where('slug', $role)->value('id')]);
        $this->actingAs($person);

        logoPost($this->franco, logoUpload(logoPng(10, 10), 'a.png', 'image/png'))->assertForbidden();

        expect($this->franco->fresh()->logo_path)->toBeNull()
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    })->with([
        "the firm's manager" => ['gestao', true],
        'the team' => ['equipe', true],
        'an operator placed in a firm' => ['operador', true],
    ]);
});

describe('DELETE platform/firms/{id}/logo', function () {
    it('removes the file and the path, back to the logo text', function () {
        $this->actingAs($this->operator);
        logoPost($this->franco, logoUpload(logoPng(100, 100), 'a.png', 'image/png'))->assertOk();
        $path = (string) $this->franco->fresh()->logo_path;

        $this->deleteJson("/api/platform/firms/{$this->franco->id}/logo")->assertOk()->assertJsonPath('data.logo_url', null);

        expect($this->franco->fresh()->logo_path)->toBeNull();
        Storage::disk('public')->assertMissing($path);
    });

    it('is refused to the firm\'s manager', function () {
        $this->actingAs(User::factory()->create(['firm_id' => $this->franco->id, 'role_id' => Role::where('slug', 'gestao')->value('id')]));

        $this->deleteJson("/api/platform/firms/{$this->franco->id}/logo")->assertForbidden();
    });
});
