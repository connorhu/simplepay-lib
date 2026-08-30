<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Internal;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

/**
 * Tipizált mezőolvasás a SimplePay nyers válaszaiból.
 *
 * Minden hiányzó vagy rossz típusú kötelező mező kivételt dob — soha nem
 * ad csendben alapértelmezett értéket.
 *
 * @internal
 */
final class PayloadReader
{
    /** @param array<string, mixed> $payload */
    public static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (!is_scalar($value) || '' === (string) $value) {
            throw self::missing($key, $value);
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $payload */
    public static function nullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (null === $value || !is_scalar($value) || '' === (string) $value) {
            return null;
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $payload */
    public static function int(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        if (!is_int($value) && !(is_string($value) && 1 === preg_match('/^-?\d+$/', $value))) {
            throw self::missing($key, $value);
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $payload */
    public static function scalarAmount(array $payload, string $key): string|int|float
    {
        $value = $payload[$key] ?? null;

        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw self::missing($key, $value);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public static function dateTime(array $payload, string $key): \DateTimeImmutable
    {
        $raw = self::string($payload, $key);

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception $exception) {
            throw new UnexpectedResponseException(
                sprintf('A SimplePay "%s" mezője nem értelmezhető dátum: "%s".', $key, $raw),
                previous: $exception,
            );
        }
    }

    /** @param array<string, mixed> $payload */
    public static function nullableDateTime(array $payload, string $key): ?\DateTimeImmutable
    {
        return null === self::nullableString($payload, $key) ? null : self::dateTime($payload, $key);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    public static function mapList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        if (!is_array($value)) {
            throw self::missing($key, $value);
        }

        $list = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new UnexpectedResponseException(sprintf(
                    'A SimplePay "%s" listájának minden eleme objektum kell legyen.',
                    $key,
                ));
            }

            /** @var array<string, mixed> $typedItem */
            $typedItem = $item;
            $list[] = $typedItem;
        }

        return $list;
    }

    private static function missing(string $key, mixed $value): UnexpectedResponseException
    {
        return new UnexpectedResponseException(sprintf(
            'A SimplePay válaszából hiányzik vagy rossz típusú a "%s" mező (kapott: %s).',
            $key,
            get_debug_type($value),
        ));
    }
}
