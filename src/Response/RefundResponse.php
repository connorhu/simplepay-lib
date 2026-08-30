<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\TransactionStatus;

final readonly class RefundResponse
{
    public function __construct(
        public string $merchant,
        public string $orderRef,
        public string $transactionId,
        public ?string $refundTransactionId = null,
        public ?TransactionStatus $status = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $status = PayloadReader::nullableString($payload, 'status');

        return new self(
            merchant: PayloadReader::string($payload, 'merchant'),
            orderRef: PayloadReader::string($payload, 'orderRef'),
            transactionId: PayloadReader::string($payload, 'transactionId'),
            refundTransactionId: PayloadReader::nullableString($payload, 'refundTransactionId'),
            status: null === $status ? null : TransactionStatus::fromApi($status),
        );
    }
}
