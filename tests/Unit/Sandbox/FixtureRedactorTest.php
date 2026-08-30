<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Sandbox;

use CodeConjure\SimplePay\Tests\Sandbox\FixtureRedactor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FixtureRedactor::class)]
final class FixtureRedactorTest extends TestCase
{
    public function testRemovesCustomerAndInvoiceFieldsAtTheTopLevel(): void
    {
        $redacted = FixtureRedactor::redact([
            'orderRef' => 'ORDER-1',
            'customer' => 'Teszt Elek',
            'customerEmail' => 'teszt@example.com',
            'invoice' => ['name' => 'Teszt Elek', 'city' => 'Budapest'],
        ]);

        self::assertSame(['orderRef' => 'ORDER-1'], $redacted);
    }

    public function testRemovesSaltAtTheTopLevel(): void
    {
        $redacted = FixtureRedactor::redact([
            'merchant' => 'PUBLICTESTHUF',
            'salt' => 'giMQEuUuDuLhR4aNdVl7nKfIgypkw4Ut',
        ]);

        self::assertSame(['merchant' => 'PUBLICTESTHUF'], $redacted);
    }

    public function testRemovesSensitiveFieldsInsideNestedTransactions(): void
    {
        $redacted = FixtureRedactor::redact([
            'salt' => 'top-level-salt',
            'merchant' => 'PUBLICTESTHUF',
            'transactions' => [
                [
                    'salt' => 'nested-salt',
                    'merchant' => 'PUBLICTESTHUF',
                    'orderRef' => 'ORDER-1',
                    'customer' => 'Teszt Elek',
                    'customerEmail' => 'teszt@example.com',
                    'invoice' => ['name' => 'Teszt Elek'],
                    'status' => 'INIT',
                ],
            ],
        ]);

        self::assertSame([
            'merchant' => 'PUBLICTESTHUF',
            'transactions' => [
                [
                    'merchant' => 'PUBLICTESTHUF',
                    'orderRef' => 'ORDER-1',
                    'status' => 'INIT',
                ],
            ],
        ], $redacted);
    }

    public function testLeavesUnrelatedFieldsUntouched(): void
    {
        $redacted = FixtureRedactor::redact([
            'orderRef' => 'ORDER-1',
            'total' => 1000,
            'currency' => 'HUF',
        ]);

        self::assertSame([
            'orderRef' => 'ORDER-1',
            'total' => 1000,
            'currency' => 'HUF',
        ], $redacted);
    }
}
