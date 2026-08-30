<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

enum Environment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox => 'https://sandbox.simplepay.hu/payment/v2/',
            self::Production => 'https://secure.simplepay.hu/payment/v2/',
        };
    }
}
