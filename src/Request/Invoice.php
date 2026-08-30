<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

use CodeConjure\SimplePay\Exception\ConfigurationException;

final readonly class Invoice
{
    public function __construct(
        public string $name,
        public string $country,
        public string $city,
        public string $zip,
        public string $address,
        public ?string $address2 = null,
        public ?string $state = null,
        public ?string $phone = null,
    ) {
        foreach ([
            'name' => $name,
            'country' => $country,
            'city' => $city,
            'zip' => $zip,
            'address' => $address,
        ] as $field => $value) {
            if ('' === $value) {
                throw new ConfigurationException(sprintf(
                    'A számlázási cím "%s" mezője nem lehet üres.',
                    $field,
                ));
            }
        }
    }

    /** @return array<string, string> */
    public function toPayload(): array
    {
        return array_filter([
            'name' => $this->name,
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'zip' => $this->zip,
            'address' => $this->address,
            'address2' => $this->address2,
            'phone' => $this->phone,
        ], static fn (?string $value): bool => null !== $value && '' !== $value);
    }
}
