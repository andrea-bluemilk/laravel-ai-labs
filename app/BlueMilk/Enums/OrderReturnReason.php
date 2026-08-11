<?php

namespace App\BlueMilk\Enums;

enum OrderReturnReason: string
{
    case WRONG_PRODUCT = 'wrong_product';
    case WRONG_SIZE = 'wrong_size';
    case FAILED_PRODUCT = 'failed_product';
    case WRONG_ORDER = 'wrong_order';
    case OTHER = 'other';

    public function description(): string
    {
        return match ($this) {
            self::WRONG_PRODUCT => 'Prodotto errato',
            self::WRONG_SIZE => 'Taglia errata',
            self::FAILED_PRODUCT => 'Prodotto difettoso',
            self::WRONG_ORDER => 'Ordine errato',
            self::OTHER => 'Altro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WRONG_PRODUCT => 'orange',
            self::WRONG_SIZE => 'blue',
            self::FAILED_PRODUCT => 'red',
            self::WRONG_ORDER => 'yellow',
            self::OTHER => 'gray',

        };
    }
}
