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

    protected function client(): Client
    {
        $merchant = getenv('SIMPLEPAY_SANDBOX_MERCHANT');
        $secret = getenv('SIMPLEPAY_SANDBOX_SECRET');

        if (!is_string($merchant) || !is_string($secret) || '' === $merchant || '' === $secret) {
            self::markTestSkipped('Nincs sandbox merchant vagy secret a környezetben.');
        }

        $factory = new Psr17Factory();

        return new Client(
            new Config($merchant, $secret, Environment::Sandbox),
            new CurlClient($factory, $factory),
            $factory,
            $factory,
        );
    }

    /**
     * A valódi választ fixture-ként rögzíti, hogy a unit tesztek ne kitalált,
     * hanem mért adatot játsszanak vissza.
     */
    protected function record(string $name, mixed $payload): void
    {
        if (!is_dir(self::FIXTURE_DIR)) {
            mkdir(self::FIXTURE_DIR, 0o775, true);
        }

        file_put_contents(
            sprintf('%s/%s.json', self::FIXTURE_DIR, $name),
            json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR) . "\n",
        );
    }

    protected function orderRef(): string
    {
        return sprintf('CONTRACT-%s-%s', date('Ymd-His'), bin2hex(random_bytes(3)));
    }
}
