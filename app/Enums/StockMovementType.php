<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case PurchaseCancel = 'purchase_cancel';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case ExpiredWriteOff = 'expired_writeoff';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening stock',
            self::Purchase => 'Purchase',
            self::PurchaseCancel => 'Purchase cancelled',
            self::Sale => 'Sale',
            self::SaleReturn => 'Sales return',
            self::AdjustmentIn => 'Adjustment (+)',
            self::AdjustmentOut => 'Adjustment (−)',
            self::ExpiredWriteOff => 'Expired write-off',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Opening, self::Purchase, self::SaleReturn, self::AdjustmentIn => 'green',
            self::Sale => 'blue',
            self::PurchaseCancel, self::AdjustmentOut, self::ExpiredWriteOff => 'red',
        };
    }
}
