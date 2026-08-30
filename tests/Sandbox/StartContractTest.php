<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Sandbox;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\Request\Invoice;
use CodeConjure\SimplePay\Request\StartRequest;
use CodeConjure\SimplePay\Request\Urls;
use PHPUnit\Framework\Attributes\Group;

#[Group('sandbox')]
final class StartContractTest extends SandboxTestCase
{
    public function testTheSandboxAcceptsOurSignatureAndReturnsAPaymentUrl(): void
    {
        $orderRef = $this->orderRef();

        $response = $this->client()->start(new StartRequest(
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

        self::assertSame($orderRef, $response->orderRef);
        self::assertNotSame('', $response->transactionId);
        self::assertStringStartsWith('https://', $response->paymentUrl);
        self::assertSame(1000, $response->total->minorUnits);

        $this->record('start', [
            'orderRef' => $response->orderRef,
            'transactionId' => $response->transactionId,
            'merchant' => $response->merchant,
            'paymentUrl' => $response->paymentUrl,
            'total' => $response->total->toApiValue(),
            'currency' => $response->total->currency->value,
            'timeout' => $response->timeout?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
