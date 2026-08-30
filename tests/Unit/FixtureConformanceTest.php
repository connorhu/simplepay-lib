<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Exception\RequestException;
use CodeConjure\SimplePay\Response\QueryResponse;
use CodeConjure\SimplePay\Response\RefundResponse;
use CodeConjure\SimplePay\Response\StartResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A tests/Sandbox/ alatti kontraktus-tesztek valódi SimplePay válaszokat
 * rögzítenek a tests/Fixtures/sandbox/ könyvtárba (lásd
 * SandboxTestCase::record()/recordRaw()). Ez a teszt a gyors, hálózat
 * nélküli suite tagja: minden rögzített fixture-t átereszt a hozzá
 * tartozó válasz-osztály fromPayload()-ján, hogy egy megváltozott
 * SimplePay válaszalak azonnal, a következő rendes futásnál kiderüljön —
 * ne csak akkor, ha valaki kézzel megnézi a JSON-t.
 *
 * Kétféle fixture van, és csak az egyik bizonyít valamit:
 *
 * - `start.json`, `query.json`, `refund_error.json` — a SandboxTestCase
 *   `record()`-jával írt DTO-összefoglalók. Olvashatók, de a mi
 *   szerializálásunkat tükrözik vissza; ha egy mezőnév vagy típus
 *   megváltozna a SimplePay oldalán, ezek a fixture-ök simán túlélnék,
 *   mert a DTO már átment a téves feltevésen, mielőtt a fixture
 *   megíródott.
 * - `raw_start.json`, `raw_query.json`, `raw_refund_error.json` — a
 *   `recordRaw()`-val írt, dekódolatlan válasz-törzsek, ahogy a
 *   SimplePay ténylegesen elküldte őket. EZEK a bizonyíték: ha a
 *   SimplePay átnevez vagy áttípusít egy mezőt, ez a teszt a legközelebbi
 *   `vendor/bin/phpunit` futáson elbukik, amint egy éjszakai sandbox
 *   futás frissíti a nyers fixture-t.
 *
 * A `refund_error.json`/`raw_refund_error.json` nem sikeres API-válasz
 * alakja: a jóváírást a sandbox kontraktus-teszt nem tudja emberi
 * kattintás nélkül előidézni, ezért egy elutasítás alakját rögzíti.
 * Ehhez nincs válasz-DTO — a rögzített hibakódokat a
 * RequestException::fromCodes()-on eresztjük át.
 */
#[CoversClass(StartResponse::class)]
#[CoversClass(QueryResponse::class)]
#[CoversClass(RefundResponse::class)]
#[CoversClass(RequestException::class)]
final class FixtureConformanceTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../Fixtures/sandbox';

    /** @return list<string> */
    private static function fixtureFiles(): array
    {
        if (!is_dir(self::FIXTURE_DIR)) {
            return [];
        }

        $files = glob(self::FIXTURE_DIR . '/*.json');

        return false === $files ? [] : $files;
    }

    /**
     * Mindig legalább egy adatsort ad vissza — üres data provider PHPUnit
     * hibát dob ("Empty data set provided"), ami pont azt a friss
     * checkout / "sandbox még sosem futott" állapotot buktatná el, aminek
     * tisztán kellene skippelnie. Üres fixture-könyvtár esetén ezért egy
     * `null` jelzőértéket ad vissza, amit a teszt maga fordít
     * markTestSkipped()-re.
     *
     * @return iterable<string, array{?string}>
     */
    public static function fixtureProvider(): iterable
    {
        $files = self::fixtureFiles();

        if ([] === $files) {
            yield 'nincs-fixture' => [null];

            return;
        }

        foreach ($files as $file) {
            yield basename($file) => [$file];
        }
    }

    #[DataProvider('fixtureProvider')]
    public function testFixtureParsesThroughItsResponseClass(?string $file): void
    {
        if (null === $file) {
            self::markTestSkipped(
                'Nincs rögzített sandbox fixture — a sandbox suite '
                . '(vendor/bin/phpunit --group sandbox) még nem futott.',
            );
        }

        self::assertFixtureParses($file);
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
            // DTO-összefoglalók — olvashatóság, nem bizonyíték.
            'start' => self::assertStartFixtureParses($payload),
            'query' => self::assertQueryFixtureParses($payload),
            'refund' => self::assertRefundFixtureParses($payload),
            'refund_error' => self::assertRefundErrorSummaryMatchesTheRealException($payload),
            // Nyers válasz-törzsek — ezek a bizonyíték.
            'raw_start' => self::assertStartFixtureParses($payload),
            'raw_query' => self::assertQueryFixtureParses($payload),
            'raw_refund_error' => self::assertRawRefundErrorFixtureIsWellFormed($payload),
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

    /**
     * A `refund_error.json` a SandboxTestCase kényelmi "codes"/"message"
     * összefoglalója. A "codes" listát átengedjük a valódi kivétel-építőn,
     * és — a puszta nem-üresség helyett — a felépített kivétel üzenetét
     * szó szerint összevetjük a rögzített üzenettel, hogy egy jövőbeli
     * ErrorCatalog-változás (pl. egy leírás szövegének módosítása) is
     * kiderüljön, ne csak a kódok jelenléte.
     *
     * @param array<string, mixed> $payload
     */
    private static function assertRefundErrorSummaryMatchesTheRealException(array $payload): void
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

        $exception = RequestException::fromCodes($intCodes);
        self::assertSame($intCodes, $exception->codes());

        $message = $payload['message'] ?? null;

        if (!is_string($message) || '' === $message) {
            self::fail('A refund_error fixture "message" mezője hiányzik vagy üres.');
        }

        self::assertSame(
            $exception->getMessage(),
            $message,
            'A rögzített üzenet nem egyezik azzal, amit a RequestException::fromCodes() ma építene — '
            . 'vagy a hibakód-katalógus, vagy a rögzítés módja változott.',
        );
    }

    /**
     * A nyers, dekódolatlan `/refund` hiba-válasz a SimplePay saját
     * mezőnevét ("errorCodes") hordozza, nem a mi kényelmi "codes"
     * nevünket — ez a mappelés maga is a bizonyíték része: ha a SimplePay
     * átnevezné ezt a mezőt, ez a teszt buknia kellene.
     *
     * @param array<string, mixed> $payload
     */
    private static function assertRawRefundErrorFixtureIsWellFormed(array $payload): void
    {
        $errorCodes = $payload['errorCodes'] ?? null;

        if (!is_array($errorCodes) || [] === $errorCodes) {
            self::fail('A raw_refund_error fixture "errorCodes" mezője hiányzik, nem lista, vagy üres.');
        }

        $intCodes = [];

        foreach ($errorCodes as $code) {
            if (!is_int($code)) {
                self::fail('A raw_refund_error fixture "errorCodes" listája csak egész számokat tartalmazhat.');
            }

            $intCodes[] = $code;
        }

        self::assertNotSame([], $intCodes);

        $exception = RequestException::fromCodes($intCodes);
        self::assertSame($intCodes, $exception->codes());
    }
}
