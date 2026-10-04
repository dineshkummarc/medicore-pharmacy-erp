<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Support\Barcode;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_converts_to_and_from_cents_without_float_drift(): void
    {
        $this->assertSame(1999, Money::toCents('19.99'));
        $this->assertSame(30, Money::toCents(0.1 + 0.2));
        $this->assertSame(0, Money::toCents(null));
        $this->assertSame('19.99', Money::fromCents(1999));
        $this->assertSame('-0.05', Money::fromCents(-5));
    }

    public function test_percentage_rounds_half_up_to_the_cent(): void
    {
        $this->assertSame(50, Money::percentOf(1000, 5));
        $this->assertSame(17, Money::percentOf(333, 5));   // 16.65 → 17
        $this->assertSame(0, Money::percentOf(1000, 0));
    }

    public function test_payment_status_from_amounts(): void
    {
        $this->assertSame(PaymentStatus::Paid, PaymentStatus::fromAmounts(1000, 1000));
        $this->assertSame(PaymentStatus::Partial, PaymentStatus::fromAmounts(1000, 1));
        $this->assertSame(PaymentStatus::Unpaid, PaymentStatus::fromAmounts(1000, 0));
        $this->assertSame(PaymentStatus::Paid, PaymentStatus::fromAmounts(0, 0));
    }

    public function test_code128_checksum_and_svg(): void
    {
        // START B (104) + "A"(33) + "1"(17) → checksum (104 + 33 + 34) % 103 = 68
        $this->assertSame([104, 33, 17, 68, 106], Barcode::code128Values('A1'));

        $svg = Barcode::code128Svg('<x>');
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('<x>', $svg, 'label text must be escaped');
    }
}
