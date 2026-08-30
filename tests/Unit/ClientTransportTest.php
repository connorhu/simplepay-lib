<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Client;
use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Environment;
use CodeConjure\SimplePay\Exception\DeveloperException;
use CodeConjure\SimplePay\Exception\RequestException;
use CodeConjure\SimplePay\Exception\SignatureException;
use CodeConjure\SimplePay\Exception\TransportException;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\Request\Invoice;
use CodeConjure\SimplePay\Request\StartRequest;
use CodeConjure\SimplePay\Request\Urls;
use CodeConjure\SimplePay\SaltGenerator;
use CodeConjure\SimplePay\Signature;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(Client::class)]
final class ClientTransportTest extends TestCase
{
    private const string SECRET = 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6';

    private MockClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockClient();
    }

    private function client(): Client
    {
        $factory = new Psr17Factory();

        return new Client(
            new Config('PUBLICTESTHUF', self::SECRET, Environment::Sandbox),
            $this->httpClient,
            $factory,
            $factory,
            new SaltGenerator(new Randomizer(new Xoshiro256StarStar(1234))),
        );
    }

    /** @param array<string, mixed> $payload */
    private function signedResponse(array $payload, int $status = 200): Response
    {
        $body = json_encode($payload, \JSON_THROW_ON_ERROR);

        return new Response($status, ['Signature' => new Signature(self::SECRET)->sign($body)], $body);
    }

    private function startRequest(): StartRequest
    {
        return new StartRequest(
            orderRef: 'ORDER-1',
            total: Money::fromMinorUnits(1000, Currency::HUF),
            customerEmail: 'teszt@example.com',
            invoice: new Invoice('Teszt Elek', 'HU', 'Budapest', '1011', 'Fő utca 1.'),
            urls: new Urls(
                'https://bolt.hu/s',
                'https://bolt.hu/f',
                'https://bolt.hu/c',
                'https://bolt.hu/t',
                'https://bolt.hu/ipn',
            ),
        );
    }

    /** @return array<string, mixed> */
    private static function startPayload(): array
    {
        return [
            'merchant' => 'PUBLICTESTHUF',
            'orderRef' => 'ORDER-1',
            'currency' => 'HUF',
            'transactionId' => 99999999,
            'total' => 1000,
            'paymentUrl' => 'https://sandbox.simplepay.hu/pay/pay/xyz',
        ];
    }

    public function testItPostsToTheStartEndpoint(): void
    {
        $this->httpClient->addResponse($this->signedResponse(self::startPayload()));

        $this->client()->start($this->startRequest());

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://sandbox.simplepay.hu/payment/v2/start', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testTheSentBodyIsSignedExactlyAsSent(): void
    {
        $this->httpClient->addResponse($this->signedResponse(self::startPayload()));

        $this->client()->start($this->startRequest());

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        $body = (string) $request->getBody();

        self::assertTrue(
            new Signature(self::SECRET)->verify($body, $request->getHeaderLine('Signature')),
            'Az aláírásnak a ténylegesen elküldött byte-okra kell illeszkednie.',
        );
    }

    public function testTheClientAddsMerchantSaltAndSdkVersion(): void
    {
        $this->httpClient->addResponse($this->signedResponse(self::startPayload()));

        $this->client()->start($this->startRequest());

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        $sent = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($sent);
        self::assertSame('PUBLICTESTHUF', $sent['merchant']);
        self::assertIsString($sent['salt']);
        self::assertSame(32, strlen($sent['salt']));
        self::assertIsString($sent['sdkVersion']);
        self::assertStringContainsString('CodeConjure', $sent['sdkVersion']);
    }

    public function testAnUnsignedSuccessfulResponseIsRejected(): void
    {
        $this->httpClient->addResponse(new Response(200, [], json_encode(self::startPayload(), \JSON_THROW_ON_ERROR)));

        $this->expectException(SignatureException::class);

        $this->client()->start($this->startRequest());
    }

    public function testATamperedResponseIsRejected(): void
    {
        $body = json_encode(self::startPayload(), \JSON_THROW_ON_ERROR);
        $this->httpClient->addResponse(new Response(200, ['Signature' => 'aGFtaXM='], $body));

        $this->expectException(SignatureException::class);

        $this->client()->start($this->startRequest());
    }

    public function testAnUnsignedServerErrorBecomesATransportException(): void
    {
        $this->httpClient->addResponse(new Response(502, [], 'Bad Gateway'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('502');

        $this->client()->start($this->startRequest());
    }

    public function testAMalformedBodyBecomesATransportException(): void
    {
        $this->httpClient->addResponse(new Response(200, ['Signature' => new Signature(self::SECRET)->sign('nem json')], 'nem json'));

        $this->expectException(TransportException::class);

        $this->client()->start($this->startRequest());
    }

    public function testErrorCodesBecomeARequestException(): void
    {
        $this->httpClient->addResponse($this->signedResponse(['errorCodes' => [2013]], 400));

        try {
            $this->client()->start($this->startRequest());
            self::fail('Kivételt vártunk.');
        } catch (RequestException $exception) {
            self::assertSame([2013], $exception->codes());
            self::assertNotInstanceOf(DeveloperException::class, $exception);
        }
    }

    public function testDeveloperErrorCodesBecomeADeveloperException(): void
    {
        $this->httpClient->addResponse($this->signedResponse(['errorCodes' => [2003]], 400));

        $this->expectException(DeveloperException::class);

        $this->client()->start($this->startRequest());
    }

    public function testAMixedErrorCodesListStillThrowsWithTheUsableCodes(): void
    {
        $this->httpClient->addResponse($this->signedResponse(['errorCodes' => [2013, 'FOO']], 400));

        try {
            $this->client()->start($this->startRequest());
            self::fail('Kivételt vártunk.');
        } catch (RequestException $exception) {
            self::assertSame([2013], $exception->codes());
        }
    }

    public function testAnAllUnusableErrorCodesListBecomesAnUnexpectedResponseException(): void
    {
        $this->httpClient->addResponse($this->signedResponse(['errorCodes' => ['FOO', 'BAR']], 400));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('FOO');

        $this->client()->start($this->startRequest());
    }

    public function testANonArrayErrorCodesValueBecomesAnUnexpectedResponseException(): void
    {
        $this->httpClient->addResponse($this->signedResponse(['errorCodes' => 'FOO'], 400));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('FOO');

        $this->client()->start($this->startRequest());
    }

    public function testATransportFailureIsWrapped(): void
    {
        $this->httpClient->addException(new class() extends \RuntimeException implements ClientExceptionInterface {
        });

        $this->expectException(TransportException::class);

        $this->client()->start($this->startRequest());
    }

    public function testStartReturnsATypedResponse(): void
    {
        $this->httpClient->addResponse($this->signedResponse(self::startPayload()));

        $response = $this->client()->start($this->startRequest());

        self::assertSame('https://sandbox.simplepay.hu/pay/pay/xyz', $response->paymentUrl);
        self::assertSame('99999999', $response->transactionId);
    }
}
