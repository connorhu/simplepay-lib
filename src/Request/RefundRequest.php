<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

use CodeConjure\SimplePay\Exception\ConfigurationException;
use CodeConjure\SimplePay\Money;

final readonly class RefundRequest
{
    public function __construct(
        public Money $refundTotal,
        public ?string $orderRef = null,
        public ?string $transactionId = null,
    ) {
        if (!self::isPresent($orderRef) && !self::isPresent($transactionId)) {
            throw new ConfigurationException(
                'A jóváíráshoz orderRef vagy transactionId kell.',
            );
        }
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        $payload = [
            'refundTotal' => $this->refundTotal->toApiValue(),
            'currency' => $this->refundTotal->currency->value,
        ];

        if (self::isPresent($this->orderRef)) {
            $payload['orderRef'] = $this->orderRef;
        }

        if (self::isPresent($this->transactionId)) {
            $payload['transactionId'] = $this->transactionId;
        }

        return $payload;
    }

    /**
     * Egy `null` vagy üres string nem azonosít semmit — a hívó gyakran
     * hiányzó adatot `''`-re redukál, ezt itt kell elkapni, nem az API-nál.
     */
    private static function isPresent(?string $value): bool
    {
        return null !== $value && '' !== $value;
    }
}
