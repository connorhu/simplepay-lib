<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

/**
 * Mind a négy cím kötelező. A SimplePay hivatalos API-ja kétféle formát
 * fogad el a `start` kérésben: egy string `url` mezőt (egyetlen közös
 * visszairányítási cím minden kimenetelre), vagy egy objektum `urls`
 * mezőt a differenciált success/fail/cancel/timeout címekkel. Ez a
 * csomag szándékosan mindig a differenciált formát küldi — a hívónak
 * többet ér tudni, hogy a vásárló sikeresen fizetett, elutasították,
 * megszakította vagy időtúllépés érte, mint a közös URL egyszerűsége —
 * ezért a payload mindig `urls` alatt megy ki, sosem `url` alatt.
 *
 * FONTOS: `url` nem hiányzó vagy érvénytelen kulcs a SimplePay API-ban —
 * ez az egyszerű, string alakú forma neve. A korábbi hiba (Task 13,
 * sandbox kontraktus-teszttel felfedve) pontosan az volt, hogy ezt az
 * objektumot tévedésből az `url` kulcs alá csomagoltuk, egy stringet váró
 * mezőbe. A SimplePay erre 5321-es hibakóddal ("Formátumhiba / érvénytelen
 * JSON string") válaszolt — helyesen, hiszen objektumot kapott string
 * helyett. A javítás nem egy nemlétező kulcs helyesre cserélése volt,
 * hanem a differenciált formához tartozó, helyes kulcs (`urls`) használata.
 *
 * Nincs per-request IPN-cím mező sem (sem `url`, sem `urls` alatt, és
 * semmilyen más néven): a hivatalos dokumentáció szerint az IPN (fizetési
 * értesítés) címét NEM a `start` kérés hordozza, azt a kereskedői admin
 * felületen, a "Technikai adatok" fülön kell beállítani, fiókszinten. Ne
 * keress ide paramétert az IPN cím megadására — nincs ilyen, és korábban
 * egy `ipn`/`dn` mező itt pontosan ezt a téves benyomást keltette (a
 * sandbox csendben eldobta, sosem routolt vele semmit).
 */
final readonly class Urls
{
    public function __construct(
        public string $success,
        public string $fail,
        public string $cancel,
        public string $timeout,
    ) {
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
