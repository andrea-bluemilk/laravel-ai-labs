<?php

namespace App\BlueMilk\Enums;

enum OrderState: string
{
    case CREATED = 'new';
    case PAID = 'paid';
    case DELIVERY_REQUESTED = 'req_delivery';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case RETURNED = 'returned';

    public function description(): string
    {
        return match ($this) {
            self::CREATED => 'Attesa pagamento',
            self::PAID => 'Pagato',
            self::DELIVERY_REQUESTED => 'Richiesta presa',
            self::SHIPPED => 'In consegna',
            self::DELIVERED => 'Consegnato',
            self::FAILED => 'Fallito',
            self::RETURNED => 'Restituito',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'orange',
            self::PAID => 'blue',
            self::DELIVERY_REQUESTED => 'yellow',
            self::SHIPPED => 'orange',
            self::DELIVERED => 'emerald',
            self::FAILED => 'red',
            self::RETURNED => 'gray',

        };
    }
}
