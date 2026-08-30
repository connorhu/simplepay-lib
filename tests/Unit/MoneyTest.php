<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Money::class)]
#[CoversClass(Currency::class)]
final class MoneyTest extends TestCase
{
    public function testHufHasNoDecimals(): void
    {
        self::assertSame(0, Currency::HUF->exponent());
        self::assertSame('1000', Money::fromMinorUnits(1000, Currency::HUF)->toApiValue());
    }

    public function testEuroHasTwoDecimals(): void
    {
        self::assertSame(2, Currency::EUR->exponent());
        self::assertSame('10.50', Money::fromMinorUnits(1050, Currency::EUR)->toApiValue());
    }

    public function testEuroPadsASingleDecimal(): void
    {
        self::assertSame('10.05', Money::fromMinorUnits(1005, Currency::EUR)->toApiValue());
    }

    public function testEuroKeepsWholeAmountsFormatted(): void
    {
        self::assertSame('10.00', Money::fromMinorUnits(1000, Currency::EUR)->toApiValue());
    }

    public function testZeroIsFormattedForBothExponents(): void
    {
        self::assertSame('0', Money::fromMinorUnits(0, Currency::HUF)->toApiValue());
        self::assertSame('0.00', Money::fromMinorUnits(0, Currency::EUR)->toApiValue());
    }

    public function testDecimalStringRoundtripsForEuro(): void
    {
        $money = Money::fromDecimalString('10.50', Currency::EUR);

        self::assertSame(1050, $money->minorUnits);
        self::assertSame('10.50', $money->toApiValue());
    }

    public function testDecimalStringWithoutFractionWorksForHuf(): void
    {
        self::assertSame(1000, Money::fromDecimalString('1000', Currency::HUF)->minorUnits);
    }

    public function testHufRejectsAFractionalAmount(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        Money::fromDecimalString('1000.50', Currency::HUF);
    }

    public function testApiValueAcceptsAnInteger(): void
    {
        self::assertSame(1000, Money::fromApiValue(1000, Currency::HUF)->minorUnits);
    }

    public function testApiValueAcceptsADecimalString(): void
    {
        self::assertSame(1050, Money::fromApiValue('10.50', Currency::EUR)->minorUnits);
    }

    public function testApiValueRejectsGarbage(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        Money::fromApiValue('sok pénz', Currency::HUF);
    }

    public function testCurrencyFromApiRejectsAnUnknownCode(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('GBP');

        Currency::fromApi('GBP');
    }
}
