<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Environment;
use CodeConjure\SimplePay\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    public function testItKeepsTheGivenValues(): void
    {
        $config = new Config('PUBLICTESTHUF', 'titok', Environment::Sandbox);

        self::assertSame('PUBLICTESTHUF', $config->merchant);
        self::assertSame('titok', $config->secretKey);
        self::assertSame(Environment::Sandbox, $config->environment);
    }

    public function testBaseUrlComesFromTheEnvironment(): void
    {
        self::assertSame(
            'https://secure.simplepay.hu/payment/v2/',
            new Config('M', 's', Environment::Production)->baseUrl(),
        );
    }

    public function testSignatureUsesTheSecretKey(): void
    {
        $config = new Config('PUBLICTESTHUF', 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6', Environment::Sandbox);

        self::assertSame(
            '2jhhXDkc6/cJna/lMvut1qRt+a1t1AakfzqiovFTkuweGmMTsj3qSjYzfpcNcWU2',
            $config->signature()->sign('{"salt":"abcdefghijklmnopqrstuvwxyz012345","merchant":"PUBLICTESTHUF"}'),
        );
    }

    public function testEmptyMerchantIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('merchant');

        new Config('   ', 'titok', Environment::Sandbox);
    }

    public function testEmptySecretKeyIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('secretKey');

        new Config('PUBLICTESTHUF', '', Environment::Sandbox);
    }
}
