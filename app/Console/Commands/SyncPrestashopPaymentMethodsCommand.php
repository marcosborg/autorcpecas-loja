<?php

namespace App\Console\Commands;

use App\Services\Payments\PrestashopPaymentSyncService;
use Illuminate\Console\Command;
use PDO;

class SyncPrestashopPaymentMethodsCommand extends Command
{
    protected $signature = 'prestashop:sync-payment-methods
        {--parameters=C:\Users\sara.borges\Desktop\autorcpecasprestahop\app\config\parameters.php : Caminho do parameters.php}
        {--host= : Override host da BD PrestaShop}
        {--port= : Override porto da BD PrestaShop}
        {--dbname= : Override nome da BD PrestaShop}
        {--user= : Override utilizador da BD PrestaShop}
        {--password= : Override password da BD PrestaShop}
        {--prefix= : Override prefixo de tabelas (ex: ps_)}
        {--target-database=production : Ligacao Laravel de destino (sandbox|production)}';

    protected $description = 'Sincroniza metodos de pagamento SIBS e transferencia bancaria da PrestaShop para a loja';

    public function handle(PrestashopPaymentSyncService $syncService): int
    {
        $parametersPath = (string) $this->option('parameters');
        if (! is_file($parametersPath)) {
            $this->error("Ficheiro nao encontrado: {$parametersPath}");

            return self::FAILURE;
        }

        /** @var array<string, mixed> $cfg */
        $cfg = include $parametersPath;
        $params = (array) ($cfg['parameters'] ?? []);

        $host = (string) ($this->option('host') ?: ($params['database_host'] ?? '127.0.0.1'));
        $port = (string) ($this->option('port') ?: (($params['database_port'] ?? '') ?: '3306'));
        $db = (string) ($this->option('dbname') ?: ($params['database_name'] ?? ''));
        $user = (string) ($this->option('user') ?: ($params['database_user'] ?? ''));
        $pass = (string) ($this->option('password') ?: ($params['database_password'] ?? ''));
        $prefix = (string) ($this->option('prefix') ?: ($params['database_prefix'] ?? 'ps_'));
        $targetDatabase = (string) ($this->option('target-database') ?: 'production');

        if ($db === '' || $user === '') {
            $this->error('Configuracao de BD invalida no parameters.php');

            return self::FAILURE;
        }

        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Throwable $e) {
            $this->error('Falha ao ligar a BD PrestaShop: '.$e->getMessage());

            return self::FAILURE;
        }

        $result = $syncService->sync($pdo, $prefix, $targetDatabase);
        $this->info('Sincronizacao concluida.');
        $this->line('metodos atualizados: '.$result['created_or_updated']);
        $this->line('codes: '.implode(', ', $result['methods']));

        return self::SUCCESS;
    }
}

