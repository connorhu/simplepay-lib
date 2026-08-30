<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Sandbox;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\Request\Invoice;
use CodeConjure\SimplePay\Request\QueryRequest;
use CodeConjure\SimplePay\Request\StartRequest;
use CodeConjure\SimplePay\Request\Urls;
use CodeConjure\SimplePay\TransactionStatus;
use PHPUnit\Framework\Attributes\Group;

#[Group('sandbox')]
final class QueryContractTest extends SandboxTestCase
{
    public function testAFreshTransactionCanBeQueriedBackByOrderRef(): void
    {
        $client = $this->client();
        $orderRef = $this->orderRef();

        $client->start(new StartRequest(
            orderRef: $orderRef,
            total: Money::fromMinorUnits(1000, Currency::HUF),
            customerEmail: 'contract-test@example.com',
            invoice: new Invoice('Teszt Elek', 'HU', 'Budapest', '1011', 'Fő utca 1.'),
            urls: new Urls(
                success: 'https://example.com/vissza?e=success',
                fail: 'https://example.com/vissza?e=fail',
                cancel: 'https://example.com/vissza?e=cancel',
                timeout: 'https://example.com/vissza?e=timeout',
            ),
        ));

        $response = $client->query(new QueryRequest(orderRefs: [$orderRef], detailed: true));

        self::assertGreaterThanOrEqual(1, $response->totalCount);

        $transaction = $response->byOrderRef($orderRef);
        self::assertNotNull($transaction, 'A lekérdezés a transactions tömbben adja vissza a tranzakciót.');
        self::assertInstanceOf(TransactionStatus::class, $transaction->status);

        $this->record('query', [
            'totalCount' => $response->totalCount,
            'transactions' => array_map(
                static fn ($item): array => [
                    'merchant' => $item->merchant,
                    'orderRef' => $item->orderRef,
                    'transactionId' => $item->transactionId,
                    'status' => $item->status->value,
                    'total' => $item->total?->toApiValue(),
                    'currency' => $item->total?->currency->value,
                    'method' => $item->method?->value,
                    'paymentDate' => $item->paymentDate?->format(\DateTimeInterface::ATOM),
                ],
                $response->transactions,
            ),
        ]);
    }
}
