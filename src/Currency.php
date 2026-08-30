<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

enum Currency: string
{
    case HUF = 'HUF';
    case EUR = 'EUR';
    case USD = 'USD';

    public function exponent(): int
    {
        return match ($this) {
            self::HUF => 0,
            self::EUR, self::USD => 2,
        };
    }

    public static function fromApi(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new UnexpectedResponseException(sprintf(
                'A SimplePay ismeretlen pénznemet küldött: "%s". A támogatottak: %s.',
                $value,
                implode(', ', array_column(self::cases(), 'value')),
            ));
    }
}
