<?php

namespace App\Console\Commands;

use App\Models\PaymentMethod;
use App\Models\ShippingCarrier;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class ImportPrestashopCommerceCommand extends Command
{
    protected $signature = 'prestashop:import-commerce
        {--parameters=C:\Users\sara.borges\Desktop\autorcpecasprestahop\app\config\parameters.php : Caminho do parameters.php}
        {--truncate : Limpa tabelas de shipping/payment antes de importar}';

    protected $description = 'Importa transportadoras e módulos de pagamento de uma base PrestaShop para o modelo interno';

    public function handle(): int
    {
        $parametersPath = (string) $this->option('parameters');

        if (! is_file($parametersPath)) {
            $this->error("Ficheiro não encontrado: {$parametersPath}");

            return self::FAILURE;
        }

        /** @var array<string, mixed> $cfg */
        $cfg = include $parametersPath;
        $params = (array) ($cfg['parameters'] ?? []);

        $host = (string) ($params['database_host'] ?? '127.0.0.1');
        $port = (string) ($params['database_port'] ?? '3306');
        $db = (string) ($params['database_name'] ?? '');
        $user = (string) ($params['database_user'] ?? '');
        $pass = (string) ($params['database_password'] ?? '');
        $prefix = (string) ($params['database_prefix'] ?? 'ps_');

        if ($db === '' || $user === '') {
            $this->error('Configuração de BD inválida no parameters.php');

            return self::FAILURE;
        }

        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Throwable $e) {
            $this->error('Falha ao ligar à BD PrestaShop: '.$e->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('truncate')) {
            DB::transaction(function (): void {
                DB::table('payment_method_shipping_carrier')->delete();
                DB::table('shipping_rates')->delete();
                DB::table('shipping_zone_countries')->delete();
                DB::table('payment_methods')->delete();
                DB::table('shipping_carriers')->delete();
                DB::table('shipping_zones')->delete();
            });
        }

        $zoneMap = $this->importZones($pdo, $prefix);
        $carrierMap = $this->importCarriersAndRates($pdo, $prefix, $zoneMap);
        $this->importPaymentMethods($pdo, $prefix, $carrierMap);

        $this->info('Importação concluída.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, int> [ps_zone_id => local_zone_id]
     */
    private function importZones(PDO $pdo, string $prefix): array
    {
        $zoneRows = $pdo->query("SELECT id_zone, name, active FROM `{$prefix}zone`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];

        foreach ($zoneRows as $z) {
            $psId = (int) ($z['id_zone'] ?? 0);
            if ($psId <= 0) {
                continue;
            }

            $zone = ShippingZone::query()->updateOrCreate(
                ['code' => 'PS_ZONE_'.$psId],
                [
                    'name' => (string) ($z['name'] ?? 'Zona '.$psId),
                    'active' => (bool) ((int) ($z['active'] ?? 1)),
                    'position' => $psId,
                ]
            );

            $map[$psId] = (int) $zone->id;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $zoneMap
     * @return array<int, int> [ps_carrier_id => local_carrier_id]
     */
    private function importCarriersAndRates(PDO $pdo, string $prefix, array $zoneMap): array
    {
        $carrierRows = $pdo->query("SELECT id_carrier, name, active, deleted, shipping_method FROM `{$prefix}carrier`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $carrierMap = [];

        foreach ($carrierRows as $c) {
            $deleted = (int) ($c['deleted'] ?? 0);
            $psId = (int) ($c['id_carrier'] ?? 0);
            if ($psId <= 0 || $deleted === 1) {
                continue;
            }

            $delay = null;
            $delayStmt = $pdo->prepare("SELECT delay FROM `{$prefix}carrier_lang` WHERE id_carrier = :id LIMIT 1");
            $delayStmt->execute(['id' => $psId]);
            $delayRow = $delayStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($delayRow) && isset($delayRow['delay'])) {
                $delay = trim((string) $delayRow['delay']) ?: null;
            }

            $rateBasis = ((int) ($c['shipping_method'] ?? 1) === 2) ? 'price' : 'weight';

            $carrier = ShippingCarrier::query()->updateOrCreate(
                ['code' => 'PS_CARRIER_'.$psId],
                [
                    'name' => (string) ($c['name'] ?? 'Carrier '.$psId),
                    'rate_basis' => $rateBasis,
                    'transit_delay' => $delay,
                    'active' => (bool) ((int) ($c['active'] ?? 1)),
                    'position' => $psId,
                ]
            );

            $carrierMap[$psId] = (int) $carrier->id;
        }

        $deliveryRows = $pdo->query("SELECT id_carrier, id_zone, id_range_price, id_range_weight, price FROM `{$prefix}delivery`")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($deliveryRows as $d) {
            $psCarrierId = (int) ($d['id_carrier'] ?? 0);
            $psZoneId = (int) ($d['id_zone'] ?? 0);

            if (! isset($carrierMap[$psCarrierId]) || ! isset($zoneMap[$psZoneId])) {
                continue;
            }

            $calcType = 'price';
            $rangeFrom = 0.0;
            $rangeTo = null;

            $rangePriceId = (int) ($d['id_range_price'] ?? 0);
            $rangeWeightId = (int) ($d['id_range_weight'] ?? 0);

            if ($rangeWeightId > 0) {
                $calcType = 'weight';
                [$rangeFrom, $rangeTo] = $this->fetchRange($pdo, "{$prefix}range_weight", $rangeWeightId);
            } elseif ($rangePriceId > 0) {
                $calcType = 'price';
                [$rangeFrom, $rangeTo] = $this->fetchRange($pdo, "{$prefix}range_price", $rangePriceId);
            }

            ShippingRate::query()->updateOrCreate(
                [
                    'shipping_carrier_id' => $carrierMap[$psCarrierId],
                    'shipping_zone_id' => $zoneMap[$psZoneId],
                    'calc_type' => $calcType,
                    'range_from' => $rangeFrom,
                    'range_to' => $rangeTo,
                ],
                [
                    'price_ex_vat' => (float) ($d['price'] ?? 0),
                    'handling_fee_ex_vat' => 0,
                    'active' => true,
                ]
            );
        }

        return $carrierMap;
    }

    /**
     * @param  array<int, int>  $carrierMap
     */
    private function importPaymentMethods(PDO $pdo, string $prefix, array $carrierMap): void
    {
        $moduleRows = $pdo->query("SELECT id_module, name, active FROM `{$prefix}module`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $paymentLike = static fn (string $name): bool => (bool) preg_match('/(pay|checkout|bank|wire|mbway|multibanco|card|stripe|paypal)/i', $name);

        foreach ($moduleRows as $m) {
            $psId = (int) ($m['id_module'] ?? 0);
            $name = (string) ($m['name'] ?? '');

            if ($psId <= 0 || $name === '' || ! $paymentLike($name)) {
                continue;
            }

            $method = PaymentMethod::query()->updateOrCreate(
                ['code' => 'PS_MODULE_'.$name],
                [
                    'name' => ucwords(str_replace(['_', '-'], ' ', $name)),
                    'provider' => 'PrestaShop module',
                    'fee_type' => 'none',
                    'fee_value' => 0,
                    'active' => (bool) ((int) ($m['active'] ?? 1)),
                    'position' => $psId,
                    'meta' => ['prestashop_module_id' => $psId, 'prestashop_module' => $name],
                ]
            );

            $stmt = $pdo->prepare("SELECT id_carrier FROM `{$prefix}module_carrier` WHERE id_module = :id");
            $stmt->execute(['id' => $psId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $localCarrierIds = [];

            foreach ($rows as $r) {
                $psCarrierId = (int) ($r['id_carrier'] ?? 0);
                if (isset($carrierMap[$psCarrierId])) {
                    $localCarrierIds[] = $carrierMap[$psCarrierId];
                }
            }

            if (count($localCarrierIds) > 0) {
                $method->carriers()->syncWithoutDetaching(array_values(array_unique($localCarrierIds)));
            }
        }
    }

    /**
     * @return array{0: float, 1: float|null}
     */
    private function fetchRange(PDO $pdo, string $table, int $id): array
    {
        $idField = str_contains($table, 'range_price') ? 'id_range_price' : 'id_range_weight';
        $stmt = $pdo->prepare("SELECT delimiter1, delimiter2 FROM `{$table}` WHERE {$idField} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! is_array($row)) {
            return [0.0, null];
        }

        $from = (float) ($row['delimiter1'] ?? 0);
        $toRaw = $row['delimiter2'] ?? null;
        $to = is_numeric($toRaw) ? (float) $toRaw : null;

        return [$from, $to];
    }
}
