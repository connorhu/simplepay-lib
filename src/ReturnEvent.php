<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

enum ReturnEvent: string
{
    case Success = 'SUCCESS';
    case Fail = 'FAIL';
    case Cancel = 'CANCEL';
    case Timeout = 'TIMEOUT';

    public static function fromApi(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new UnexpectedResponseException(sprintf(
                'A SimplePay ismeretlen visszatérési eseményt küldött: "%s".',
                $value,
            ));
    }
}
