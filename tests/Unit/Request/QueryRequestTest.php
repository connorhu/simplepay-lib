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

    /**
     * A `refunds` kapcsoló szándékosan hiányzik a `QueryRequest`-ből (lásd
     * az osztály docblockját): a hozott extra mezőket a válasz-oldal nem
     * olvassa ki, tehát a kapcsoló bekapcsolása néma ígéret lenne. Ez a
     * teszt lepinneli, hogy a payload sosem tartalmazhat ilyen kulcsot.
     */
    public function testPayloadNeverCarriesTheRefundsFlag(): void
    {
        $payload = new QueryRequest(orderRefs: ['ORDER-1'])->toPayload();

        self::assertArrayNotHasKey('refunds', $payload);
    }

    /**
     * A `detailed: true`-t a `toPayload()` mindig kiküldi, nem publikus
     * opcióként, hanem mert enélkül a SimplePay a `total`/`remainingTotal`
     * mezőket `currency` nélkül küldi vissza (élő sandboxon megfigyelve,
     * Task 13) — a `Transaction::fromPayload()` pedig jogosan hangos hibát
     * dob egy pénznem nélküli összegre. Ez a teszt lepinneli, hogy ez a
     * belső részlet nem vész el egy jövőbeli refaktornál.
     */
    public function testDetailedIsAlwaysSentToGuaranteeCurrency(): void
    {
        $payload = new QueryRequest(orderRefs: ['ORDER-1'])->toPayload();

        self::assertTrue($payload['detailed']);
    }

    public function testAnEmptyQueryIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        new QueryRequest();
    }

    public function testAListOfOnlyBlankTransactionIdsIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        new QueryRequest(transactionIds: ['']);
    }

    public function testBlankEntriesAreDroppedButUsableOnesSurvive(): void
    {
        $payload = new QueryRequest(orderRefs: ['', 'ORDER-1'])->toPayload();

        self::assertSame(['ORDER-1'], $payload['orderRefs']);
    }
}
