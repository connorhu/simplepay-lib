<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Client;
use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Environment;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\Request\QueryRequest;
use CodeConjure\SimplePay\Request\RefundRequest;
use CodeConjure\SimplePay\Signature;
use CodeConjure\SimplePay\TransactionStatus;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Client::class)]
final class ClientOperationsTest extends TestCase
{
    private const string SECRET = 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6';

    private MockClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockClient();
    }

    private function client(): Client
    {
        $factory = new Psr17Factory();

        return new Client(
            new Config('PUBLICTESTHUF', self::SECRET, Environment::Sandbox),
            $this->httpClient,
            $factory,
            $factory,
        );
    }

    /** @param array<string, mixed> $payload */
    private function signedResponse(array $payload): Response
    {
        $body = json_encode($payload, \JSON_THROW_ON_ERROR);

        return new Response(200, ['Signature' => new Signature(self::SECRET)->sign($body)], $body);
    }

    public function testQueryHitsTheQueryEndpoint(): void
    {
        $this->httpClient->addResponse($this->signedResponse(['totalCount' => 0, 'transactions' => []]));

        $this->client()->query(new QueryRequest(orderRefs: ['ORDER-1']));

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        self::assertSame('https://sandbox.simplepay.hu/payment/v2/query', (string) $request->getUri());
    }

    public function testQuerySendsOrderRefsAsAList(): void
    {
        $this->httpClient->addResponse($this->signedResponse(['totalCount' => 0, 'transactions' => []]));

        $this->client()->query(new QueryRequest(orderRefs: ['ORDER-1']));

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        $sent = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($sent);
        self::assertSame(['ORDER-1'], $sent['orderRefs']);
        self::assertArrayNotHasKey('order_ref', $sent);
        self::assertArrayNotHasKey('transaction_id', $sent);
    }

    public function testQueryReadsTheStatusFromTheTransactionsList(): void
    {
        $this->httpClient->addResponse($this->signedResponse([
            'totalCount' => 1,
            'transactions' => [[
                'merchant' => 'PUBLICTESTHUF',
                'orderRef' => 'ORDER-1',
                'transactionId' => 99999999,
                'status' => 'FINISHED',
                'total' => 1000,
                'currency' => 'HUF',
            ]],
        ]));

        $response = $this->client()->query(new QueryRequest(orderRefs: ['ORDER-1']));

        $transaction = $response->first();
        self::assertNotNull($transaction);
        self::assertSame(TransactionStatus::Finished, $transaction->status);
        self::assertTrue($transaction->status->isSuccessful());
    }

    public function testRefundHitsTheRefundEndpoint(): void
    {
        $this->httpClient->addResponse($this->signedResponse([
            'merchant' => 'PUBLICTESTHUF',
            'orderRef' => 'ORDER-1',
            'currency' => 'HUF',
            'transactionId' => 99999999,
            'refundTransactionId' => 88888888,
            'refundTotal' => 1000,
            'remainingTotal' => 0,
        ]));

        $response = $this->client()->refund(new RefundRequest(
            refundTotal: Money::fromMinorUnits(1000, Currency::HUF),
            orderRef: 'ORDER-1',
        ));

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        self::assertSame('https://sandbox.simplepay.hu/payment/v2/refund', (string) $request->getUri());
        self::assertSame('88888888', $response->refundTransactionId);
        self::assertSame(1000, $response->refundTotal->minorUnits);
        self::assertSame(0, $response->remainingTotal->minorUnits);
    }

    public function testRefundSendsRefundTotal(): void
    {
        $this->httpClient->addResponse($this->signedResponse([
            'merchant' => 'PUBLICTESTHUF',
            'orderRef' => 'ORDER-1',
            'currency' => 'HUF',
            'transactionId' => 99999999,
            'refundTotal' => 500,
            'remainingTotal' => 0,
        ]));

        $this->client()->refund(new RefundRequest(
            refundTotal: Money::fromMinorUnits(500, Currency::HUF),
            orderRef: 'ORDER-1',
        ));

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        $sent = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($sent);
        self::assertSame('500', $sent['refundTotal']);
        self::assertSame('HUF', $sent['currency']);
    }
}
