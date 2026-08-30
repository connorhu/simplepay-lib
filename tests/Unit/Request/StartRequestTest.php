<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Request;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\ConfigurationException;
use CodeConjure\SimplePay\Language;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\Request\Invoice;
use CodeConjure\SimplePay\Request\StartRequest;
use CodeConjure\SimplePay\Request\Urls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StartRequest::class)]
#[CoversClass(Invoice::class)]
#[CoversClass(Urls::class)]
final class StartRequestTest extends TestCase
{
    private static function urls(): Urls
    {
        return new Urls(
            success: 'https://bolt.hu/vissza?e=success',
            fail: 'https://bolt.hu/vissza?e=fail',
            cancel: 'https://bolt.hu/vissza?e=cancel',
            timeout: 'https://bolt.hu/vissza?e=timeout',
            ipn: 'https://bolt.hu/ipn',
        );
    }

    private static function invoice(): Invoice
    {
        return new Invoice(
            name: 'Teszt Elek',
            country: 'HU',
            city: 'Budapest',
            zip: '1011',
            address: 'Fő utca 1.',
        );
    }

    private static function request(): StartRequest
    {
        return new StartRequest(
            orderRef: 'ORDER-1',
            total: Money::fromMinorUnits(1000, Currency::HUF),
            customerEmail: 'teszt@example.com',
            invoice: self::invoice(),
            urls: self::urls(),
        );
    }

    public function testPayloadUsesCamelCaseSimplePayFieldNames(): void
    {
        $payload = self::request()->toPayload();

        self::assertSame('ORDER-1', $payload['orderRef']);
        self::assertSame('teszt@example.com', $payload['customerEmail']);
        self::assertSame('HUF', $payload['currency']);
        self::assertSame('1000', $payload['total']);
        self::assertSame('HU', $payload['language']);
        self::assertSame(['CARD'], $payload['methods']);
    }

    public function testPayloadCarriesNoSnakeCaseKeys(): void
    {
        $payload = self::request()->toPayload();
        $topLevelKeyCount = count($payload);

        $totalKeyCount = self::assertNoSnakeCaseKeysRecursively($payload);

        // A historikus hiba a beágyazott map-ekben (invoice, url) élt, nem a
        // felső szinten — ha a rekurzió nem néz bele azokba, ez a teszt
        // üresen futna át egy snake_case kulcson is. Ezért bizonyítjuk, hogy
        // ténylegesen több kulcsot vizsgáltunk meg, mint amennyi a felső
        // szinten van.
        self::assertGreaterThan(
            $topLevelKeyCount,
            $totalKeyCount,
            'A rekurzív ellenőrzésnek be kell néznie a beágyazott map-ekbe is, nem csak a felső szintre.',
        );
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return int a megvizsgált kulcsok száma, beleértve a beágyazottakat is
     */
    private static function assertNoSnakeCaseKeysRecursively(array $payload): int
    {
        $count = 0;

        foreach ($payload as $key => $value) {
            self::assertStringNotContainsString('_', (string) $key);
            ++$count;

            if (is_array($value)) {
                $count += self::assertNoSnakeCaseKeysRecursively($value);
            }
        }

        return $count;
    }

    public function testPayloadDoesNotCarryClientManagedFields(): void
    {
        $payload = self::request()->toPayload();

        self::assertArrayNotHasKey('merchant', $payload);
        self::assertArrayNotHasKey('salt', $payload);
        self::assertArrayNotHasKey('sdkVersion', $payload);
    }

    public function testUrlsAreSentAsAMapAndTheIpnGoesOutAsDn(): void
    {
        $urls = self::request()->toPayload()['url'];

        self::assertIsArray($urls);
        self::assertSame('https://bolt.hu/vissza?e=success', $urls['success']);
        self::assertSame('https://bolt.hu/vissza?e=fail', $urls['fail']);
        self::assertSame('https://bolt.hu/vissza?e=cancel', $urls['cancel']);
        self::assertSame('https://bolt.hu/vissza?e=timeout', $urls['timeout']);
        self::assertSame('https://bolt.hu/ipn', $urls['dn']);
    }

    public function testInvoiceIsNested(): void
    {
        $invoice = self::request()->toPayload()['invoice'];

        self::assertIsArray($invoice);
        self::assertSame('Teszt Elek', $invoice['name']);
        self::assertSame('HU', $invoice['country']);
        self::assertSame('1011', $invoice['zip']);
    }

    public function testOptionalInvoiceFieldsAreOmittedWhenNull(): void
    {
        $invoice = self::request()->toPayload()['invoice'];

        self::assertIsArray($invoice);
        self::assertArrayNotHasKey('address2', $invoice);
        self::assertArrayNotHasKey('phone', $invoice);
        self::assertArrayNotHasKey('state', $invoice);
    }

    public function testTimeoutIsOmittedWhenNotGiven(): void
    {
        self::assertArrayNotHasKey('timeout', self::request()->toPayload());
    }

    public function testTimeoutIsSerialisedAsIso8601(): void
    {
        $request = new StartRequest(
            orderRef: 'ORDER-1',
            total: Money::fromMinorUnits(1000, Currency::HUF),
            customerEmail: 'teszt@example.com',
            invoice: self::invoice(),
            urls: self::urls(),
            timeout: new \DateTimeImmutable('2026-08-30T12:00:00+02:00'),
        );

        self::assertSame('2026-08-30T12:00:00+02:00', $request->toPayload()['timeout']);
    }

    public function testWireMethodIsSerialised(): void
    {
        $request = new StartRequest(
            orderRef: 'ORDER-1',
            total: Money::fromMinorUnits(1000, Currency::HUF),
            customerEmail: 'teszt@example.com',
            invoice: self::invoice(),
            urls: self::urls(),
            language: Language::En,
            methods: [PaymentMethod::Card, PaymentMethod::Wire],
        );

        self::assertSame(['CARD', 'WIRE'], $request->toPayload()['methods']);
        self::assertSame('EN', $request->toPayload()['language']);
    }

    public function testInvoiceRejectsABlankRequiredField(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/zip/');

        new Invoice(
            name: 'Teszt Elek',
            country: 'HU',
            city: 'Budapest',
            zip: '',
            address: 'Fő utca 1.',
        );
    }

    public function testInvoiceKeepsAZeroPostcode(): void
    {
        $invoice = new Invoice(
            name: 'Teszt Elek',
            country: 'HU',
            city: 'Budapest',
            zip: '0',
            address: 'Fő utca 1.',
        );

        self::assertSame('0', $invoice->toPayload()['zip']);
    }
}
