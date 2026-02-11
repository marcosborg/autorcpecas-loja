<?php

namespace App\Console\Commands;

use App\Models\PaymentMethod;
use App\Models\ShippingCarrier;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCountry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class ImportPrestashopCommerceCommand extends Command
{
    protected $signature = 'prestashop:import-commerce
        {--parameters=C:\Users\sara.borges\Desktop\autorcpecasprestahop\app\config\parameters.php : Caminho do parameters.php}
        {--host= : Override host da BD PrestaShop}
        {--port= : Override porto da BD PrestaShop}
        {--dbname= : Override nome da BD PrestaShop}
        {--user= : Override utilizador da BD PrestaShop}
        {--password= : Override password da BD PrestaShop}
        {--prefix= : Override prefixo de tabelas (ex: ps_)}
        {--target-database=sandbox : Ligacao Laravel de destino (sandbox|production)}
        {--shipping-only : Importa apenas transportadoras/zonas/tarifas}
        {--truncate : Limpa tabelas de shipping/payment antes de importar}';

    protected $description = 'Importa transportadoras e modulos de pagamento da base PrestaShop';

    public function handle(): int
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
        $targetDatabase = (string) ($this->option('target-database') ?: 'sandbox');
        $shippingOnly = (bool) $this->option('shipping-only');

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

        if ((bool) $this->option('truncate')) {
            DB::connection($targetDatabase)->transaction(function () use ($targetDatabase, $shippingOnly): void {
                $target = DB::connection($targetDatabase);

                if (! $shippingOnly) {
                    $target->table('payment_method_shipping_carrier')->delete();
                }

                $target->table('shipping_rates')->delete();
                $target->table('shipping_zone_countries')->delete();

                if (! $shippingOnly) {
                    $target->table('payment_methods')->delete();
                }

                $target->table('shipping_carriers')->delete();
                $target->table('shipping_zones')->delete();
            });
        }

        $zoneMap = $this->importZones($pdo, $prefix, $targetDatabase);
        $carrierMap = $this->importCarriersAndRates($pdo, $prefix, $zoneMap, $targetDatabase);

        if (! $shippingOnly) {
            $this->importPaymentMethods($pdo, $prefix, $carrierMap, $targetDatabase);
        }

        $this->info('Importacao concluida.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, int> [ps_zone_id => local_zone_id]
     */
    private function importZones(PDO $pdo, string $prefix, string $targetDatabase): array
    {
        $zoneRows = $pdo->query("SELECT id_zone, name, active FROM `{$prefix}zone`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];

        foreach ($zoneRows as $z) {
            $psId = (int) ($z['id_zone'] ?? 0);
            if ($psId <= 0) {
                continue;
            }

            $zone = ShippingZone::on($targetDatabase)->updateOrCreate(
                ['code' => 'PS_ZONE_'.$psId],
                [
                    'name' => (string) ($z['name'] ?? 'Zona '.$psId),
                    'active' => (bool) ((int) ($z['active'] ?? 1)),
                    'position' => $psId,
                ]
            );

            $map[$psId] = (int) $zone->id;
        }

        $countries = $pdo->query("SELECT iso_code, id_zone FROM `{$prefix}country`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($countries as $country) {
            $psZoneId = (int) ($country['id_zone'] ?? 0);
            $iso = mb_strtoupper(trim((string) ($country['iso_code'] ?? '')), 'UTF-8');
            if ($iso === '' || ! isset($map[$psZoneId])) {
                continue;
            }

            ShippingZoneCountry::on($targetDatabase)->updateOrCreate([
                'shipping_zone_id' => $map[$psZoneId],
                'country_iso2' => $iso,
            ]);
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $zoneMap
     * @return array<int, int> [ps_carrier_id => local_carrier_id]
     */
    private function importCarriersAndRates(PDO $pdo, string $prefix, array $zoneMap, string $targetDatabase): array
    {
        $carrierRows = $pdo->query(
            "SELECT id_carrier, name, active, deleted, shipping_method, is_free, need_range, range_behavior
             FROM `{$prefix}carrier`"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
            $isFree = (bool) ((int) ($c['is_free'] ?? 0));
            $needRange = (bool) ((int) ($c['need_range'] ?? 1));

            $carrier = ShippingCarrier::on($targetDatabase)->updateOrCreate(
                ['code' => 'PS_CARRIER_'.$psId],
                [
                    'name' => (string) ($c['name'] ?? 'Carrier '.$psId),
                    'rate_basis' => $rateBasis,
                    'transit_delay' => $delay,
                    'is_free' => $isFree,
                    'need_range' => $needRange,
                    'range_behavior' => (int) ($c['range_behavior'] ?? 1),
                    'is_pickup' => $isFree,
                    'active' => (bool) ((int) ($c['active'] ?? 1)),
                    'position' => $psId,
                ]
            );

            $carrierMap[$psId] = (int) $carrier->id;
        }

        $deliveryRows = $pdo->query(
            "SELECT id_carrier, id_zone, id_range_price, id_range_weight, price
             FROM `{$prefix}delivery`"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

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

            ShippingRate::on($targetDatabase)->updateOrCreate(
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

        $carrierZoneRows = $pdo->query("SELECT id_carrier, id_zone FROM `{$prefix}carrier_zone`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($carrierZoneRows as $carrierZone) {
            $psCarrierId = (int) ($carrierZone['id_carrier'] ?? 0);
            $psZoneId = (int) ($carrierZone['id_zone'] ?? 0);
            if (! isset($carrierMap[$psCarrierId], $zoneMap[$psZoneId])) {
                continue;
            }

            $carrier = ShippingCarrier::on($targetDatabase)->find($carrierMap[$psCarrierId]);
            if (! $carrier || ! $carrier->is_free || $carrier->need_range) {
                continue;
            }

            ShippingRate::on($targetDatabase)->updateOrCreate(
                [
                    'shipping_carrier_id' => $carrier->id,
                    'shipping_zone_id' => $zoneMap[$psZoneId],
                    'calc_type' => $carrier->rate_basis,
                    'range_from' => 0,
                    'range_to' => null,
                ],
                [
                    'price_ex_vat' => 0,
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
    private function importPaymentMethods(PDO $pdo, string $prefix, array $carrierMap, string $targetDatabase): void
    {
        $moduleRows = $pdo->query("SELECT id_module, name, active FROM `{$prefix}module`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $paymentLike = static fn (string $name): bool => (bool) preg_match('/(pay|checkout|bank|wire|mbway|multibanco|card|stripe|paypal)/i', $name);

        foreach ($moduleRows as $m) {
            $psId = (int) ($m['id_module'] ?? 0);
            $name = (string) ($m['name'] ?? '');

            if ($psId <= 0 || $name === '' || ! $paymentLike($name)) {
                continue;
            }

            $method = PaymentMethod::on($targetDatabase)->updateOrCreate(
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

