<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Response;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\Response\QueryResponse;
use CodeConjure\SimplePay\TransactionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryResponse::class)]
final class QueryResponseTest extends TestCase
{
    /**
     * @return array{
     *     salt: string,
     *     merchant: string,
     *     totalCount: int,
     *     transactions: list<array<string, mixed>>,
     * }
     */
    private static function payload(): array
    {
        return [
            'salt' => 'abcdefghijklmnopqrstuvwxyz012345',
            'merchant' => 'PUBLICTESTHUF',
            'totalCount' => 2,
            'transactions' => [
                [
                    'merchant' => 'PUBLICTESTHUF',
                    'orderRef' => 'ORDER-1',
                    'transactionId' => 99999999,
                    'status' => 'FINISHED',
                    'total' => 1000,
                    'currency' => 'HUF',
                    'paymentDate' => '2026-08-30T12:05:00+02:00',
                    'method' => 'CARD',
                ],
                [
                    'merchant' => 'PUBLICTESTHUF',
                    'orderRef' => 'ORDER-2',
                    'transactionId' => 99999998,
                    'status' => 'CANCELLED',
                    'currency' => 'HUF',
                ],
            ],
        ];
    }

    public function testTheStatusComesFromInsideTheTransactionsList(): void
    {
        $response = QueryResponse::fromPayload(self::payload());

        self::assertCount(2, $response->transactions);
        self::assertSame(TransactionStatus::Finished, $response->transactions[0]->status);
        self::assertSame(TransactionStatus::Cancelled, $response->transactions[1]->status);
    }

    public function testTotalCountIsRead(): void
    {
        self::assertSame(2, QueryResponse::fromPayload(self::payload())->totalCount);
    }

    public function testFirstReturnsTheFirstTransaction(): void
    {
        self::assertSame('ORDER-1', QueryResponse::fromPayload(self::payload())->first()?->orderRef);
    }

    public function testByOrderRefFindsTheMatchingTransaction(): void
    {
        self::assertSame(
            TransactionStatus::Cancelled,
            QueryResponse::fromPayload(self::payload())->byOrderRef('ORDER-2')?->status,
        );
    }

    public function testByOrderRefReturnsNullWhenAbsent(): void
    {
        self::assertNull(QueryResponse::fromPayload(self::payload())->byOrderRef('ORDER-9'));
    }

    public function testOptionalTransactionFieldsMayBeMissing(): void
    {
        $second = QueryResponse::fromPayload(self::payload())->transactions[1];

        self::assertNull($second->paymentDate);
        self::assertNull($second->method);
        self::assertNull($second->total);
    }

    public function testMethodIsParsed(): void
    {
        self::assertSame(PaymentMethod::Card, QueryResponse::fromPayload(self::payload())->transactions[0]->method);
    }

    public function testAnEmptyResultIsValid(): void
    {
        $response = QueryResponse::fromPayload(['totalCount' => 0, 'transactions' => []]);

        self::assertSame([], $response->transactions);
        self::assertNull($response->first());
    }

    public function testAnUnknownStatusInsideTheListIsLoud(): void
    {
        $payload = self::payload();
        $payload['transactions'][0]['status'] = 'COMPLETE';

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('COMPLETE');

        QueryResponse::fromPayload($payload);
    }
}
