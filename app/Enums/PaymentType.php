<?php

namespace App\Enums;

enum PaymentType: string
{
    case Sale = 'sale';
    case CustomerPayment = 'customer_payment';
    case Refund = 'refund';
    case SupplierPayment = 'supplier_payment';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sale payment',
            self::CustomerPayment => 'Customer payment',
            self::Refund => 'Refund',
            self::SupplierPayment => 'Supplier payment',
        };
    }

    public function direction(): string
    {
        return match ($this) {
            self::Sale, self::CustomerPayment => 'in',
            self::Refund, self::SupplierPayment => 'out',
        };
    }
}
