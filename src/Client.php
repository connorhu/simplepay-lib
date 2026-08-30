<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\RequestException;
use CodeConjure\SimplePay\Exception\SignatureException;
use CodeConjure\SimplePay\Exception\TransportException;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Request\QueryRequest;
use CodeConjure\SimplePay\Request\RefundRequest;
use CodeConjure\SimplePay\Request\StartRequest;
use CodeConjure\SimplePay\Response\QueryResponse;
use CodeConjure\SimplePay\Response\RefundResponse;
use CodeConjure\SimplePay\Response\StartResponse;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class Client
{
    public const string SDK_VERSION = 'CodeConjure_SimplePay/1.0';

    private const int JSON_FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

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
