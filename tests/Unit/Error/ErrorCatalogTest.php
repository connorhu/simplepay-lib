<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Error;

use CodeConjure\SimplePay\Error\ErrorCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorCatalog::class)]
final class ErrorCatalogTest extends TestCase
{
    public function testKnownCodeHasHungarianDescription(): void
    {
        self::assertSame('Nincs elég fedezet a kártyán.', ErrorCatalog::describe(2013));
    }

    public function testUnknownCodeHasNoDescription(): void
    {
        self::assertNull(ErrorCatalog::describe(987654));
    }

    public function testDeveloperErrorIsRecognised(): void
    {
        self::assertTrue(ErrorCatalog::isDeveloperError(2003));
    }

    public function testNonDeveloperErrorIsNotFlagged(): void
    {
        self::assertFalse(ErrorCatalog::isDeveloperError(2013));
    }

    public function testUnknownCodeIsNotADeveloperError(): void
    {
        self::assertFalse(ErrorCatalog::isDeveloperError(987654));
    }
}
