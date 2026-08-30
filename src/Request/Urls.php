<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

/**
 * Mind az öt cím kötelező. A négy visszairányítási cím (success/fail/cancel/
 * timeout) a SimplePay felé a `start` kérés `urls` map-jeként megy ki.
 *
 * FONTOS, sandbox kontraktus-teszttel megerősítve (Task 13): a hivatalos
 * SimplePay v2 dokumentáció szerint az IPN (fizetési értesítés) címét NEM a
 * `start` kérés hordozza — azt a kereskedői admin felületen, a "Technikai
 * adatok" fülön kell beállítani. Az `ipn` mező itt a `dn` kulcs alatt megy ki
 * a payloadban, de ez a mező nem szerepel a dokumentált API-ban; a sandbox azt
 * csendben figyelmen kívül hagyja (nem hibázik rá, de nem is használja). Ne
 * bízz abban, hogy ennek beállítása bármit befolyásol — az IPN cím a
 * kereskedői fiók beállításából származik, nem ebből az objektumból.
 */
final readonly class Urls
{
    public function __construct(
        public string $success,
        public string $fail,
        public string $cancel,
        public string $timeout,
        public string $ipn,
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
            'dn' => $this->ipn,
        ];
    }
}
