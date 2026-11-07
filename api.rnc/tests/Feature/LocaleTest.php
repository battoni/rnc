<?php

use Illuminate\Support\Facades\Route;

/*
 * The API writes a few strings itself — a verification e-mail's subject, sent while the request is
 * open — and they came out in the server's default language whatever the person was reading the app
 * in. The SPA sends `Accept-Language` on every request; `SetLocale` honours it.
 */

beforeEach(function () {
    Route::middleware('api')->get('/api/testing/locale', fn () => response()->json([
        'locale' => app()->getLocale(),
        'subject' => __('mail.verification.subject'),
    ]));
});

describe('Accept-Language', function () {
    it('answers in Portuguese when the interface is in Portuguese', function () {
        $this->getJson('/api/testing/locale', ['Accept-Language' => 'pt-BR'])
            ->assertOk()
            ->assertJson(['locale' => 'pt_BR', 'subject' => 'Seu código de verificação']);
    });

    it('reads the first tag of a weighted list', function () {
        $this->getJson('/api/testing/locale', ['Accept-Language' => 'en-US,pt-BR;q=0.8'])
            ->assertJson(['locale' => 'en', 'subject' => 'Your verification code']);
    });

    it('keeps the configured locale for a language the app does not have', function () {
        $this->getJson('/api/testing/locale', ['Accept-Language' => 'fr-FR'])
            ->assertJson(['locale' => config('app.locale')]);
    });

    it('keeps the configured locale when no header is sent', function () {
        $this->withHeaders(['Accept-Language' => ''])
            ->getJson('/api/testing/locale')
            ->assertJson(['locale' => config('app.locale')]);
    });
});
