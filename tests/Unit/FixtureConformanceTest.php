<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Exception\RequestException;
use CodeConjure\SimplePay\Response\QueryResponse;
use CodeConjure\SimplePay\Response\RefundResponse;
use CodeConjure\SimplePay\Response\StartResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A tests/Sandbox/ alatti kontraktus-tesztek valódi SimplePay válaszokat
 * rögzítenek a tests/Fixtures/sandbox/ könyvtárba (lásd SandboxTestCase::record()).
 * Ez a teszt a gyors, hálózat nélküli suite tagja: minden rögzített
 * fixture-t átereszt a hozzá tartozó válasz-osztály fromPayload()-ján,
 * hogy egy megváltozott SimplePay válaszalak azonnal, a következő rendes
 * futásnál kiderüljön — ne csak akkor, ha valaki kézzel megnézi a JSON-t.
 *
 * A refund_error.json nem egy sikeres API-válasz alakja: a jóváírást a
 * sandbox kontraktus-teszt nem tudja emberi kattintás nélkül előidézni,
 * ezért egy elutasítás (RequestException) alakját rögzíti. Ehhez nincs
 * válasz-DTO — a rögzített hibakódokat a RequestException::fromCodes()-on
 * eresztjük át, ami a saját hibakód-katalógusán ellenőrzi őket.
 */
#[CoversClass(StartResponse::class)]
#[CoversClass(QueryResponse::class)]
#[CoversClass(RefundResponse::class)]
#[CoversClass(RequestException::class)]
final class FixtureConformanceTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../Fixtures/sandbox';

    public function testEveryRecordedFixtureParsesThroughItsResponseClass(): void
    {
        if (!is_dir(self::FIXTURE_DIR)) {
            self::markTestSkipped(
                'A sandbox fixture könyvtár nem létezik — a sandbox suite '
                . '(vendor/bin/phpunit --group sandbox) még nem futott.',
            );
        }

        $files = glob(self::FIXTURE_DIR . '/*.json');

        if (false === $files || [] === $files) {
            self::markTestSkipped(
                'Nincs rögzített sandbox fixture — a sandbox suite '
                . '(vendor/bin/phpunit --group sandbox) még nem futott.',
            );
        }

        foreach ($files as $file) {
            self::assertFixtureParses($file);
        }
    }

    private static function assertFixtureParses(string $file): void
    {
        $name = basename($file, '.json');
        $raw = file_get_contents($file);

        if (false === $raw) {
            self::fail(sprintf('A(z) "%s" fixture nem olvasható.', $file));
        }

        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            self::fail(sprintf('A(z) "%s.json" fixture nem JSON objektum.', $name));
        }

        /** @var array<string, mixed> $payload */
        $payload = $decoded;

        match ($name) {
            'start' => self::assertStartFixtureParses($payload),
            'query' => self::assertQueryFixtureParses($payload),
            'refund' => self::assertRefundFixtureParses($payload),
            'refund_error' => self::assertRefundErrorFixtureIsWellFormed($payload),
            default => self::fail(sprintf(
                'A(z) "%s.json" fixture-höz nincs hozzárendelt válaszosztály a '
                . 'FixtureConformanceTestben — vedd fel a mappelést.',
                $name,
            )),
        };
    }

    /** @param array<string, mixed> $payload */
    private static function assertStartFixtureParses(array $payload): void
    {
        self::assertInstanceOf(StartResponse::class, StartResponse::fromPayload($payload));
    }

    /** @param array<string, mixed> $payload */
    private static function assertQueryFixtureParses(array $payload): void
    {
        self::assertInstanceOf(QueryResponse::class, QueryResponse::fromPayload($payload));
    }

    /** @param array<string, mixed> $payload */
    private static function assertRefundFixtureParses(array $payload): void
    {
        self::assertInstanceOf(RefundResponse::class, RefundResponse::fromPayload($payload));
    }

    /** @param array<string, mixed> $payload */
    private static function assertRefundErrorFixtureIsWellFormed(array $payload): void
    {
        $codes = $payload['codes'] ?? null;

        if (!is_array($codes) || [] === $codes) {
            self::fail('A refund_error fixture "codes" mezője hiányzik, nem lista, vagy üres.');
        }

        $intCodes = [];

        foreach ($codes as $code) {
            if (!is_int($code)) {
                self::fail('A refund_error fixture "codes" listája csak egész számokat tartalmazhat.');
            }

            $intCodes[] = $code;
        }

        self::assertNotSame([], $intCodes);

        // A rögzített hibakódokat átengedjük a valódi kivétel-építőn — ez a
        // fixture konformitás-ellenőrzésének lényege ennél a fixture-nél.
        $exception = RequestException::fromCodes($intCodes);
        self::assertSame($intCodes, $exception->codes());

        $message = $payload['message'] ?? null;

        if (!is_string($message) || '' === $message) {
            self::fail('A refund_error fixture "message" mezője hiányzik vagy üres.');
        }

        self::assertNotSame('', $message);
    }
}
