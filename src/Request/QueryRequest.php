<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

use CodeConjure\SimplePay\Exception\ConfigurationException;

/**
 * A SimplePay listát vár, nem skalárt: `transactionIds` és `orderRefs`.
 */
final readonly class QueryRequest
{
    /**
     * @param list<string> $transactionIds
     * @param list<string> $orderRefs
     */
    public function __construct(
        public array $transactionIds = [],
        public array $orderRefs = [],
        public bool $detailed = false,
        public bool $refunds = false,
    ) {
        if ([] === $transactionIds && [] === $orderRefs) {
            throw new ConfigurationException(
                'A lekérdezéshez legalább egy transactionId vagy orderRef kell.',
            );
        }
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        $payload = [];

        if ([] !== $this->transactionIds) {
            $payload['transactionIds'] = $this->transactionIds;
        }

        if ([] !== $this->orderRefs) {
            $payload['orderRefs'] = $this->orderRefs;
        }

        if ($this->detailed) {
            $payload['detailed'] = true;
        }

        if ($this->refunds) {
            $payload['refunds'] = true;
        }

        return $payload;
    }
}
