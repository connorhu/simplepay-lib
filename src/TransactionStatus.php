<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

enum TransactionStatus: string
{
    case Init = 'INIT';
    case InPayment = 'INPAYMENT';
    case Authorized = 'AUTHORIZED';
    case InFraud = 'INFRAUD';
    case Finished = 'FINISHED';
    case Cancelled = 'CANCELLED';
    case Timeout = 'TIMEOUT';
    case NotAuthorized = 'NOTAUTHORIZED';
    case Fraud = 'FRAUD';
    case Reversed = 'REVERSED';
    case Refund = 'REFUND';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Init, self::InPayment, self::Authorized, self::InFraud => false,
            default => true,
        };
    }

    public function isSuccessful(): bool
    {
        return self::Finished === $this;
    }

    public static function fromApi(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new UnexpectedResponseException(sprintf(
                'A SimplePay ismeretlen tranzakció-státuszt küldött: "%s". Az ismertek: %s.',
                $value,
                implode(', ', array_column(self::cases(), 'value')),
            ));
    }
}
