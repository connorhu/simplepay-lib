<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\SaltGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(SaltGenerator::class)]
final class SaltGeneratorTest extends TestCase
{
    public function testSaltIs32CharactersLong(): void
    {
        self::assertSame(32, strlen(new SaltGenerator()->generate()));
    }

    public function testSaltIsAlphanumeric(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', new SaltGenerator()->generate());
    }

    public function testTwoSaltsDiffer(): void
    {
        $generator = new SaltGenerator();

        self::assertNotSame($generator->generate(), $generator->generate());
    }

    public function testSaltIsReproducibleForASeededRandomizer(): void
    {
        $first = new SaltGenerator(new Randomizer(new Xoshiro256StarStar(1234)))->generate();
        $second = new SaltGenerator(new Randomizer(new Xoshiro256StarStar(1234)))->generate();

        self::assertSame($first, $second);
    }
}
