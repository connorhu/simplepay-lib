<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Request;

use CodeConjure\SimplePay\Exception\ConfigurationException;
use CodeConjure\SimplePay\Request\QueryRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryRequest::class)]
final class QueryRequestTest extends TestCase
{
    public function testTransactionIdsAreSentAsAList(): void
    {
        $payload = new QueryRequest(transactionIds: ['99999999'])->toPayload();

        self::assertSame(['99999999'], $payload['transactionIds']);
        self::assertArrayNotHasKey('orderRefs', $payload);
    }

    public function testOrderRefsAreSentAsAList(): void
    {
        $payload = new QueryRequest(orderRefs: ['ORDER-1', 'ORDER-2'])->toPayload();

        self::assertSame(['ORDER-1', 'ORDER-2'], $payload['orderRefs']);
        self::assertArrayNotHasKey('transactionIds', $payload);
    }

    public function testFlagsAreOmittedWhenFalse(): void
    {
        $payload = new QueryRequest(orderRefs: ['ORDER-1'])->toPayload();

        self::assertArrayNotHasKey('detailed', $payload);
        self::assertArrayNotHasKey('refunds', $payload);
    }

    public function testFlagsAreSentWhenTrue(): void
    {
        $payload = new QueryRequest(orderRefs: ['ORDER-1'], detailed: true, refunds: true)->toPayload();

        self::assertTrue($payload['detailed']);
        self::assertTrue($payload['refunds']);
    }

    public function testAnEmptyQueryIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        new QueryRequest();
    }
}
