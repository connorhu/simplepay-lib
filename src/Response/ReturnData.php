<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\ReturnEvent;

/**
 * A fizetőoldalról a vásárló böngészőjén keresztül visszaérkező adat.
 *
 * Az aláírás miatt nem hamisítható, de attól még csak azt mondja meg, mit lát
 * a vásárló: tájékoztató, nem bizonyíték. A rendelés állapotát mindig a
 * lekérdezés vagy az értesítés dönti el.
 */
final readonly class ReturnData
{
    public function __construct(
        public ReturnEvent $event,
        public string $transactionId,
        public string $orderRef,
        public string $merchant,
        public int $responseCode,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            event: ReturnEvent::fromApi(PayloadReader::string($payload, 'e')),
            transactionId: PayloadReader::string($payload, 't'),
            orderRef: PayloadReader::string($payload, 'o'),
            merchant: PayloadReader::string($payload, 'm'),
            responseCode: PayloadReader::int($payload, 'r'),
        );
    }
}
