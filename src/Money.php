<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

final readonly class Money
{
    private function __construct(
        public int $minorUnits,
        public Currency $currency,
    ) {
    }

    public static function fromMinorUnits(int $amount, Currency $currency): self
    {
        return new self($amount, $currency);
    }

    public static function fromDecimalString(string $amount, Currency $currency): self
    {
        $trimmed = trim($amount);

        if (1 !== preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $trimmed, $matches)) {
            throw new UnexpectedResponseException(sprintf('Nem értelmezhető összeg: "%s".', $amount));
        }

        $exponent = $currency->exponent();
        $fraction = $matches[3] ?? '';

        if (strlen($fraction) > $exponent) {
            throw new UnexpectedResponseException(sprintf(
                'A(z) %s legfeljebb %d tizedesjegyet enged, kapott: "%s".',
                $currency->value,
                $exponent,
                $amount,
            ));
        }

        $digits = $matches[2] . str_pad($fraction, $exponent, '0');
        $minorUnits = (int) $digits;
        $normalisedDigits = ltrim($digits, '0');

        if ('' === $normalisedDigits) {
            $normalisedDigits = '0';
        }

        if ((string) $minorUnits !== $normalisedDigits) {
            throw new UnexpectedResponseException(sprintf(
                'A(z) "%s" összeg túl nagy, nem fér el egész számként.',
                $amount,
            ));
        }

        return new self('-' === $matches[1] ? -$minorUnits : $minorUnits, $currency);
    }

    public static function fromApiValue(string|int|float $value, Currency $currency): self
    {
        if (is_int($value)) {
            return self::fromDecimalString((string) $value, $currency);
        }

        if (is_float($value)) {
            $formatted = number_format($value, $currency->exponent(), '.', '');
            if ((float) $formatted !== $value) {
                throw new UnexpectedResponseException(sprintf(
                    'A(z) %s legfeljebb %d tizedesjegyet enged, kapott: "%s".',
                    $currency->value,
                    $currency->exponent(),
                    $value,
                ));
            }

            return self::fromDecimalString($formatted, $currency);
        }

        return self::fromDecimalString($value, $currency);
    }

    public function toApiValue(): string
    {
        $exponent = $this->currency->exponent();

        if (0 === $exponent) {
            return (string) $this->minorUnits;
        }

        $sign = $this->minorUnits < 0 ? '-' : '';
        $absolute = abs($this->minorUnits);
        $divisor = 10 ** $exponent;

        return sprintf(
            '%s%d.%s',
            $sign,
            intdiv($absolute, $divisor),
            str_pad((string) ($absolute % $divisor), $exponent, '0', \STR_PAD_LEFT),
        );
    }
}
