<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Sandbox;

/**
 * A sandbox kontraktus-tesztek nyers fixture-rögzítője elé ül: a rögzítés
 * pillanatában cseréli le az érzékeny mezők ÉRTÉKÉT egy nyilvánvalóan
 * szintetikus jelölőre, mielőtt a JSON lemezre kerülne — lásd a design
 * spec 13. fejezetét.
 *
 * Szándékosan NEM törli a kulcsot. A nyers fixture-ök egyetlen célja
 * bizonyítani, mit küld ténylegesen a SimplePay — melyik mező van jelen, és
 * milyen típusú. Ha egy érzékeny mezőt `unset()`-tel eltávolítanánk, a
 * fixture-ből olvasó ember nem tudná megkülönböztetni "a SimplePay sosem
 * küldte" és "mi töröltük" esetét — pedig pontosan ennek a két esetnek a
 * megkülönböztetése a fixture értelme. Ezért az alak (a kulcs jelenléte, egy
 * beágyazott objektum tagkulcsai) megmarad, csak a tartalom cserélődik le.
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
    /** A redaktált mezők helyettesítő értéke — szándékosan felismerhetően szintetikus. */
    public const string MARKER = '[REDACTED]';

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
                $payload[$key] = self::redactValue($value);

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = self::redact($value);
            }
        }

        return $payload;
    }

    /**
     * Egy skalár érzékeny mezőt a jelölőre cserél. Egy tömb/objektum
     * (pl. `invoice`) esetén megtartja a tagkulcsokat, és rekurzívan
     * ugyanígy jelöli a bennük lévő értékeket — az alak (mely mezők
     * léteznek) a bizonyíték része, csak a tartalom nem maradhat.
     */
    private static function redactValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return self::MARKER;
        }

        $redacted = [];

        foreach ($value as $key => $nestedValue) {
            $redacted[$key] = self::redactValue($nestedValue);
        }

        return $redacted;
    }
}
