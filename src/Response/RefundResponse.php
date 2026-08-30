<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\Money;

final readonly class RefundResponse
{
    public function __construct(
        public string $merchant,
        public string $orderRef,
        public string $transactionId,
        public Money $refundTotal,
        public Money $remainingTotal,
        public ?string $refundTransactionId = null,
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
            refundTotal: Money::fromApiValue(PayloadReader::scalarAmount($payload, 'refundTotal'), $currency),
            remainingTotal: Money::fromApiValue(PayloadReader::scalarAmount($payload, 'remainingTotal'), $currency),
            refundTransactionId: PayloadReader::nullableString($payload, 'refundTransactionId'),
        );
    }
}
