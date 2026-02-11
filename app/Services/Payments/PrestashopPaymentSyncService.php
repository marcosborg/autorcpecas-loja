<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use App\Models\ShippingCarrier;
use PDO;

class PrestashopPaymentSyncService
{
    /**
     * @return array{
     *   created_or_updated: int,
     *   methods: list<string>
     * }
     */
    public function sync(PDO $pdo, string $prefix, string $targetConnection): array
    {
        $configRows = $pdo->query("SELECT name, value FROM `{$prefix}configuration`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $config = [];
        foreach ($configRows as $row) {
            $config[(string) ($row['name'] ?? '')] = (string) ($row['value'] ?? '');
        }

        $moduleRows = $pdo->query("SELECT id_module, name, active FROM `{$prefix}module`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $modules = [];
        foreach ($moduleRows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $modules[$name] = [
                'id' => (int) ($row['id_module'] ?? 0),
                'active' => ((int) ($row['active'] ?? 0)) === 1,
            ];
        }

        $carrierMap = [];
        $localCarriers = ShippingCarrier::on($targetConnection)->get(['id', 'code']);
        foreach ($localCarriers as $carrier) {
            if (preg_match('/^PS_CARRIER_(\d+)$/', (string) $carrier->code, $m)) {
                $carrierMap[(int) $m[1]] = (int) $carrier->id;
            }
        }
        $allLocalCarrierIds = ShippingCarrier::on($targetConnection)->where('active', true)->pluck('id')->map(fn ($v): int => (int) $v)->all();

        $carrierReferenceToLocal = [];
        $psCarriers = $pdo->query("SELECT id_carrier, id_reference, deleted FROM `{$prefix}carrier`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($psCarriers as $psCarrier) {
            if (((int) ($psCarrier['deleted'] ?? 0)) === 1) {
                continue;
            }
            $psCarrierId = (int) ($psCarrier['id_carrier'] ?? 0);
            $reference = (int) ($psCarrier['id_reference'] ?? 0);
            if ($psCarrierId <= 0 || $reference <= 0 || ! isset($carrierMap[$psCarrierId])) {
                continue;
            }
            $carrierReferenceToLocal[$reference] = $carrierMap[$psCarrierId];
        }

        $moduleCarrierMap = [];
        $moduleCarrierRows = $pdo->query("SELECT id_module, id_reference FROM `{$prefix}module_carrier`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($moduleCarrierRows as $row) {
            $moduleId = (int) ($row['id_module'] ?? 0);
            $carrierReference = (int) ($row['id_reference'] ?? 0);
            if ($moduleId <= 0 || $carrierReference <= 0 || ! isset($carrierReferenceToLocal[$carrierReference])) {
                continue;
            }
            $moduleCarrierMap[$moduleId][] = $carrierReferenceToLocal[$carrierReference];
        }

        $methods = [];

        $sibsModule = $modules['sibs'] ?? ['id' => 0, 'active' => false];
        $sibsCommonMeta = [
            'gateway' => 'sibs',
            'client_id' => (string) ($config['SIBS_GENERAL_CLIENTID'] ?? ''),
            'terminal_id' => (string) ($config['SIBS_GENERAL_TERMINALID'] ?? ''),
            'bearer_token' => (string) ($config['SIBS_GENERAL_BEARER'] ?? ''),
            'webhook_secret' => (string) ($config['SIBS_GENERAL_SECRET'] ?? ''),
            'moto' => ((string) ($config['SIBS_GENERAL_MOTO'] ?? '0')) === '1',
        ];

        $ccCards = trim((string) ($config['SIBS_CC_CARDS'] ?? ''));
        $ccActive = ((string) ($config['SIBS_CC_ACTIVE'] ?? '0')) === '1' && $ccCards !== '';
        $methods[] = [
            'code' => 'sibs_card',
            'name' => 'SIBS Cartao',
            'provider' => 'SIBS',
            'active' => $sibsModule['active'] && $ccActive,
            'position' => (int) ($config['SIBS_CC_SORT'] ?? 1),
            'module_id' => (int) $sibsModule['id'],
            'meta' => array_merge($sibsCommonMeta, [
                'method' => 'CARD',
                'server' => (string) ($config['SIBS_CC_SERVER'] ?? 'TEST'),
                'mode' => (string) ($config['SIBS_CC_MODE'] ?? 'DB'),
                'cards' => $ccCards === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $ccCards)))),
            ]),
        ];

        $mbwayActive = ((string) ($config['SIBS_MBWAY_ACTIVE'] ?? '0')) === '1';
        $methods[] = [
            'code' => 'sibs_mbway',
            'name' => 'SIBS MB WAY',
            'provider' => 'SIBS',
            'active' => $sibsModule['active'] && $mbwayActive,
            'position' => (int) ($config['SIBS_MBWAY_SORT'] ?? 2),
            'module_id' => (int) $sibsModule['id'],
            'meta' => array_merge($sibsCommonMeta, [
                'method' => 'MBWAY',
                'server' => (string) ($config['SIBS_MBWAY_SERVER'] ?? 'TEST'),
                'mode' => (string) ($config['SIBS_MBWAY_MODE'] ?? 'DB'),
                'mandate_type' => (string) ($config['SIBS_MBWAY_MANDATETYPE'] ?? ''),
            ]),
        ];

        $multibancoActive = ((string) ($config['SIBS_MULTIBANCO_ACTIVE'] ?? '0')) === '1';
        $methods[] = [
            'code' => 'sibs_multibanco',
            'name' => 'SIBS Referencia Multibanco',
            'provider' => 'SIBS',
            'active' => $sibsModule['active'] && $multibancoActive,
            'position' => (int) ($config['SIBS_MULTIBANCO_SORT'] ?? 3),
            'module_id' => (int) $sibsModule['id'],
            'meta' => array_merge($sibsCommonMeta, [
                'method' => 'REFERENCE',
                'server' => (string) ($config['SIBS_MULTIBANCO_SERVER'] ?? 'TEST'),
                'payment_entity' => (string) ($config['SIBS_MULTIBANCO_PAYMENT_ENTITY'] ?? ''),
                'payment_type' => (string) ($config['SIBS_MULTIBANCO_PAYMENT_TYPE'] ?? ''),
                'payment_value' => (string) ($config['SIBS_MULTIBANCO_PAYMENT_VALUE'] ?? ''),
            ]),
        ];

        $wireModule = $modules['ps_wirepayment'] ?? ($modules['bankwire'] ?? ['id' => 0, 'active' => false]);
        $methods[] = [
            'code' => 'bank_transfer',
            'name' => 'Transferencia Bancaria',
            'provider' => 'Pagamento manual',
            'active' => (bool) $wireModule['active'],
            'position' => 40,
            'module_id' => (int) $wireModule['id'],
            'meta' => [
                'gateway' => 'manual_bank_transfer',
                'owner' => (string) ($config['BANK_WIRE_OWNER'] ?? ''),
                'details' => (string) ($config['BANK_WIRE_DETAILS'] ?? ''),
                'address' => (string) ($config['BANK_WIRE_ADDRESS'] ?? ''),
            ],
        ];

        $createdOrUpdated = 0;
        $methodCodes = [];

        foreach ($methods as $methodData) {
            $existing = PaymentMethod::on($targetConnection)->where('code', $methodData['code'])->first();
            $methodData['meta'] = $this->mergeMetaWithExisting(
                (string) $methodData['code'],
                is_array($methodData['meta']) ? $methodData['meta'] : [],
                $existing?->meta
            );

            $method = PaymentMethod::on($targetConnection)->updateOrCreate(
                ['code' => $methodData['code']],
                [
                    'name' => $methodData['name'],
                    'provider' => $methodData['provider'],
                    'fee_type' => 'none',
                    'fee_value' => 0,
                    'active' => (bool) $methodData['active'],
                    'position' => (int) $methodData['position'],
                    'meta' => $methodData['meta'],
                ]
            );
            $createdOrUpdated++;
            $methodCodes[] = (string) $method->code;

            $carrierIds = [];
            $moduleId = (int) $methodData['module_id'];
            if ($moduleId > 0 && isset($moduleCarrierMap[$moduleId])) {
                $carrierIds = array_values(array_unique(array_map('intval', $moduleCarrierMap[$moduleId])));
            }
            if (count($carrierIds) === 0) {
                $carrierIds = $allLocalCarrierIds;
            }
            $method->carriers()->sync($carrierIds);
        }

        PaymentMethod::on($targetConnection)
            ->whereIn('code', ['mbway', 'multibanco_ref', 'card'])
            ->update(['active' => false]);

        return [
            'created_or_updated' => $createdOrUpdated,
            'methods' => $methodCodes,
        ];
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  mixed  $existingMeta
     * @return array<string, mixed>
     */
    private function mergeMetaWithExisting(string $code, array $incoming, mixed $existingMeta): array
    {
        $existing = is_array($existingMeta) ? $existingMeta : [];

        if (str_starts_with($code, 'sibs_')) {
            foreach (['client_id', 'terminal_id', 'bearer_token', 'webhook_secret'] as $key) {
                $newValue = trim((string) ($incoming[$key] ?? ''));
                $oldValue = trim((string) ($existing[$key] ?? ''));
                if ($newValue === '' && $oldValue !== '') {
                    $incoming[$key] = $oldValue;
                }
            }
        }

        if ($code === 'bank_transfer') {
            foreach (['owner', 'details', 'address'] as $key) {
                $newValue = trim((string) ($incoming[$key] ?? ''));
                $oldValue = trim((string) ($existing[$key] ?? ''));
                if ($newValue === '' && $oldValue !== '') {
                    $incoming[$key] = $oldValue;
                }
            }
        }

        return $incoming;
    }
}
