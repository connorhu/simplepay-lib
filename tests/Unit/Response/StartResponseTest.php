<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Response;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Response\StartResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StartResponse::class)]
final class StartResponseTest extends TestCase
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
            'timeout' => '2026-08-30T12:30:00+02:00',
            'total' => 1000,
            'paymentUrl' => 'https://sandbox.simplepay.hu/pay/pay/xyz',
        ];
    }

    public function testItReadsEveryField(): void
    {
        $response = StartResponse::fromPayload(self::payload());

        self::assertSame('PUBLICTESTHUF', $response->merchant);
        self::assertSame('ORDER-1', $response->orderRef);
        self::assertSame('99999999', $response->transactionId);
        self::assertSame('https://sandbox.simplepay.hu/pay/pay/xyz', $response->paymentUrl);
        self::assertSame(1000, $response->total->minorUnits);
        self::assertSame(Currency::HUF, $response->total->currency);
    }

    public function testTransactionIdBecomesAString(): void
    {
        self::assertSame('string', gettype(StartResponse::fromPayload(self::payload())->transactionId));
    }

    public function testAMissingPaymentUrlIsLoud(): void
    {
        $payload = self::payload();
        unset($payload['paymentUrl']);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('paymentUrl');

        StartResponse::fromPayload($payload);
    }

    public function testAMissingTimeoutIsTolerated(): void
    {
        $payload = self::payload();
        unset($payload['timeout']);

        self::assertNull(StartResponse::fromPayload($payload)->timeout);
    }
}
