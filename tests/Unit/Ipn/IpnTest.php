<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Ipn;

use CodeConjure\SimplePay\Client;
use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Environment;
use CodeConjure\SimplePay\Exception\SignatureException;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Ipn\IpnConfirmation;
use CodeConjure\SimplePay\Ipn\IpnMessage;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\Signature;
use CodeConjure\SimplePay\TransactionStatus;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IpnMessage::class)]
#[CoversClass(IpnConfirmation::class)]
#[CoversClass(Client::class)]
final class IpnTest extends TestCase
{
    private const string SECRET = 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6';

    private const string BODY = '{"salt":"abcdefghijklmnopqrstuvwxyz012345","orderRef":"ORDER-1","method":"CARD","merchant":"PUBLICTESTHUF","finishDate":"2026-08-30T12:05:00+02:00","paymentDate":"2026-08-30T12:04:00+02:00","transactionId":99999999,"status":"FINISHED"}';

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

    private static function signature(string $body): string
    {
        return new Signature(self::SECRET)->sign($body);
    }

    private static function receivedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-30T12:06:00+02:00');
    }

    public function testItParsesTheMessage(): void
    {
        $message = $this->client()->ipn(self::BODY, self::signature(self::BODY))->message();

        self::assertSame('ORDER-1', $message->orderRef);
        self::assertSame('99999999', $message->transactionId);
        self::assertSame(TransactionStatus::Finished, $message->status);
        self::assertSame(PaymentMethod::Card, $message->method);
        self::assertSame('2026-08-30T12:04:00+02:00', $message->paymentDate?->format(\DateTimeInterface::ATOM));
    }

    public function testABadSignatureIsRejected(): void
    {
        $this->expectException(SignatureException::class);

        $this->client()->ipn(self::BODY, 'aGFtaXM=');
    }

    public function testAnEmptySignatureIsRejected(): void
    {
        $this->expectException(SignatureException::class);

        $this->client()->ipn(self::BODY, '');
    }

    public function testATamperedBodyIsRejected(): void
    {
        $tampered = str_replace('"FINISHED"', '"CANCELLED"', self::BODY);

        self::assertNotSame(self::BODY, $tampered, 'A teszt csak akkor értelmes, ha tényleg módosult a törzs.');

        $this->expectException(SignatureException::class);

        $this->client()->ipn($tampered, self::signature(self::BODY));
    }

    public function testTheConfirmationAppendsReceiveDate(): void
    {
        $body = $this->client()->ipn(self::BODY, self::signature(self::BODY), self::receivedAt())->responseBody();

        self::assertStringContainsString('"receiveDate":"2026-08-30T12:06:00+02:00"', $body);
    }

    public function testTheConfirmationPreservesTheIncomingBytes(): void
    {
        $body = $this->client()->ipn(self::BODY, self::signature(self::BODY), self::receivedAt())->responseBody();

        self::assertStringStartsWith(substr(self::BODY, 0, -1), $body);
        self::assertStringEndsWith('}', $body);
    }

    public function testTheConfirmationIsValidJson(): void
    {
        $body = $this->client()->ipn(self::BODY, self::signature(self::BODY), self::receivedAt())->responseBody();
        $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame('ORDER-1', $decoded['orderRef']);
        self::assertSame('2026-08-30T12:06:00+02:00', $decoded['receiveDate']);
    }

    public function testTheConfirmationIsSignedOverItsOwnBody(): void
    {
        $confirmation = $this->client()->ipn(self::BODY, self::signature(self::BODY), self::receivedAt());

        self::assertTrue(
            new Signature(self::SECRET)->verify($confirmation->responseBody(), $confirmation->responseSignature()),
        );
    }

    public function testTheConfirmationHandlesTrailingWhitespaceInTheRawBody(): void
    {
        $body = self::BODY . "\n  ";

        $confirmation = $this->client()->ipn($body, self::signature($body), self::receivedAt());
        $decoded = json_decode($confirmation->responseBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame('2026-08-30T12:06:00+02:00', $decoded['receiveDate']);
    }

    public function testTheConfirmationHandlesLeadingWhitespaceInTheRawBody(): void
    {
        $body = "  \n" . self::BODY;

        $confirmation = $this->client()->ipn($body, self::signature($body), self::receivedAt());
        $decoded = json_decode($confirmation->responseBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame('ORDER-1', $decoded['orderRef']);
        self::assertSame('2026-08-30T12:06:00+02:00', $decoded['receiveDate']);
    }

    public function testTheConfirmationHandlesLeadingAndTrailingWhitespaceInTheRawBody(): void
    {
        $body = "  \n" . self::BODY . "\n  ";

        $confirmation = $this->client()->ipn($body, self::signature($body), self::receivedAt());
        $decoded = json_decode($confirmation->responseBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame('ORDER-1', $decoded['orderRef']);
        self::assertSame(99999999, $decoded['transactionId']);
        self::assertSame('2026-08-30T12:06:00+02:00', $decoded['receiveDate']);
    }

    public function testTheConfirmationHandlesANestedObjectAtTheEndOfTheBody(): void
    {
        $body = '{"salt":"abcdefghijklmnopqrstuvwxyz012345","orderRef":"ORDER-1","merchant":"PUBLICTESTHUF","transactionId":1,"status":"FINISHED","extra":{"a":1,"b":2}}';

        $confirmation = $this->client()->ipn($body, self::signature($body), self::receivedAt());
        $decoded = json_decode($confirmation->responseBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame(['a' => 1, 'b' => 2], $decoded['extra']);
        self::assertSame('2026-08-30T12:06:00+02:00', $decoded['receiveDate']);
    }

    public function testAMalformedBodyIsRejected(): void
    {
        $body = 'nem json';

        $this->expectException(UnexpectedResponseException::class);

        $this->client()->ipn($body, self::signature($body));
    }

    public function testABodyThatIsNotAJsonObjectIsRejected(): void
    {
        $body = '"csak egy string"';

        $this->expectException(UnexpectedResponseException::class);

        $this->client()->ipn($body, self::signature($body));
    }

    public function testAMessageForADifferentMerchantIsRejected(): void
    {
        $body = str_replace('"PUBLICTESTHUF"', '"OTHERMERCHANT"', self::BODY);

        self::assertNotSame(self::BODY, $body, 'A teszt csak akkor értelmes, ha tényleg más merchant szerepel benne.');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessageMatches('/merchant/');

        $this->client()->ipn($body, self::signature($body));
    }
}
