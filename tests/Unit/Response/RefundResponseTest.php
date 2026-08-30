<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Response;

use CodeConjure\SimplePay\Response\RefundResponse;
use CodeConjure\SimplePay\TransactionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundResponse::class)]
final class RefundResponseTest extends TestCase
{
    public function testItReadsTheRefundTransactionId(): void
    {
        $response = RefundResponse::fromPayload([
            'salt' => 'abcdefghijklmnopqrstuvwxyz012345',
            'merchant' => 'PUBLICTESTHUF',
            'orderRef' => 'ORDER-1',
            'transactionId' => 99999999,
            'refundTransactionId' => 88888888,
            'status' => 'REFUND',
        ]);

        self::assertSame('99999999', $response->transactionId);
        self::assertSame('88888888', $response->refundTransactionId);
        self::assertSame(TransactionStatus::Refund, $response->status);
    }

    public function testOptionalFieldsMayBeMissing(): void
    {
        $response = RefundResponse::fromPayload([
            'merchant' => 'PUBLICTESTHUF',
            'orderRef' => 'ORDER-1',
            'transactionId' => 99999999,
        ]);

        self::assertNull($response->refundTransactionId);
        self::assertNull($response->status);
    }
}
