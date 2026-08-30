<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

/**
 * HMAC-SHA384 aláírás a pontos byte-sorozat felett.
 *
 * A metódusok szándékosan `string`-et fogadnak és nem tömböt: a bejövő üzenetet
 * mindig a kapott byte-okon kell ellenőrizni, sosem egy újrakódolt változatán.
 */
final readonly class Signature
{
    private const string ALGORITHM = 'sha384';

    public function __construct(private string $secretKey)
    {
    }

    public function sign(string $body): string
    {
        return base64_encode(hash_hmac(self::ALGORITHM, $body, $this->secretKey, true));
    }

    public function verify(string $body, string $signature): bool
    {
        return hash_equals($this->sign($body), trim($signature));
    }
}
