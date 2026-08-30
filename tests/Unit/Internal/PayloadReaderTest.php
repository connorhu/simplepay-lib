<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Internal;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Internal\PayloadReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayloadReader::class)]
final class PayloadReaderTest extends TestCase
{
    public function testStringReadsAString(): void
    {
        self::assertSame('érték', PayloadReader::string(['kulcs' => 'érték'], 'kulcs'));
    }

    public function testStringCastsAnInteger(): void
    {
        self::assertSame('99999999', PayloadReader::string(['t' => 99999999], 't'));
    }

    public function testMissingStringNamesTheKey(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('orderRef');

        PayloadReader::string([], 'orderRef');
    }

    public function testNullableStringReturnsNullWhenAbsent(): void
    {
        self::assertNull(PayloadReader::nullableString([], 'nincs'));
    }

    public function testIntReadsAnInteger(): void
    {
        self::assertSame(3, PayloadReader::int(['totalCount' => 3], 'totalCount'));
    }

    public function testIntAcceptsANumericString(): void
    {
        self::assertSame(3, PayloadReader::int(['totalCount' => '3'], 'totalCount'));
    }

    public function testIntRejectsNonNumeric(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        PayloadReader::int(['totalCount' => 'három'], 'totalCount');
    }

    public function testDateTimeParsesIso8601(): void
    {
        $date = PayloadReader::dateTime(['paymentDate' => '2026-08-30T12:00:00+02:00'], 'paymentDate');

        self::assertSame('2026-08-30T12:00:00+02:00', $date->format(\DateTimeInterface::ATOM));
    }

    public function testDateTimeRejectsGarbage(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        PayloadReader::dateTime(['paymentDate' => 'tegnap'], 'paymentDate');
    }

    public function testNullableDateTimeReturnsNullWhenAbsent(): void
    {
        self::assertNull(PayloadReader::nullableDateTime([], 'paymentDate'));
    }

    public function testMapListReadsAListOfMaps(): void
    {
        $list = PayloadReader::mapList(['transactions' => [['a' => 1], ['b' => 2]]], 'transactions');

        self::assertCount(2, $list);
        self::assertSame(1, $list[0]['a']);
    }

    public function testMapListReturnsEmptyWhenAbsent(): void
    {
        self::assertSame([], PayloadReader::mapList([], 'transactions'));
    }

    public function testMapListRejectsAScalarElement(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        PayloadReader::mapList(['transactions' => ['nem tömb']], 'transactions');
    }

    public function testScalarAmountAcceptsAnInt(): void
    {
        self::assertSame(1000, PayloadReader::scalarAmount(['total' => 1000], 'total'));
    }

    public function testScalarAmountAcceptsAZeroInt(): void
    {
        self::assertSame(0, PayloadReader::scalarAmount(['total' => 0], 'total'));
    }

    public function testScalarAmountAcceptsAFloat(): void
    {
        self::assertSame(10.5, PayloadReader::scalarAmount(['total' => 10.5], 'total'));
    }

    public function testScalarAmountAcceptsANumericString(): void
    {
        self::assertSame('1000', PayloadReader::scalarAmount(['total' => '1000'], 'total'));
    }

    public function testScalarAmountAcceptsTheStringZero(): void
    {
        self::assertSame('0', PayloadReader::scalarAmount(['total' => '0'], 'total'));
    }

    public function testScalarAmountMissingNamesTheKey(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('total');

        PayloadReader::scalarAmount([], 'total');
    }

    public function testScalarAmountRejectsNonNumeric(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        PayloadReader::scalarAmount(['total' => 'sok'], 'total');
    }
}
