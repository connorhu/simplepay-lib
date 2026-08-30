<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Request;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\ConfigurationException;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\Request\RefundRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundRequest::class)]
final class RefundRequestTest extends TestCase
{
    public function testPayloadCarriesRefundTotalAndCurrency(): void
    {
        $payload = new RefundRequest(
            refundTotal: Money::fromMinorUnits(1000, Currency::HUF),
            orderRef: 'ORDER-1',
        )->toPayload();

        self::assertSame('1000', $payload['refundTotal']);
        self::assertSame('HUF', $payload['currency']);
        self::assertSame('ORDER-1', $payload['orderRef']);
        self::assertArrayNotHasKey('transactionId', $payload);
    }

    public function testTransactionIdAloneIsEnough(): void
    {
        $payload = new RefundRequest(
            refundTotal: Money::fromMinorUnits(500, Currency::HUF),
            transactionId: '99999999',
        )->toPayload();

        self::assertSame('99999999', $payload['transactionId']);
        self::assertArrayNotHasKey('orderRef', $payload);
    }

    public function testAtLeastOneIdentifierIsRequired(): void
    {
        $this->expectException(ConfigurationException::class);

        new RefundRequest(refundTotal: Money::fromMinorUnits(500, Currency::HUF));
    }
}
