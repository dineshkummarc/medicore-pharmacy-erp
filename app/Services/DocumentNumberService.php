<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free sequential document numbers (invoices, purchases, returns).
 *
 * The sequence row is locked FOR UPDATE inside the caller's transaction, so
 * concurrent sales serialize on it and a rolled-back sale releases its number.
 */
class DocumentNumberService
{
    public const INVOICE = 'invoice';

    public const PURCHASE = 'purchase';

    public const RETURN = 'return';

    public function next(string $type): string
    {
        return DB::transaction(function () use ($type) {
            $sequence = $this->lockedSequence($type);
            $number = (int) $sequence->next_number;

            $sequence->forceFill(['next_number' => $number + 1])->save();

            return $this->prefix($type).str_pad((string) $number, 6, '0', STR_PAD_LEFT);
        });
    }

    private function lockedSequence(string $type): DocumentSequence
    {
        $sequence = DocumentSequence::query()->where('type', $type)->lockForUpdate()->first();

        if ($sequence) {
            return $sequence;
        }

        try {
            DB::transaction(fn () => DocumentSequence::query()->create(['type' => $type, 'next_number' => 1]));
        } catch (UniqueConstraintViolationException) {
            // Another request created it first; fall through and lock it.
        }

        return DocumentSequence::query()->where('type', $type)->lockForUpdate()->firstOrFail();
    }

    private function prefix(string $type): string
    {
        return (string) match ($type) {
            self::INVOICE => settings('invoice_prefix', 'INV-'),
            self::PURCHASE => settings('purchase_prefix', 'PUR-'),
            self::RETURN => settings('return_prefix', 'RET-'),
            default => strtoupper($type).'-',
        };
    }
}
