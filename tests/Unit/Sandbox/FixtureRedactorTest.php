<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Sandbox;

use CodeConjure\SimplePay\Tests\Sandbox\FixtureRedactor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FixtureRedactor::class)]
final class FixtureRedactorTest extends TestCase
{
    public function testReplacesCustomerAndInvoiceFieldValuesAtTheTopLevelButKeepsTheKeys(): void
    {
        $redacted = FixtureRedactor::redact([
            'orderRef' => 'ORDER-1',
            'customer' => 'Teszt Elek',
            'customerEmail' => 'teszt@example.com',
            'invoice' => ['name' => 'Teszt Elek', 'city' => 'Budapest'],
        ]);

        self::assertSame([
            'orderRef' => 'ORDER-1',
            'customer' => FixtureRedactor::MARKER,
            'customerEmail' => FixtureRedactor::MARKER,
            'invoice' => [
                'name' => FixtureRedactor::MARKER,
                'city' => FixtureRedactor::MARKER,
            ],
        ], $redacted);
    }

    public function testReplacesSaltAtTheTopLevelButKeepsTheKey(): void
    {
        $redacted = FixtureRedactor::redact([
            'merchant' => 'PUBLICTESTHUF',
            'salt' => 'giMQEuUuDuLhR4aNdVl7nKfIgypkw4Ut',
        ]);

        self::assertSame([
            'merchant' => 'PUBLICTESTHUF',
            'salt' => FixtureRedactor::MARKER,
        ], $redacted);
    }

    public function testReplacesSensitiveFieldValuesInsideNestedTransactionsButKeepsTheKeys(): void
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
            'salt' => FixtureRedactor::MARKER,
            'merchant' => 'PUBLICTESTHUF',
            'transactions' => [
                [
                    'salt' => FixtureRedactor::MARKER,
                    'merchant' => 'PUBLICTESTHUF',
                    'orderRef' => 'ORDER-1',
                    'customer' => FixtureRedactor::MARKER,
                    'customerEmail' => FixtureRedactor::MARKER,
                    'invoice' => ['name' => FixtureRedactor::MARKER],
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

    public function testKeysArePresentAndDistinguishableFromAFieldNeverSentByTheApi(): void
    {
        // A fixture bizonyító ereje pontosan azon múlik, hogy meg lehessen
        // különböztetni "a SimplePay nem küldte" és "mi redaktáltuk" esetét.
        $redacted = FixtureRedactor::redact([
            'merchant' => 'PUBLICTESTHUF',
            'salt' => 'abc123',
        ]);

        self::assertArrayHasKey('salt', $redacted, 'A redaktált mezőnek meg kell maradnia, csak az értéke cserélődik.');
        self::assertArrayNotHasKey('customer', $redacted, 'Egy sosem kapott mező nem jelenhet meg — se valós, se redaktált értékkel.');
    }
}
