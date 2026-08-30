<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Response;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Response\RefundResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundResponse::class)]
final class RefundResponseTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function payload(): array
    {
        return [
            'salt' => 'abcdefghijklmnopqrstuvwxyz012345',
            'merchant' => 'PUBLICTESTHUF',
            'orderRef' => 'ORDER-1',
            'currency' => 'HUF',
            'transactionId' => 99999999,
            'refundTransactionId' => 88888888,
            'refundTotal' => 5,
            'remainingTotal' => 10,
        ];
    }

    public function testItReadsTheRefundAmountsAsMoney(): void
    {
        $response = RefundResponse::fromPayload(self::payload());

        self::assertSame('99999999', $response->transactionId);
        self::assertSame('88888888', $response->refundTransactionId);
        self::assertSame(5, $response->refundTotal->minorUnits);
        self::assertSame(Currency::HUF, $response->refundTotal->currency);
        self::assertSame(10, $response->remainingTotal->minorUnits);
        self::assertSame(Currency::HUF, $response->remainingTotal->currency);
    }

    public function testRefundTransactionIdMayBeMissing(): void
    {
        $payload = self::payload();
        unset($payload['refundTransactionId']);

        self::assertNull(RefundResponse::fromPayload($payload)->refundTransactionId);
    }

    public function testAMissingCurrencyIsLoud(): void
    {
        $payload = self::payload();
        unset($payload['currency']);

        $this->expectException(UnexpectedResponseException::class);

        RefundResponse::fromPayload($payload);
    }

    public function testAMissingRefundTotalIsLoud(): void
    {
        $payload = self::payload();
        unset($payload['refundTotal']);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('refundTotal');

        RefundResponse::fromPayload($payload);
    }

    public function testAMissingRemainingTotalIsLoud(): void
    {
        $payload = self::payload();
        unset($payload['remainingTotal']);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('remainingTotal');

        RefundResponse::fromPayload($payload);
    }
}
