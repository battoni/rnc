<?php

use App\Modules\Briefing\DTOs\BriefingMailDTO;
use App\Modules\Firm\Services\MailBrand;

/*
 * rnc-3 R25 (W81): the e-mail's sentence for the Radar's action, the demo's copy
 * (`ceo.js` → `topActions`: "Radar: N clientes da carteira com “<título>”, <faixa>.").
 */
it('writes the Radar action in the demo words, with the clients, the title and the range', function () {
    app()->setLocale('pt_BR');
    $mail = new BriefingMailDTO('Reinaldo', 'Franco Contabilidade', 'http://localhost:5173', [], MailBrand::platform());

    expect($mail->actionLine([
        'kind' => 'radar',
        'params' => ['rule' => 'contratos', 'title' => 'Contratos longos sem cláusula', 'clients' => 12, 'range_cents' => [12_000_000, 36_000_000], 'period' => 'ano'],
        'to' => '/gestao/radar',
    ]))->toBe('Radar: 12 clientes da carteira com “Contratos longos sem cláusula”, R$ 120–360 mil/ano.');
});

it('writes a monthly Radar range per month', function () {
    app()->setLocale('pt_BR');
    $mail = new BriefingMailDTO('Reinaldo', 'Franco Contabilidade', 'http://localhost:5173', [], MailBrand::platform());

    expect($mail->actionLine([
        'kind' => 'radar',
        'params' => ['rule' => 'split-payment', 'title' => 'Split payment', 'clients' => 1, 'range_cents' => [12_000_000, 36_000_000], 'period' => 'mes'],
        'to' => '/gestao/radar',
    ]))->toBe('Radar: 1 cliente da carteira com “Split payment”, R$ 120–360 mil/mês.');
});
