<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Ipn;

use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\TransactionStatus;

final readonly class IpnMessage
{
    public function __construct(
        public string $merchant,
        public string $orderRef,
        public string $transactionId,
        public TransactionStatus $status,
        public ?PaymentMethod $method = null,
        public ?\DateTimeImmutable $paymentDate = null,
        public ?\DateTimeImmutable $finishDate = null,
        public ?string $salt = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $method = PayloadReader::nullableString($payload, 'method');

        return new self(
            merchant: PayloadReader::string($payload, 'merchant'),
            orderRef: PayloadReader::string($payload, 'orderRef'),
            transactionId: PayloadReader::string($payload, 'transactionId'),
            status: TransactionStatus::fromApi(PayloadReader::string($payload, 'status')),
            method: null === $method ? null : PaymentMethod::fromApi($method),
            paymentDate: PayloadReader::nullableDateTime($payload, 'paymentDate'),
            finishDate: PayloadReader::nullableDateTime($payload, 'finishDate'),
            salt: PayloadReader::nullableString($payload, 'salt'),
        );
    }
}
