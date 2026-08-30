<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\Money;

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
