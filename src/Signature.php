<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

/**
 * HMAC-SHA384 aláírás a pontos byte-sorozat felett.
 *
 * A metódusok szándékosan `string`-et fogadnak és nem tömböt: a bejövő üzenetet
 * mindig a kapott byte-okon kell ellenőrizni, sosem egy újrakódolt változatán.
 *
 * A secretKey-t trim()-eljük, mielőtt aláírásra használnánk — a hivatalos
 * SimplePay PHP SDK (2.1.5, 2026-06-27, `src/SimplePayV21.php`) is
 * `trim($key)`-t hív a saját `getSignature()`-jében, mind aláíráskor, mind
 * ellenőrzéskor. Enélkül egy vezető/záró szóközzel bemásolt kulcs csendben
 * más aláírást adna, mint amit a SimplePay (és a saját SDK-juk) számolna —
 * minden hívás elutasításra kerülne egy olyan hibával, ami sehol nem
 * mutatna a valódi okra.
 */
final readonly class Signature
{
    private const string ALGORITHM = 'sha384';

    private string $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = trim($secretKey);
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
