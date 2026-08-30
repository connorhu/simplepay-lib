<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

use CodeConjure\SimplePay\Exception\ConfigurationException;

/**
 * A SimplePay listát vár, nem skalárt: `transactionIds` és `orderRefs`.
 *
 * A `detailed` és `refunds` kérési kapcsolók szándékosan hiányoznak a
 * publikus felületről. Mindkettő a dokumentáció szerint valódi, meglévő
 * SimplePay funkció, de az általuk hozott extra mezőket (`customer`,
 * `customerEmail`, `invoice`, `delivery`, `twoStep`, `shippingCost`,
 * `discount`, illetve `refundStatus`/`refunds[]`) a `Transaction`/
 * `QueryResponse` jelenleg nem olvassa ki — egy publikus kapcsoló így néma
 * ígéret lenne. A `refunds` alakja emellett sandboxból sosem figyelhető
 * meg (jóváírás csak befejezett fizetésen indítható, azt a tesztsuite
 * emberi kattintás nélkül nem tudja előállítani); a `detailed` alakja
 * megfigyelhető volt (Task 13), de egy nem dokumentált mezőt
 * (`currencyEnum`) is tartalmazott, és a teljes modellezéshez a
 * request-oldali `Invoice`-tól eltérő válasz-oldali reprezentáció kellene
 * — önálló tervezési döntés, nem old meg útközben. Lásd a design spec 7.
 * és 16. fejezetét.
 *
 * A `detailed: true`-t a `toPayload()` ENNEK ELLENÉRE mindig kiküldi,
 * belső implementációs részletként, nem a hívó számára választható
 * opcióként. Az ok: élő sandbox méréssel megerősítve (Task 13), az
 * alapértelmezett (nem részletes) `/query` válasz a `total`/
 * `remainingTotal` mezőket `currency` NÉLKÜL küldi vissza — a
 * `Transaction::fromPayload()` pedig jogosan hangos hibát dob egy
 * pénznem nélküli összegre, mert nem tudja, hogyan értelmezze. A
 * `detailed: true` válasz mindig tartalmazza a `currency` mezőt is,
 * enélkül a csomag a `query()` leggyakoribb, legalapvetőbb használatakor
 * (egy imént indított tranzakció összegének/állapotának lekérdezésekor)
 * minden alkalommal dobna. A `detailed` extra mezőit (customer, invoice
 * stb.) a `Transaction` továbbra sem olvassa ki — ez a mező csak a
 * `currency` biztosítására szolgál, nem a részletes adatok kiajánlására.
 *
 * ADATVÉDELMI KÖVETKEZMÉNY: mivel a `detailed: true` mindig kimegy, a
 * SimplePay válasza minden `query()` hívásnál tartalmazza a vevő nevét
 * (`customer`), e-mail címét (`customerEmail`) és számlázási címét
 * (`invoice{}`) is — még ha a `Transaction` ezeket el is dobja, a
 * byte-ok akkor is végigmentek a hálózaton, és megjelenhetnek bármilyen
 * HTTP-szintű naplózásban, amit a csomagot használó rendszer bekapcsolt
 * (pl. PSR-18 kliens middleware, proxy log). Ez a `currency` mező
 * biztosításának valódi ára — nem elméleti, hanem a jelen tervezési
 * döntés tényleges következménye.
 */
final readonly class QueryRequest
{
    /** @var list<string> */
    public array $transactionIds;

    /** @var list<string> */
    public array $orderRefs;

    /**
     * @param list<string> $transactionIds
     * @param list<string> $orderRefs
     */
    public function __construct(
        array $transactionIds = [],
        array $orderRefs = [],
    ) {
        $transactionIds = self::withoutBlanks($transactionIds);
        $orderRefs = self::withoutBlanks($orderRefs);

        if ([] === $transactionIds && [] === $orderRefs) {
            throw new ConfigurationException(
                'A lekérdezéshez legalább egy transactionId vagy orderRef kell.',
            );
        }

        $this->transactionIds = $transactionIds;
        $this->orderRefs = $orderRefs;
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        // Lásd az osztály docblockját: ez nem publikus opció, hanem a
        // currency mező biztosítása a total/remainingTotal értelmezéséhez.
        $payload = ['detailed' => true];

        if ([] !== $this->transactionIds) {
            $payload['transactionIds'] = $this->transactionIds;
        }

        if ([] !== $this->orderRefs) {
            $payload['orderRefs'] = $this->orderRefs;
        }

        return $payload;
    }

    /**
     * Egy üres string a listában nem azonosít semmit; kiszűrjük, mielőtt
     * eldöntenénk, hogy a lekérdezés üres-e, és mielőtt kimenne a payloadban.
     *
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function withoutBlanks(array $values): array
    {
        return array_values(array_filter(
            $values,
            static fn (string $value): bool => '' !== $value,
        ));
    }
}
