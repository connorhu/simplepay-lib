<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Error;

final readonly class SimplePayError
{
    public function __construct(
        public int $code,
        public ?string $description,
        public bool $isDeveloperError,
    ) {
    }

    public static function fromCode(int $code): self
    {
        return new self($code, ErrorCatalog::describe($code), ErrorCatalog::isDeveloperError($code));
    }

    public function __toString(): string
    {
        return null === $this->description
            ? sprintf('%d (ismeretlen hibakód)', $this->code)
            : sprintf('%d: %s', $this->code, $this->description);
    }
}
