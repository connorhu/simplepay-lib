<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

use CodeConjure\SimplePay\Exception\ConfigurationException;

/**
 * A SimplePay listát vár, nem skalárt: `transactionIds` és `orderRefs`.
 */
final readonly class QueryRequest
{
    /** @var list<string> */
    public array $transactionIds;

    /** @var list<string> */
    public array $orderRefs;

    public bool $detailed;

    public bool $refunds;

    /**
     * @param list<string> $transactionIds
     * @param list<string> $orderRefs
     */
    public function __construct(
        array $transactionIds = [],
        array $orderRefs = [],
        bool $detailed = false,
        bool $refunds = false,
    ) {
        $transactionIds = self::withoutBlanks($transactionIds);
        $orderRefs = self::withoutBlanks($orderRefs);

        if ([] === $transactionIds && [] === $orderRefs) {
            throw new ConfigurationException(
                'A lekérdezéshez legalább egy transactionId vagy orderRef kell.',
            );
        }

        $this->transactionIds = $transactionIds;
        $this->orderRefs = $orderRefs;
        $this->detailed = $detailed;
        $this->refunds = $refunds;
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

    /**
     * Egy üres string a listában nem azonosít semmit; kiszűrjük, mielőtt
     * eldöntenénk, hogy a lekérdezés üres-e, és mielőtt kimenne a payloadban.
     *
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function withoutBlanks(array $values): array
    {
        return array_values(array_filter(
            $values,
            static fn (string $value): bool => '' !== $value,
        ));
    }
}
