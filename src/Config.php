<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\ConfigurationException;

final readonly class Config
{
    public function __construct(
        public string $merchant,
        public string $secretKey,
        public Environment $environment,
    ) {
        if ('' === trim($merchant)) {
            throw new ConfigurationException('A SimplePay kliens nem indítható üres merchant azonosítóval.');
        }

        if ('' === trim($secretKey)) {
            throw new ConfigurationException('A SimplePay kliens nem indítható üres secretKey értékkel.');
        }
    }

    public function baseUrl(): string
    {
        return $this->environment->baseUrl();
    }

    public function signature(): Signature
    {
        return new Signature($this->secretKey);
    }
}
