<?php

use Resend\Laravel\ResendServiceProvider;

/*
 * The sender has to belong to a domain Resend has verified.
 *
 * This is the failure mode of this integration and it is silent: with an unverified sender the
 * API accepts the call and never delivers. No exception, no failed job, nothing in the log — the
 * person never receives the code, and the only symptom is a support message days later.
 *
 * It asks nothing of Resend. A test that needs the network is a test that fails on a plane; this
 * one asks whether the configuration is a combination that CAN work.
 *
 * **It reads the .env file, not `config()`, and that is the point.** `phpunit.xml` sets
 * `MAIL_MAILER=array` so the suite never sends anything — which also means a config-based check
 * inspects the test environment and is blind to what is actually deployed. The first version of
 * this file did exactly that: two of three mutations sailed straight through it.
 */

/**
 * The verified domains come from the project's own `.env`, never from a constant in here.
 *
 * This file is MERGE in the sync manifest: it travels to every project. A domain written into
 * it is one product's answer shipped as everyone's, and the next project inherits a check that
 * passes for a domain it does not own. `MAIL_VERIFIED_DOMAINS` is read from the same file the
 * rest of this test reads, so the mechanism travels and the data does not.
 *
 * An empty list is treated as "the project has not answered yet" and reported, rather than
 * silently passing everything — an unanswered guard that says nothing is the failure mode this
 * whole file exists to refuse.
 */
function verifiedDomains(array $env): array
{
    return array_values(array_filter(array_map(
        'trim',
        explode(',', $env['MAIL_VERIFIED_DOMAINS'] ?? '')
    )));
}

/** The deployed configuration, as written on disk. */
function envValues(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    $values = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        if ($line === '' || str_starts_with(ltrim($line), '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim(trim($value), '"\'');
    }

    return $values;
}

/**
 * Every reason this configuration cannot deliver mail, as sentences. Empty means it can.
 *
 * A pure function over the four values, so the rule can be exercised with cases the deployed
 * file does not happen to contain — which is what a guard has to survive being.
 *
 * @return array<int, string>
 */
function mailConfigurationProblems(array $env): array
{
    $mailer = $env['MAIL_MAILER'] ?? 'log';
    $address = $env['MAIL_FROM_ADDRESS'] ?? '';
    $key = $env['RESEND_API_KEY'] ?? '';
    $verifies = ($env['REGISTRATION_VERIFY_EMAIL'] ?? 'false') === 'true';

    $fromName = $env['MAIL_FROM_NAME'] ?? '';
    $appName = $env['APP_NAME'] ?? '';

    if ($fromName !== '' && $fromName === $appName) {
        // `APP_NAME` is this API's codename — `api.rnc`. It belongs in logs and queue names, never
        // above a message to a person. Gmail showed "api.rnc <nao-responda@…>" on the first real
        // send, and a machine-looking sender is one of the signals a spam filter weighs.
        return ["MAIL_FROM_NAME is `{$fromName}`, the API's own codename. Use the product's name."];
    }

    if ($address === 'hello@example.com') {
        // Laravel's own placeholder, which survives a careless copy of .env.example.
        return ['MAIL_FROM_ADDRESS is still the framework placeholder.'];
    }

    // `log` and `array` deliver nowhere by design, so nothing below applies to them.
    if (in_array($mailer, ['log', 'array'], true)) {
        return [];
    }

    $problems = [];
    $domain = mb_strtolower(mb_substr(mb_strrchr($address, '@') ?: '', 1));

    $verified = verifiedDomains($env);

    if ($verified === []) {
        $problems[] = 'MAIL_VERIFIED_DOMAINS is empty — name the domains Resend verified for '.
            'this project, or this check cannot tell a real sender from a typo.';
    } elseif (! in_array($domain, $verified, true)) {
        $problems[] = "MAIL_FROM_ADDRESS `{$address}` is not on a domain verified in Resend — ".
            'the API will accept the call and deliver nothing, silently.';
    }

    if ($mailer === 'resend' && $key === '') {
        $problems[] = 'MAIL_MAILER is resend and RESEND_API_KEY is empty.';
    }

    if (! $verifies) {
        // A live mailer whose only message is switched off is an integration with no consumer;
        // the gate open with no mailer is a sign-up asking for a code nobody can receive.
        $problems[] = 'The mailer is live and REGISTRATION_VERIFY_EMAIL is false — nothing sends mail.';
    }

    return $problems;
}

describe('the rule', function () {
    it('passes a configuration that can actually deliver', function () {
        expect(mailConfigurationProblems([
            'MAIL_MAILER' => 'resend',
            'MAIL_VERIFIED_DOMAINS' => 'mail.example.com',
            'MAIL_FROM_ADDRESS' => 'nao-responda@mail.example.com',
            'RESEND_API_KEY' => 're_something',
            'REGISTRATION_VERIFY_EMAIL' => 'true',
        ]))->toBe([]);
    });

    it('catches a sender on an unverified domain — including the parent domain', function () {
        // The PARENT domain is not what was verified: verification does not climb, so
        // `mail.example.com` being verified says nothing about `example.com`.
        expect(mailConfigurationProblems([
            'MAIL_MAILER' => 'resend',
            'MAIL_VERIFIED_DOMAINS' => 'mail.example.com',
            'MAIL_FROM_ADDRESS' => 'nao-responda@example.com',
            'RESEND_API_KEY' => 're_something',
            'REGISTRATION_VERIFY_EMAIL' => 'true',
        ]))->toHaveCount(1);
    });

    it('catches a project that never named its verified domains', function () {
        // The branch that made this worth extracting from a constant: a project that inherits
        // the check and never answers it. Silence here would pass every address on earth.
        expect(mailConfigurationProblems([
            'MAIL_MAILER' => 'resend',
            'MAIL_VERIFIED_DOMAINS' => '',
            'MAIL_FROM_ADDRESS' => 'nao-responda@mail.example.com',
            'RESEND_API_KEY' => 're_something',
            'REGISTRATION_VERIFY_EMAIL' => 'true',
        ]))->toHaveCount(1);
    });

    it('catches a live mailer with no key', function () {
        expect(mailConfigurationProblems([
            'MAIL_MAILER' => 'resend',
            'MAIL_VERIFIED_DOMAINS' => 'mail.example.com',
            'MAIL_FROM_ADDRESS' => 'nao-responda@mail.example.com',
            'RESEND_API_KEY' => '',
            'REGISTRATION_VERIFY_EMAIL' => 'true',
        ]))->toHaveCount(1);
    });

    it('catches a live mailer whose only message is switched off', function () {
        expect(mailConfigurationProblems([
            'MAIL_MAILER' => 'resend',
            'MAIL_VERIFIED_DOMAINS' => 'mail.example.com',
            'MAIL_FROM_ADDRESS' => 'nao-responda@mail.example.com',
            'RESEND_API_KEY' => 're_something',
            'REGISTRATION_VERIFY_EMAIL' => 'false',
        ]))->toHaveCount(1);
    });

    it('catches a sender name copied from the API codename', function () {
        expect(mailConfigurationProblems([
            'APP_NAME' => 'api.rnc',
            'MAIL_FROM_NAME' => 'api.rnc',
            'MAIL_MAILER' => 'resend',
            'MAIL_VERIFIED_DOMAINS' => 'mail.example.com',
            'MAIL_FROM_ADDRESS' => 'nao-responda@mail.example.com',
            'RESEND_API_KEY' => 're_something',
            'REGISTRATION_VERIFY_EMAIL' => 'true',
        ]))->toHaveCount(1);
    });

    it('catches the framework placeholder even under a mailer that sends nowhere', function () {
        expect(mailConfigurationProblems([
            'MAIL_MAILER' => 'log',
            'MAIL_VERIFIED_DOMAINS' => 'mail.example.com',
            'MAIL_FROM_ADDRESS' => 'hello@example.com',
        ]))->toHaveCount(1);
    });

    it('leaves a local `log` configuration alone', function () {
        expect(mailConfigurationProblems([
            'MAIL_MAILER' => 'log',
            'MAIL_VERIFIED_DOMAINS' => 'mail.example.com',
            'MAIL_FROM_ADDRESS' => 'nao-responda@mail.example.com',
        ]))->toBe([]);
    });
});

describe('this checkout', function () {
    it('is configured in a way that could deliver', function () {
        $problems = mailConfigurationProblems(envValues(base_path('.env')));

        expect($problems)->toBe([], implode(' ', $problems));
    });

    it('ships an example file that is complete and carries no credential', function () {
        $example = envValues(base_path('.env.example'));

        expect($example)->toHaveKey('RESEND_API_KEY');
        expect($example['RESEND_API_KEY'])->toBe('', '.env.example carries a live key.');
        expect(mailConfigurationProblems($example))->toBe([]);
    });
});

describe('the resend transport', function () {
    it('is wired, so switching the mailer is a one-line change', function () {
        expect(config('mail.mailers.resend.transport'))->toBe('resend');
        expect(class_exists(ResendServiceProvider::class))->toBeTrue();
    });
});
