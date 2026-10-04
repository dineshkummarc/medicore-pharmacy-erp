<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Completed = 'completed';
    case PartiallyReturned = 'partially_returned';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::PartiallyReturned => 'Partially returned',
            self::Returned => 'Returned',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Completed => 'green',
            self::PartiallyReturned => 'amber',
            self::Returned => 'gray',
        };
    }
}
