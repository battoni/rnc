<?php

namespace App\Console\Commands;

use App\Modules\Firm\Services\FirmOffboarder;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * `firm:offboard <slug> --confirm` (rnc-4 R40, A49): a firm leaving the platform — its data exported
 * to the private disk, every lead anonymised, the firm closed (host 404, logins refused), by
 * `FirmOffboarder`. Irreversible, so without `--confirm` it only says what it would do.
 */
class OffboardFirm extends Command
{
    protected $signature = 'firm:offboard
        {slug : O slug da firma que sai da plataforma}
        {--confirm : Confirma a saída: exporta, anonimiza os leads e fecha a firma (irreversível)}';

    protected $description = "Export a firm's data, anonymise its leads and close it (host 404, logins refused)";

    public function handle(FirmOffboarder $firmOffboarder): int
    {
        $slug = (string) $this->argument('slug');

        try {
            $firm = $firmOffboarder->findOffboardable($slug);

            if (! $this->option('confirm')) {
                $this->warn("Nada foi feito. A saída de {$firm->name} ({$firm->slug}) exporta os dados, anonimiza todos os leads e fecha a firma — sem volta.");
                $this->line("Para seguir: php artisan firm:offboard {$firm->slug} --confirm");

                return self::FAILURE;
            }

            $offboarded = $firmOffboarder->offboard($slug);
        } catch (ValidationException $exception) {
            $this->error('Firma não desligada:');

            foreach ($exception->errors() as $field => $keys) {
                $this->line("  {$field}: ".implode(', ', $keys));
            }

            return self::FAILURE;
        }

        $this->info("Firma desligada: {$offboarded->firm->name} ({$offboarded->firm->slug})");
        $this->line("Exportação (disco local, entregue à firma e depois apague): {$offboarded->path}");
        $this->line("Leads anonimizados: {$offboarded->leads}");

        return self::SUCCESS;
    }
}
