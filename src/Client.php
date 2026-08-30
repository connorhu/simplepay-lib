<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\RequestException;
use CodeConjure\SimplePay\Exception\SignatureException;
use CodeConjure\SimplePay\Exception\TransportException;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Ipn\IpnConfirmation;
use CodeConjure\SimplePay\Ipn\IpnMessage;
use CodeConjure\SimplePay\Request\QueryRequest;
use CodeConjure\SimplePay\Request\RefundRequest;
use CodeConjure\SimplePay\Request\StartRequest;
use CodeConjure\SimplePay\Response\QueryResponse;
use CodeConjure\SimplePay\Response\RefundResponse;
use CodeConjure\SimplePay\Response\ReturnData;
use CodeConjure\SimplePay\Response\StartResponse;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class Client
{
    public const string SDK_VERSION = 'CodeConjure_SimplePay/1.0';

    private const int JSON_FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

    private const string RECEIVE_DATE_FORMAT = \DateTimeInterface::ATOM;

    public function __construct(
        private Config $config,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private SaltGenerator $saltGenerator = new SaltGenerator(),
    ) {
    }

    public function start(StartRequest $request): StartResponse
    {
        return StartResponse::fromPayload($this->post('start', $request->toPayload()));
    }

    public function query(QueryRequest $request): QueryResponse
    {
        return QueryResponse::fromPayload($this->post('query', $request->toPayload()));
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        return RefundResponse::fromPayload($this->post('refund', $request->toPayload()));
    }

    /**
     * @param \DateTimeImmutable|null $receivedAt a válaszba beírt `receiveDate` időbélyege — alapértelmezés
     *                                             szerint a hívás pillanata (`new DateTimeImmutable()`). Publikus
     *                                             paraméter, de elsősorban tesztelhetőségi seam: determinisztikus
     *                                             időbélyeget enged átadni a `receiveDate` érték ellenőrzéséhez,
     *                                             ahelyett hogy a rendszeridőt kellene mockolni.
     */
    public function ipn(
        string $rawBody,
        string $signatureHeader,
        ?\DateTimeImmutable $receivedAt = null,
    ): IpnConfirmation {
        $signature = $this->config->signature();

        if ('' === trim($signatureHeader) || !$signature->verify($rawBody, $signatureHeader)) {
            throw new SignatureException('A SimplePay értesítés aláírása nem stimmel.');
        }

        try {
            $decoded = json_decode($rawBody, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new UnexpectedResponseException(
                'A SimplePay értesítés nem értelmezhető JSON.',
                previous: $exception,
            );
        }

        if (!is_array($decoded)) {
            throw new UnexpectedResponseException('A SimplePay értesítés törzse nem objektum.');
        }

        /** @var array<string, mixed> $typedDecoded */
        $typedDecoded = $decoded;

        $message = IpnMessage::fromPayload($typedDecoded);

        if ($message->merchant !== $this->config->merchant) {
            throw new UnexpectedResponseException(sprintf(
                'A SimplePay értesítés "%s" merchant azonosítója nem a konfigurált "%s" merchanthoz tartozik.',
                $message->merchant,
                $this->config->merchant,
            ));
        }

        $responseBody = $this->appendReceiveDate($rawBody, $receivedAt ?? new \DateTimeImmutable());

        return new IpnConfirmation($message, $responseBody, $signature->sign($responseBody));
    }

    public function parseReturn(string $r, string $s): ReturnData
    {
        if ('' === trim($s) || !$this->config->signature()->verify($r, $s)) {
            throw new SignatureException('A SimplePay visszatérési adat aláírása nem stimmel.');
        }

        $decodedJson = base64_decode($r, true);

        if (false === $decodedJson) {
            throw new UnexpectedResponseException('A SimplePay visszatérési adat nem base64.');
        }

        try {
            $decoded = json_decode($decodedJson, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new UnexpectedResponseException(
                'A SimplePay visszatérési adat nem értelmezhető JSON.',
                previous: $exception,
            );
        }

        if (!is_array($decoded)) {
            throw new UnexpectedResponseException('A SimplePay visszatérési adat nem objektum.');
        }

        /** @var array<string, mixed> $typedPayload */
        $typedPayload = $decoded;

        return ReturnData::fromPayload($typedPayload);
    }

    /**
     * A visszaigazolás a bejövő byte-okból épül: a záró kapcsos zárójel elé
     * szúrjuk be a receiveDate mezőt, minden mást változatlanul hagyva.
     */
    private function appendReceiveDate(string $rawBody, \DateTimeImmutable $receivedAt): string
    {
        $trimmed = trim($rawBody);

        if (!str_starts_with($trimmed, '{') || !str_ends_with($trimmed, '}')) {
            throw new UnexpectedResponseException('A SimplePay értesítés törzse nem JSON objektum.');
        }

        $receiveDate = sprintf(
            '"receiveDate":%s',
            json_encode($receivedAt->format(self::RECEIVE_DATE_FORMAT), self::JSON_FLAGS),
        );

        $inner = trim(substr($trimmed, 1, -1));

        return '' === $inner
            ? '{' . $receiveDate . '}'
            : substr($trimmed, 0, -1) . ',' . $receiveDate . '}';
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $payload): array
    {
        $payload['merchant'] = $this->config->merchant;
        $payload['salt'] = $this->saltGenerator->generate();
        $payload['sdkVersion'] = self::SDK_VERSION;

        $body = json_encode($payload, self::JSON_FLAGS);
        $signature = $this->config->signature();

        $httpRequest = $this->requestFactory
            ->createRequest('POST', $this->config->baseUrl() . $endpoint)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Signature', $signature->sign($body))
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($httpRequest);
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException(
                sprintf('A SimplePay "%s" hívása nem jutott el a szolgáltatóig.', $endpoint),
                previous: $exception,
            );
        }

        $status = $response->getStatusCode();
        $responseBody = (string) $response->getBody();
        $responseSignature = $response->getHeaderLine('Signature');

        if ('' === $responseSignature) {
            if ($status < 200 || $status >= 300) {
                throw new TransportException(sprintf(
                    'A SimplePay "%s" hívása %d státusszal, aláírás nélkül tért vissza.',
                    $endpoint,
                    $status,
                ));
            }

            throw new SignatureException(sprintf(
                'A SimplePay "%s" válaszából hiányzik a Signature fejléc.',
                $endpoint,
            ));
        }

        if (!$signature->verify($responseBody, $responseSignature)) {
            throw new SignatureException(sprintf(
                'A SimplePay "%s" válaszának aláírása nem stimmel.',
                $endpoint,
            ));
        }

        try {
            $decoded = json_decode($responseBody, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new TransportException(
                sprintf('A SimplePay "%s" válasza nem értelmezhető JSON.', $endpoint),
                previous: $exception,
            );
        }

        if (!is_array($decoded)) {
            throw new TransportException(sprintf('A SimplePay "%s" válasza nem objektum.', $endpoint));
        }

        /** @var array<string, mixed> $typedDecoded */
        $typedDecoded = $decoded;

        $errorCodes = $this->extractErrorCodes($typedDecoded, $endpoint);

        if ([] !== $errorCodes) {
            throw RequestException::fromCodes($errorCodes);
        }

        if ($status < 200 || $status >= 300) {
            throw new TransportException(sprintf(
                'A SimplePay "%s" hívása %d státusszal tért vissza, hibakód nélkül.',
                $endpoint,
                $status,
            ));
        }

        return $typedDecoded;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<int>
     */
    private function extractErrorCodes(array $payload, string $endpoint): array
    {
        $raw = $payload['errorCodes'] ?? null;

        if (null === $raw || [] === $raw) {
            return [];
        }

        $entries = is_array($raw) ? $raw : [$raw];

        $codes = [];

        foreach ($entries as $code) {
            if (is_int($code) || (is_string($code) && 1 === preg_match('/^\d+$/', $code))) {
                $codes[] = (int) $code;
            }
        }

        if ([] === $codes) {
            throw new UnexpectedResponseException(sprintf(
                'A SimplePay "%s" válasza értelmezhetetlen errorCodes értékeket tartalmazott: %s',
                $endpoint,
                json_encode($raw, \JSON_THROW_ON_ERROR),
            ));
        }

        return $codes;
    }
}
