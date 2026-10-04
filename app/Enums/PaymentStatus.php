<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Unpaid = 'unpaid';

    public static function fromAmounts(int $totalCents, int $paidCents): self
    {
        return match (true) {
            $paidCents >= $totalCents => self::Paid,
            $paidCents > 0 => self::Partial,
            default => self::Unpaid,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::Partial => 'amber',
            self::Unpaid => 'red',
        };
    }
}
