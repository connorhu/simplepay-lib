<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Language;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\ReturnEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentMethod::class)]
#[CoversClass(Language::class)]
#[CoversClass(ReturnEvent::class)]
final class EnumParsingTest extends TestCase
{
    public function testPaymentMethodParses(): void
    {
        self::assertSame(PaymentMethod::Card, PaymentMethod::fromApi('CARD'));
        self::assertSame(PaymentMethod::Wire, PaymentMethod::fromApi('WIRE'));
    }

    public function testUnknownPaymentMethodThrows(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        PaymentMethod::fromApi('BITCOIN');
    }

    public function testReturnEventParses(): void
    {
        self::assertSame(ReturnEvent::Success, ReturnEvent::fromApi('SUCCESS'));
        self::assertSame(ReturnEvent::Timeout, ReturnEvent::fromApi('TIMEOUT'));
    }

    public function testUnknownReturnEventThrows(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        ReturnEvent::fromApi('MAYBE');
    }

    public function testLanguagesAreUppercaseTwoLetterCodes(): void
    {
        foreach (Language::cases() as $language) {
            self::assertMatchesRegularExpression('/^[A-Z]{2}$/', $language->value);
        }
    }

    public function testHungarianIsAvailable(): void
    {
        self::assertSame('HU', Language::Hu->value);
    }
}
