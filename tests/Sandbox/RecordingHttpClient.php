<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Sandbox;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 dekorátor, ami a valódi kliens elé ül, kizárólag a sandbox
 * kontraktus-tesztek számára. Célja: a `Client` a válasz-törzset a saját
 * DTO-in keresztül olvassa, tehát semmi mást nem tudnánk elmondani arról,
 * mi jött ténylegesen a huzalon — ez a dekorátor megőrzi a nyers,
 * dekódolatlan válasz-törzs byte-jait, mielőtt bármi feldolgozná.
 *
 * A `Client` DTO-i és a fixture-be írt DTO-összefoglalók a saját
 * szerializálásunkat tükrözik vissza — hasznosak olvasáshoz, de nem
 * bizonyítanak semmit a valódi API-alakról. A `lastRawBody()` az, ami
 * bizonyíték: pontosan azok a byte-ok, amiket a SimplePay elküldött.
 */
final class RecordingHttpClient implements ClientInterface
{
    private ?string $lastRawBody = null;

    public function __construct(private readonly ClientInterface $inner)
    {
    }

    /** @throws ClientExceptionInterface */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->inner->sendRequest($request);
        $body = $response->getBody();

        $this->lastRawBody = (string) $body;

        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $response;
    }

    /**
     * A legutóbb kapott válasz nyers törzse, ahogy a SimplePay ténylegesen
     * elküldte — a Client/DTO rétegen még nem ment át.
     */
    public function lastRawBody(): ?string
    {
        return $this->lastRawBody;
    }
}
