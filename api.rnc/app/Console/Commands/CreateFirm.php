<?php

namespace App\Console\Commands;

use App\Modules\Firm\DTOs\CreateFirmDTO;
use App\Modules\Firm\Services\FirmProvisioner;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * `firm:create <slug> --name= --manager= [--email=] [--specialist]` (rnc-4 R21, D17): a new firm
 * ready to sign in to at `<slug>.<root>`, made by `FirmProvisioner` — the same service the platform
 * console (F4a) calls. The manager's password is printed here once and stored nowhere but hashed.
 */
class CreateFirm extends Command
{
    protected $signature = 'firm:create
        {slug : O slug da firma, que é o subdomínio (ex.: acme)}
        {--name= : O nome da firma (ex.: "Acme Contábil")}
        {--manager= : O usuário do gestor (ex.: helena.acme)}
        {--email= : O e-mail do gestor, também a caixa de contato da firma}
        {--specialist : O gestor também atende agendamentos, no horário da firma}';

    protected $description = 'Create a firm with its manager, default sector pages, consent v1 and default modules';

    public function handle(FirmProvisioner $firmProvisioner): int
    {
        $email = (string) $this->option('email');

        try {
            $provisioned = $firmProvisioner->provision(new CreateFirmDTO(
                slug: (string) $this->argument('slug'),
                name: (string) $this->option('name'),
                manager: (string) $this->option('manager'),
                email: $email === '' ? null : $email,
                specialist: (bool) $this->option('specialist'),
            ));
        } catch (ValidationException $exception) {
            $this->error('Firma não criada:');

            foreach ($exception->errors() as $field => $keys) {
                $this->line("  {$field}: ".implode(', ', $keys));
            }

            return self::FAILURE;
        }

        $firm = $provisioned->firm;
        $host = $firm->slug.'.'.config('platform.root_domain');

        $this->info("Firma criada: {$firm->name} ({$firm->slug})");
        $this->line("Endereço: {$host}");
        $this->line('Módulos: núcleo + '.implode(', ', $firm->modules));
        $this->line("Gestor: {$provisioned->manager->username}");
        $this->line("Senha (mostrada uma vez): {$provisioned->password}");

        return self::SUCCESS;
    }
}
