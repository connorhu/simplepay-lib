<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Response;

use CodeConjure\SimplePay\Client;
use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Environment;
use CodeConjure\SimplePay\Exception\SignatureException;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Response\ReturnData;
use CodeConjure\SimplePay\ReturnEvent;
use CodeConjure\SimplePay\Signature;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReturnData::class)]
#[CoversClass(Client::class)]
final class ReturnDataTest extends TestCase
{
    private const string SECRET = 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6';

    private const string R = 'eyJyIjowLCJ0Ijo5OTk5OTk5OSwiZSI6IlNVQ0NFU1MiLCJtIjoiUFVCTElDVEVTVEhVRiIsIm8iOiJPUkRFUi0xIn0=';

    private const string S = 'OMc+d/5aQ05SMXkrVfMIB9WL2SbSLyfcabqyifMr1n1ktdtDGygmTr8gJQiQwVJ7';

    private function client(): Client
    {
        $factory = new Psr17Factory();

        return new Client(
            new Config('PUBLICTESTHUF', self::SECRET, Environment::Sandbox),
            new MockClient(),
            $factory,
            $factory,
        );
    }

    public function testItParsesTheReturnPayload(): void
    {
        $data = $this->client()->parseReturn(self::R, self::S);

        self::assertSame(ReturnEvent::Success, $data->event);
        self::assertSame('99999999', $data->transactionId);
        self::assertSame('ORDER-1', $data->orderRef);
        self::assertSame('PUBLICTESTHUF', $data->merchant);
        self::assertSame(0, $data->responseCode);
    }

    public function testTheSignatureIsCheckedAgainstTheBase64String(): void
    {
        self::assertSame(ReturnEvent::Success, $this->client()->parseReturn(self::R, self::S)->event);
    }

    public function testAForgedSignatureIsRejected(): void
    {
        $this->expectException(SignatureException::class);

        $this->client()->parseReturn(self::R, 'aGFtaXM=');
    }

    public function testATamperedPayloadIsRejected(): void
    {
        $forged = base64_encode('{"r":0,"t":11111111,"e":"SUCCESS","m":"PUBLICTESTHUF","o":"ORDER-9"}');

        $this->expectException(SignatureException::class);

        $this->client()->parseReturn($forged, self::S);
    }

    public function testNonBase64InputIsRejected(): void
    {
        $r = '!!!nem base64!!!';
        $s = new Signature(self::SECRET)->sign($r);

        $this->expectException(UnexpectedResponseException::class);

        $this->client()->parseReturn($r, $s);
    }

    public function testAnUnknownEventIsLoud(): void
    {
        $r = base64_encode('{"r":0,"t":99999999,"e":"MAYBE","m":"PUBLICTESTHUF","o":"ORDER-1"}');
        $s = new Signature(self::SECRET)->sign($r);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('MAYBE');

        $this->client()->parseReturn($r, $s);
    }
}
