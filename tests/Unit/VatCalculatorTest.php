<?php

namespace Tests\Unit;

use App\Enums\VatCode;
use App\Services\Billing\VatCalculator;
use PHPUnit\Framework\TestCase;

class VatCalculatorTest extends TestCase
{
    public function test_exclusive_standard_vat_for_three_hours(): void
    {
        $line = (new VatCalculator)->calculate('3', 65000, false, VatCode::Standard);

        $this->assertSame(195000, $line->netCents);
        $this->assertSame(29250, $line->vatCents);
        $this->assertSame(224250, $line->totalCents);
    }

    public function test_inclusive_standard_vat_extracts_fifteen_percent(): void
    {
        $line = (new VatCalculator)->calculate('1', 115000, true, VatCode::Standard);

        $this->assertSame(100000, $line->netCents);
        $this->assertSame(15000, $line->vatCents);
        $this->assertSame(115000, $line->totalCents);
    }

    public function test_zero_rated_and_exempt_charge_no_vat(): void
    {
        $calculator = new VatCalculator;
        $zero = $calculator->calculate('2', 5000, false, VatCode::Zero);
        $exempt = $calculator->calculate('2', 5000, true, VatCode::Exempt);

        $this->assertSame(0, $zero->vatCents);
        $this->assertSame(10000, $zero->totalCents);
        $this->assertSame(0, $exempt->vatCents);
        $this->assertSame(10000, $exempt->totalCents);
        $this->assertNotSame($zero->vatBasisPoints, 1500);
    }

    public function test_half_hours_round_half_up(): void
    {
        $line = (new VatCalculator)->calculate('1.5', 10000, false, VatCode::Standard);

        $this->assertSame(15000, $line->netCents);
        $this->assertSame(2250, $line->vatCents);
        $this->assertSame(17250, $line->totalCents);
    }
}
