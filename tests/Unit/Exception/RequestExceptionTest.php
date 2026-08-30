<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Exception;

use CodeConjure\SimplePay\Exception\DeveloperException;
use CodeConjure\SimplePay\Exception\RequestException;
use CodeConjure\SimplePay\Exception\SimplePayException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestException::class)]
#[CoversClass(DeveloperException::class)]
final class RequestExceptionTest extends TestCase
{
    public function testDeveloperCodeProducesDeveloperException(): void
    {
        self::assertInstanceOf(DeveloperException::class, RequestException::fromCodes([2003]));
    }

    public function testNonDeveloperCodeProducesPlainRequestException(): void
    {
        $exception = RequestException::fromCodes([2013]);

        self::assertInstanceOf(RequestException::class, $exception);
        self::assertNotInstanceOf(DeveloperException::class, $exception);
    }

    public function testAnySingleDeveloperCodeMakesTheWholeFailureDeveloperFacing(): void
    {
        self::assertInstanceOf(DeveloperException::class, RequestException::fromCodes([2013, 2003]));
    }

    public function testErrorsCarryCodeAndDescription(): void
    {
        $errors = RequestException::fromCodes([2013])->errors();

        self::assertCount(1, $errors);
        self::assertSame(2013, $errors[0]->code);
        self::assertSame('Nincs elég fedezet a kártyán.', $errors[0]->description);
    }

    public function testUnknownCodeSurvivesWithoutDescription(): void
    {
        $errors = RequestException::fromCodes([987654])->errors();

        self::assertSame(987654, $errors[0]->code);
        self::assertNull($errors[0]->description);
    }

    public function testMessageNamesEveryCode(): void
    {
        $message = RequestException::fromCodes([2013, 987654])->getMessage();

        self::assertStringContainsString('2013', $message);
        self::assertStringContainsString('987654', $message);
    }

    public function testEverySimplePayExceptionShareTheMarkerInterface(): void
    {
        self::assertInstanceOf(SimplePayException::class, RequestException::fromCodes([2013]));
    }
}
