<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

use CodeConjure\SimplePay\Exception\ConfigurationException;

/**
 * Mind a négy cím kötelező. A hivatalos SimplePay dokumentáció szerint a
 * `start` kérés vagy egy string `url` mezőt fogad el (egyetlen közös
 * visszairányítási cím minden kimenetelre), vagy egy objektum `urls`
 * mezőt a differenciált success/fail/cancel/timeout címekkel — és ha
 * mindkettő jelen van egy tranzakcióban, az `url` figyelmen kívül marad.
 * Ez a csomag szándékosan mindig a differenciált formát küldi, és
 * kizárólag azt: sosem emittál `url` kulcsot `urls` mellett, hogy ne
 * maradjon kétséges, melyik payload-mező érvényesülne. A hívónak többet
 * ér tudni, hogy a vásárló sikeresen fizetett, elutasították,
 * megszakította vagy időtúllépés érte, mint a közös URL egyszerűsége.
 *
 * `url` NEM hiányzó vagy érvénytelen kulcs a SimplePay API-ban — ez a
 * dokumentált, string alakú, egyszerű forma neve. A korábbi hiba (Task 13,
 * sandbox kontraktus-teszttel felfedve) az volt, hogy ezt az objektumot
 * tévedésből az `url` kulcs alá csomagoltuk, egy stringet váró mezőbe. A
 * SimplePay erre 5321-es hibakóddal ("Formátumhiba / érvénytelen JSON
 * string") válaszolt — helyesen, hiszen objektumot kapott string helyett.
 * A javítás nem egy nemlétező kulcs helyesre cserélése volt, hanem a
 * differenciált formához tartozó, helyes kulcs (`urls`) használata.
 *
 * Nincs per-request IPN-cím mező sem (sem `url`, sem `urls` alatt, és
 * semmilyen más néven): a hivatalos dokumentáció szerint az IPN (fizetési
 * értesítés) címét NEM a `start` kérés hordozza. A dokumentáció ezt
 * kétszer, szó szerint egyformán írja le: „Az IPN URL beállítását a
 * kereskedői vezérlőpanelen lehet elvégezni. […] A címet a »Technikai
 * adatok« menüpont alatt lehet beállítani.” — fiókonként külön (ha a
 * kereskedő több fiókot használ, mindegyikben meg kell adni). Ne keress
 * ide paramétert az IPN cím megadására — nincs ilyen, és korábban egy
 * `ipn`/`dn` mező itt pontosan ezt a téves benyomást keltette (a sandbox
 * csendben eldobta, sosem routolt vele semmit).
 */
final readonly class Urls
{
    public function __construct(
        public string $success,
        public string $fail,
        public string $cancel,
        public string $timeout,
    ) {
        foreach ([
            'success' => $success,
            'fail' => $fail,
            'cancel' => $cancel,
            'timeout' => $timeout,
        ] as $field => $value) {
            if ('' === $value) {
                throw new ConfigurationException(sprintf(
                    'A visszairányítási címek "%s" mezője nem lehet üres.',
                    $field,
                ));
            }
        }
    }

    /** @return array<string, string> */
    public function toPayload(): array
    {
        return [
            'success' => $this->success,
            'fail' => $this->fail,
            'cancel' => $this->cancel,
            'timeout' => $this->timeout,
        ];
    }
}
