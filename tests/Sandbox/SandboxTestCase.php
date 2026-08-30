<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Sandbox;

use CodeConjure\SimplePay\Client;
use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Environment;
use Http\Client\Curl\Client as CurlClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('sandbox')]
abstract class SandboxTestCase extends TestCase
{
    protected const string FIXTURE_DIR = __DIR__ . '/../Fixtures/sandbox';

    private ?RecordingHttpClient $recorder = null;

    protected function client(): Client
    {
        $merchant = getenv('SIMPLEPAY_SANDBOX_MERCHANT');
        $secret = getenv('SIMPLEPAY_SANDBOX_SECRET');

        if (!is_string($merchant) || !is_string($secret) || '' === $merchant || '' === $secret) {
            self::markTestSkipped('Nincs sandbox merchant vagy secret a környezetben.');
        }

        $factory = new Psr17Factory();
        $this->recorder = new RecordingHttpClient(new CurlClient($factory, $factory));

        return new Client(
            new Config($merchant, $secret, Environment::Sandbox),
            $this->recorder,
            $factory,
            $factory,
        );
    }

    /**
     * A legutóbb a sandboxtól kapott NYERS válasz-törzs, a Client/DTO
     * rétegen még át nem esve. Ez a fixture-ök bizonyító ereje: a
     * `record()`-nak átadott DTO-összefoglalók a saját szerializálásunkat
     * játsszák vissza, ez viszont a huzalon ténylegesen látott byte-okat.
     *
     * Csak azután hívható, hogy a `client()`-tel kapott klienssel legalább
     * egy hívás lezajlott — különben hangosan dob, nem ad vissza csendben
     * semmit.
     */
    protected function rawResponse(): string
    {
        if (null === $this->recorder) {
            throw new \LogicException(
                'rawResponse() a client() metódus előtt lett meghívva — nincs mit rögzíteni.',
            );
        }

        $raw = $this->recorder->lastRawBody();

        if (null === $raw) {
            throw new \LogicException(
                'rawResponse()-t hívtunk, de a client()-tel kapott kliensen keresztül még nem ment '
                . 'ki egyetlen hívás sem.',
            );
        }

        return $raw;
    }

    /**
     * A valódi választ fixture-ként rögzíti — DTO-összefoglalóként,
     * olvashatóság kedvéért. A bizonyító erejű rögzítéshez lásd
     * `recordRaw()`.
     *
     * A könyvtár létrehozása és a fájlírás sikerét explicit ellenőrizzük:
     * ha bármelyik csendben elhasalna, a korábbi fixture maradna a
     * lemezen, és a kontraktus-teszt zölden térne vissza úgy, hogy
     * valójában semmi nem lett rögzítve — pont azon az egy helyen, ahol a
     * fixture maga a bizonyíték.
     */
    protected function record(string $name, mixed $payload): void
    {
        self::ensureFixtureDirExists();

        $path = sprintf('%s/%s.json', self::FIXTURE_DIR, $name);
        $encoded = json_encode(
            $payload,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        ) . "\n";

        if (false === file_put_contents($path, $encoded)) {
            throw new \RuntimeException(sprintf('Nem sikerült a fixture-t kiírni: "%s".', $path));
        }
    }

    /**
     * A legutóbb kapott NYERS válasz-törzset rögzíti fixture-ként —
     * pontosan azt, amit a SimplePay a huzalon elküldött, a Client/DTO
     * rétegen való áthaladás előtt. Ez a fixture-fajta hordozza a
     * bizonyító erőt: a `FixtureConformanceTest` ezeken keresztül tudja
     * ellenőrizni, hogy a válasz-osztályaink a valódi API-alakot parsolják,
     * nem a saját korábbi szerializálásunkat.
     *
     * A lemezre írás előtt a `FixtureRedactor` eltávolítja az érzékeny
     * mezőket (lásd ott) — a rögzítés pillanatában, nem utólag.
     */
    protected function recordRaw(string $name): void
    {
        $decoded = json_decode($this->rawResponse(), true, 512, \JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf(
                'A(z) "%s" nyers válasz nem JSON objektum, nem rögzíthető fixture-ként.',
                $name,
            ));
        }

        $this->record($name, FixtureRedactor::redact($decoded));
    }

    private static function ensureFixtureDirExists(): void
    {
        if (is_dir(self::FIXTURE_DIR)) {
            return;
        }

        if (!mkdir(self::FIXTURE_DIR, 0o775, true) && !is_dir(self::FIXTURE_DIR)) {
            throw new \RuntimeException(sprintf(
                'Nem sikerült létrehozni a fixture könyvtárat: "%s".',
                self::FIXTURE_DIR,
            ));
        }
    }

    protected function orderRef(): string
    {
        return sprintf('CONTRACT-%s-%s', date('Ymd-His'), bin2hex(random_bytes(3)));
    }
}
