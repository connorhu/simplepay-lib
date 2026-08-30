<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    public function testSandboxBaseUrl(): void
    {
        self::assertSame('https://sandbox.simplepay.hu/payment/v2/', Environment::Sandbox->baseUrl());
    }

    public function testProductionBaseUrl(): void
    {
        self::assertSame('https://secure.simplepay.hu/payment/v2/', Environment::Production->baseUrl());
    }

    public function testBaseUrlAlwaysEndsWithSlash(): void
    {
        foreach (Environment::cases() as $environment) {
            self::assertStringEndsWith('/', $environment->baseUrl());
        }
    }
}
