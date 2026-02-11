<?php

namespace App\Support;

class OrderStatuses
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'awaiting_payment' => 'A aguardar pagamento',
            'paid' => 'Paga',
            'processing' => 'Em processamento',
            'shipped' => 'Enviada',
            'completed' => 'Concluida',
            'cancelled' => 'Cancelada',
            'refunded' => 'Reembolsada',
        ];
    }

    public static function label(?string $status): string
    {
        $status = (string) $status;

        return self::options()[$status] ?? $status;
    }
}

