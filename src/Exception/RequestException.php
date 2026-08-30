<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Exception;

use CodeConjure\SimplePay\Error\SimplePayError;

class RequestException extends \RuntimeException implements SimplePayException
{
    /** @param list<SimplePayError> $errors */
    final public function __construct(private readonly array $errors)
    {
        parent::__construct(sprintf(
            'A SimplePay elutasította a kérést — %s',
            implode('; ', array_map(strval(...), $errors)),
        ));
    }

    /** @param list<int> $codes */
    public static function fromCodes(array $codes): self
    {
        $errors = array_map(SimplePayError::fromCode(...), $codes);

        foreach ($errors as $error) {
            if ($error->isDeveloperError) {
                return new DeveloperException($errors);
            }
        }

        return new self($errors);
    }

    /** @return list<SimplePayError> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<int> */
    public function codes(): array
    {
        return array_map(static fn (SimplePayError $error): int => $error->code, $this->errors);
    }
}
