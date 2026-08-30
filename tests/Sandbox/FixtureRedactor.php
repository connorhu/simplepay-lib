<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Sandbox;

/**
 * A sandbox kontraktus-tesztek nyers fixture-rögzítője elé ül: a rögzítés
 * pillanatában távolítja el az érzékeny mezőket, mielőtt a JSON lemezre
 * kerülne — lásd a design spec 13. fejezetét.
 *
 * Szintetikus teszt-adattal (a publikus `PUBLICTESTHUF` sandbox-merchanttal)
 * ez ma ártalmatlan — a `customer`/`customerEmail`/`invoice` mezők maguk is
 * kitalált tesztadatok. De az always-on `detailed: true` (lásd
 * `QueryRequest`) miatt minden jövőbeli rögzítés ugyanezeket a mezőket
 * hordozná akkor is, ha valaki a `sandbox` csoportot saját, valódi
 * hitelesítő adatokkal, valódi rendelésen futtatná — ez a lista azt a
 * jövőbeli esetet védi ki, nem a jelen szintetikus adatot.
 */
final class FixtureRedactor
{
    /** @var list<string> */
    private const array REDACTED_KEYS = ['customer', 'customerEmail', 'invoice', 'salt'];

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return array<array-key, mixed>
     */
    public static function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array($key, self::REDACTED_KEYS, true)) {
                unset($payload[$key]);

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = self::redact($value);
            }
        }

        return $payload;
    }
}
