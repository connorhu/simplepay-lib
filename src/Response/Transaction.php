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
        public ?Money $remainingTotal = null,
        public ?\DateTimeImmutable $paymentDate = null,
        public ?\DateTimeImmutable $finishDate = null,
        public ?PaymentMethod $method = null,
        public ?string $resultCode = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $currencyCode = PayloadReader::nullableString($payload, 'currency');
        // isset() kezeli az explicit `null` és a hiányzó kulcs esetét egyformán mindkét
        // összeg-mezőnél — a SimplePay nem szokott explicit nullt küldeni, így ez a
        // megkülönböztetés szándékosan nem számít itt.
        $hasTotal = isset($payload['total']);
        $hasRemainingTotal = isset($payload['remainingTotal']);

        $currency = null;

        if (null !== $currencyCode) {
            $currency = Currency::fromApi($currencyCode);
        } elseif ($hasTotal || $hasRemainingTotal) {
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
            total: ($hasTotal && null !== $currency)
                ? Money::fromApiValue(PayloadReader::scalarAmount($payload, 'total'), $currency)
                : null,
            remainingTotal: ($hasRemainingTotal && null !== $currency)
                ? Money::fromApiValue(PayloadReader::scalarAmount($payload, 'remainingTotal'), $currency)
                : null,
            paymentDate: PayloadReader::nullableDateTime($payload, 'paymentDate'),
            finishDate: PayloadReader::nullableDateTime($payload, 'finishDate'),
            method: null === $method ? null : PaymentMethod::fromApi($method),
            resultCode: PayloadReader::nullableString($payload, 'resultCode'),
        );
    }
}
