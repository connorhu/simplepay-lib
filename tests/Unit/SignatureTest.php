<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Signature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Signature::class)]
final class SignatureTest extends TestCase
{
    private const string SECRET = 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6';

    private const string BODY = '{"salt":"abcdefghijklmnopqrstuvwxyz012345","merchant":"PUBLICTESTHUF"}';

    private const string EXPECTED = '2jhhXDkc6/cJna/lMvut1qRt+a1t1AakfzqiovFTkuweGmMTsj3qSjYzfpcNcWU2';

    public function testSignProducesTheKnownVector(): void
    {
        self::assertSame(self::EXPECTED, new Signature(self::SECRET)->sign(self::BODY));
    }

    public function testSignatureIsBase64OfA48ByteDigest(): void
    {
        $raw = base64_decode(new Signature(self::SECRET)->sign(self::BODY), true);

        self::assertIsString($raw);
        self::assertSame(48, strlen($raw), 'A SHA-384 lenyomat 48 byte — ha 32, akkor SHA-256-ot számolunk.');
    }

    public function testVerifyAcceptsTheMatchingSignature(): void
    {
        self::assertTrue(new Signature(self::SECRET)->verify(self::BODY, self::EXPECTED));
    }

    public function testVerifyRejectsATamperedBody(): void
    {
        self::assertFalse(new Signature(self::SECRET)->verify(self::BODY . ' ', self::EXPECTED));
    }

    public function testVerifyRejectsAWrongSignature(): void
    {
        self::assertFalse(new Signature(self::SECRET)->verify(self::BODY, 'bm9wZQ=='));
    }

    public function testVerifyRejectsAnEmptySignature(): void
    {
        self::assertFalse(new Signature(self::SECRET)->verify(self::BODY, ''));
    }

    public function testVerifyToleratesSurroundingWhitespaceInTheHeader(): void
    {
        self::assertTrue(new Signature(self::SECRET)->verify(self::BODY, " \t" . self::EXPECTED . "\r\n"));
    }

    public function testADifferentKeyProducesADifferentSignature(): void
    {
        self::assertNotSame(self::EXPECTED, new Signature('masik-kulcs')->sign(self::BODY));
    }
}
