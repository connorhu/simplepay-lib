<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

/**
 * Mind a négy cím kötelező, és a SimplePay felé a `start` kérés `urls`
 * map-jeként mennek ki (nem `url` — az egyes szám 5321-es hibakóddal
 * elutasításra kerül).
 *
 * Sandbox kontraktus-teszttel megerősítve (Task 13): a hivatalos SimplePay
 * v2 dokumentáció szerint az IPN (fizetési értesítés) címét NEM a `start`
 * kérés hordozza — nincs ilyen mező a dokumentált API-ban. Az IPN cím
 * kizárólag a kereskedői admin felületen, a "Technikai adatok" fülön
 * állítható be, fiókszinten. Ne keress ide paramétert az IPN cím
 * megadására — nincs ilyen, és korábban egy `ipn`/`dn` mező itt pontosan
 * ezt a téves benyomást keltette (a sandbox csendben eldobta, sosem
 * routolt vele semmit).
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
