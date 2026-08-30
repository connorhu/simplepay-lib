<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\TransactionStatus;

final readonly class Transaction
{
    public function __construct(
        public string $merchant,
        public string $orderRef,
        public string $transactionId,
        public TransactionStatus $status,
        public ?Money $total = null,
        public ?\DateTimeImmutable $paymentDate = null,
        public ?PaymentMethod $method = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $total = null;
        $currencyCode = PayloadReader::nullableString($payload, 'currency');
        $hasTotal = isset($payload['total']);

        if (null !== $currencyCode && $hasTotal) {
            $total = Money::fromApiValue(
                PayloadReader::scalarAmount($payload, 'total'),
                Currency::fromApi($currencyCode),
            );
        } elseif (null === $currencyCode && $hasTotal) {
            $transactionId = PayloadReader::nullableString($payload, 'transactionId');

            throw new UnexpectedResponseException(sprintf(
                'A SimplePay tranzakció összeget küldött pénznem nélkül%s.',
                null !== $transactionId ? sprintf(' (tranzakcióazonosító: %s)', $transactionId) : '',
            ));
        }

        $method = PayloadReader::nullableString($payload, 'method');

        return new self(
            merchant: PayloadReader::string($payload, 'merchant'),
            orderRef: PayloadReader::string($payload, 'orderRef'),
            transactionId: PayloadReader::string($payload, 'transactionId'),
            status: TransactionStatus::fromApi(PayloadReader::string($payload, 'status')),
            total: $total,
            paymentDate: PayloadReader::nullableDateTime($payload, 'paymentDate'),
            method: null === $method ? null : PaymentMethod::fromApi($method),
        );
    }
}
