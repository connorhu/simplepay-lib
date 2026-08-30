<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\Money;

/**
 * A `salt` és a `timeout` a hivatalos dokumentáció szerint kötelező mezők, és
 * a rögzített élő sandbox fixture (`tests/Fixtures/sandbox/raw_start.json`,
 * Task 13) mindkettőt tartalmazza is — mégis nullázhatók maradnak itt.
 *
 * Ok: a csomagon belül semmi nem olvassa ki egyiket sem — a `salt` a
 * SimplePay saját belső integritás-jelölése a `/start` válaszon, nem a
 * csomag aláírás-ellenőrzésének része (azt a `Signature` fejléc adja), a
 * `timeout` pedig csak tájékoztató visszajelzés arról, amit a hívó maga
 * küldött be a kérésben. Egy hangos `UnexpectedResponseException` egy olyan
 * mezőre, amit senki nem használ, csak egy sosem hasznosuló szigor lenne —
 * a csomag "hangos hiba helyett néma" elve a ténylegesen felhasznált
 * adatokra vonatkozik, nem minden dokumentált mezőre külön-külön.
 */
final readonly class StartResponse
{
    public function __construct(
        public string $merchant,
        public string $orderRef,
        public string $transactionId,
        public string $paymentUrl,
        public Money $total,
        public ?string $salt = null,
        public ?\DateTimeImmutable $timeout = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $currency = Currency::fromApi(PayloadReader::string($payload, 'currency'));

        return new self(
            merchant: PayloadReader::string($payload, 'merchant'),
            orderRef: PayloadReader::string($payload, 'orderRef'),
            transactionId: PayloadReader::string($payload, 'transactionId'),
            paymentUrl: PayloadReader::string($payload, 'paymentUrl'),
            total: Money::fromApiValue(PayloadReader::scalarAmount($payload, 'total'), $currency),
            salt: PayloadReader::nullableString($payload, 'salt'),
            timeout: PayloadReader::nullableDateTime($payload, 'timeout'),
        );
    }
}
