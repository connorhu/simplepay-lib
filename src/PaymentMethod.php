<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

enum PaymentMethod: string
{
    case Card = 'CARD';
    case Wire = 'WIRE';

    public static function fromApi(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new UnexpectedResponseException(sprintf(
                'A SimplePay ismeretlen fizetési módot küldött: "%s".',
                $value,
            ));
    }
}
