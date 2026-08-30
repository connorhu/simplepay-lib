<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Internal\PayloadReader;

/**
 * A SimplePay a lekérdezés eredményét a `transactions` tömbben adja vissza,
 * a státusz azon belül van — nem a válasz legfelső szintjén.
 */
final readonly class QueryResponse
{
    /** @param list<Transaction> $transactions */
    public function __construct(
        public array $transactions,
        public int $totalCount,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $transactions = array_map(
            Transaction::fromPayload(...),
            PayloadReader::mapList($payload, 'transactions'),
        );

        return new self($transactions, PayloadReader::int($payload, 'totalCount'));
    }

    public function first(): ?Transaction
    {
        return $this->transactions[0] ?? null;
    }

    public function byOrderRef(string $orderRef): ?Transaction
    {
        foreach ($this->transactions as $transaction) {
            if ($transaction->orderRef === $orderRef) {
                return $transaction;
            }
        }

        return null;
    }
}
