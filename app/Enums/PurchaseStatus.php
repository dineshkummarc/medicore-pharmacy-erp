<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function tone(): string
    {
        return $this === self::Received ? 'green' : 'gray';
    }
}
