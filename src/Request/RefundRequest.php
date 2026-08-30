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
        if (null === $orderRef && null === $transactionId) {
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

        if (null !== $this->orderRef) {
            $payload['orderRef'] = $this->orderRef;
        }

        if (null !== $this->transactionId) {
            $payload['transactionId'] = $this->transactionId;
        }

        return $payload;
    }
}
