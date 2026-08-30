# codeconjure/simplepay

OTP SimplePay v2 protokoll-kliens PHP-hoz. Payum-, Sylius- és keretrendszer-mentes.

Ez a három rétegre bontott SimplePay integráció legalsó rétege:

- **`codeconjure/simplepay`** — ez a csomag: aláírás, endpointok, DTO-k, hibakódok, IPN
- `codeconjure/simplepay-payum` — Payum actionök és gateway factory
- `codeconjure/simplepay-sylius-plugin` — Sylius admin és rendelés-leképezés

## Telepítés

```bash
composer require codeconjure/simplepay
```

A csomag nem hoz magával HTTP-implementációt. Kell mellé egy PSR-18 kliens és egy PSR-17 factory:

```bash
composer require symfony/http-client nyholm/psr7
```

## Használat

```php
use CodeConjure\SimplePay\{Client, Config, Currency, Environment, Money};
use CodeConjure\SimplePay\Request\{Invoice, StartRequest, Urls};

$factory = new Nyholm\Psr7\Factory\Psr17Factory();

$client = new Client(
    new Config('MERCHANT', 'SECRET', Environment::Sandbox),
    new Symfony\Component\HttpClient\Psr18Client(),
    $factory,
    $factory,
);

$response = $client->start(new StartRequest(
    orderRef: 'ORDER-1',
    total: Money::fromMinorUnits(1000, Currency::HUF),
    customerEmail: 'vevo@example.com',
    invoice: new Invoice('Teszt Elek', 'HU', 'Budapest', '1011', 'Fő utca 1.'),
    urls: new Urls(
        success: 'https://bolt.hu/vissza?e=success',
        fail: 'https://bolt.hu/vissza?e=fail',
        cancel: 'https://bolt.hu/vissza?e=cancel',
        timeout: 'https://bolt.hu/vissza?e=timeout',
    ),
));

header('Location: ' . $response->paymentUrl);
```

### Összegek

A `Money::fromMinorUnits()` a **pénznem valódi kitevője** szerinti alegységet várja: HUF esetén egész forintot, EUR és USD esetén centet. Ha a hívó rendszer pénznemtől függetlenül kétszázados ábrázolást használ — mint a Sylius —, az átváltás a hívó dolga.

### A visszairányítási címek: `urls`, nem `url`

A `start` kérés négy kimenetelhez (siker, hiba, megszakítás, időtúllépés) négy külön címet vár a `urls` objektumban — mind a négy kötelező. A SimplePay hivatalos dokumentációja egy másik, string alakú `url` mezőt is elfogad (egyetlen közös visszairányítási cím minden kimenetelre), és kimondja, hogy ha egy tranzakcióban mindkettő jelen van, az `url` figyelmen kívül marad. Ez a csomag **szándékosan mindig a differenciált `urls` formát küldi, sosem az `url` mezőt** — a hívó így már a böngésző visszatértekor tudja, mi történt, ahelyett hogy egy közös oldalon kellene ezt utólag egy `query()` hívásból kitalálnia.

**Nincs per-request IPN-cím mező** — sem `url`, sem `urls` alatt, és semmilyen más néven. A SimplePay hivatalos dokumentációja szerint az értesítés (IPN) címét a kereskedői vezérlőpanelen, a „Technikai adatok” menüpont alatt kell beállítani, fiókonként külön. Ha több cím kell egy fiókon belül eseményenként, azt a `start` kérés nem tudja befolyásolni.

### Tranzakció lekérdezése

```php
$response = $client->query(new CodeConjure\SimplePay\Request\QueryRequest(orderRefs: ['ORDER-1']));

$transaction = $response->byOrderRef('ORDER-1');
$transaction?->status;         // TransactionStatus
$transaction?->total;          // ?Money
$transaction?->remainingTotal; // ?Money
```

A csomag minden lekérdezésnél a SimplePay `detailed: true` kapcsolóját küldi — nem választható, mindig kimegy. Ok: a `detailed` nélküli, alap `/query` válasz az összegeket (`total`, `remainingTotal`) `currency` nélkül küldi vissza, egy pénznem nélküli összeget pedig a csomag jogosan nem tud értelmezni. Ennek valódi ára van: a `detailed: true` válasz a vevő nevét, e-mail címét és számlázási címét is tartalmazza minden egyes lekérdezésnél — a csomag ezeket a mezőket eldobja, de a byte-ok addigra végigmentek a hálózaton, és megjelenhetnek bármilyen HTTP-szintű naplózásban, amit a hívó rendszer bekapcsolt (PSR-18 kliens middleware, proxy log, stb.).

### Jóváírás

```php
$response = $client->refund(new CodeConjure\SimplePay\Request\RefundRequest(
    refundTotal: Money::fromMinorUnits(500, Currency::HUF),
    orderRef: 'ORDER-1',
));

$response->refundTotal;      // Money
$response->remainingTotal;   // Money — mennyi maradt jóváírható a tranzakcióból
```

### Értesítés (IPN)

A SimplePay addig ismétli az értesítést, amíg meg nem kapja a `receiveDate` mezővel kiegészített, aláírt visszaigazolást:

```php
$confirmation = $client->ipn($rawRequestBody, $signatureHeader);

// $confirmation->message()->status  →  TransactionStatus
// válasz: 200, törzs $confirmation->responseBody(),
//         Signature fejléc $confirmation->responseSignature()
```

### Visszatérés a fizetőoldalról

```php
$data = $client->parseReturn($_GET['r'], $_GET['s']);
```

**A visszatérési adat tájékoztató, nem bizonyíték.** Ez az az adat, ami a vásárló böngészőjén keresztül érkezik vissza; az aláírás miatt nem hamisítható, de attól még csak azt mondja meg, mit lát a vásárló. A rendelés állapotát mindig a `query()` vagy az IPN döntse el.

## Feature mátrix

| Képesség | Endpoint | Állapot |
|---|---|---|
| Egylépéses fizetés | `POST /start` | ✅ |
| Tranzakció lekérdezés | `POST /query` | ✅ |
| Jóváírás, teljes és részleges | `POST /refund` | ✅ |
| IPN fogadás + `receiveDate` válasz | — | ✅ |
| Visszatérési adat (`r`/`s`) ellenőrzés | — | ✅ |
| Válasz-aláírás ellenőrzés | — | ✅ |
| Kétlépéses fizetés | `POST /finish` | ⬜ tervezett |
| Ismétlődő fizetés | `POST /do`, `/auto` | ⬜ |
| Tárolt kártya | `/cardquery`, `/cardcancel` | ⬜ |
| Token kezelés | `/tokenquery`, `/tokencancel` | ⬜ |
| Fizetési mód: kártya | — | ✅ |
| Fizetési mód: átutalás | — | ⬜ |
| Pénznem: HUF, EUR, USD | — | ✅ |
| Fizetőoldal nyelve: HU, EN, DE | — | ✅ |
| További nyelvek | — | ⬜ |
| Lekérdezés jóváírásokkal (`query` `refunds:true`) | `POST /query` | ⬜ szándékosan kihagyva |
| Lekérdezés részletes adatokkal (`query` `detailed:true`) | `POST /query` | ⚠️ a kérés mindig `detailed:true`-t küld, a válasz extra mezői kihagyva |

A `refunds:true` valóban hiányzik: az alakja (`refundStatus`, `refunds[]`) sandboxból strukturálisan sosem figyelhető meg, mert jóváírást csak befejezett fizetésen lehet indítani, azt pedig a csomag tesztsuite-ja emberi kattintás nélkül nem tudja előállítani. A `detailed:true` viszont **minden `query()` hívással kimegy** — ez nem opcionális, hívó által kikapcsolható viselkedés, hanem a csomag belső, szándékos döntése a `currency` mező biztosítására (lásd fent). Amit ez a kapcsoló emellett hozna (`customer`, `customerEmail`, `invoice{}`, `delivery`, `twoStep`, `shippingCost`, `discount`, és egy nem dokumentált `currencyEnum` mező), azt a csomag válasz-DTO-i továbbra sem olvassák ki.

## Ismert bizonytalanságok

- **A `receiveDate` formátuma dokumentumból és a hivatalos SDK forrásából megerősítve, élőben még nem visszaigazolva.** A hivatalos dokumentáció kimondja, hogy minden időpontot ISO 8601 stringként (kettőspontos időzóna-eltolással) kell átadni — ez megegyezik a `\DateTimeInterface::ATOM`-mal, amit a csomag használ; a hivatalos SDK referencia-implementációja is ugyanezt a PHP-beli, ISO 8601-nek megfelelő formátumot használja a `receiveDate` előállításához. A dokumentum egyetlen, 2019-es keltezésű IPN-válasz példája ettől eltér (kettőspont nélküli eltolás) — ez a dokumentum saját, elavult, azóta nem frissített illusztrációja, nem egy második szabály; minden későbbi (2025–2026) példa a kettőspontos formátumot használja. Amit ez nem zár le: hogy egy valódi, élesben elküldött visszaigazolást a SimplePay ténylegesen elfogad-e — ehhez kívülről elérhető URL kell, amit a csomag tesztsuite-ja nem tud előállítani. Az empirikus megerősítés a Payum- és Sylius-réteg üzembe helyezésekor, az első valódi sandbox-fizetés IPN-jéből várható.
- **Egy sikeres jóváírás válaszának pontos alakja dokumentum alapján javítva, élőben még nem ellenőrizve.** A hivatalos dokumentáció szerint a `/refund` válasz mezői `salt`, `merchant`, `orderRef`, `currency`, `transactionId`, `refundTransactionId`, `refundTotal`, `remainingTotal` — nincs közöttük `status` mező. A csomag `RefundResponse`-a ezt tükrözi. Amit a sandbox eddig produkálni tudott, az kizárólag egy elutasított jóváírás (ismeretlen tranzakcióra hivatkozó hiba) volt — egy valódi, sikeres jóváírás csak egy már befejezett fizetésen indítható, azt pedig a tesztsuite emberi kattintás nélkül nem tudja előállítani.
- **A `TransactionStatus` enum két, a hivatalos dokumentumban nem talált tagja szándékosan megtartva.** A dokumentáció kilenc tranzakció-státuszt sorol fel; a csomag enumja tizenegyet — `FRAUD` és `REFUND` nincs a dokumentált listában. Szándékosan nem törölve: egy plusz enum-eset, ami sosem érkezik meg, semmibe nem kerül; egy hiányzó eset viszont éles fizetési státuszon dobna hibát, és megakasztaná a rendelést.

## Tesztelés

```bash
vendor/bin/phpunit                 # gyors unit tesztek, hálózat nélkül
vendor/bin/phpunit --group sandbox # valódi SimplePay sandbox ellen
vendor/bin/phpstan analyse -c phpstan.dist.neon
vendor/bin/ecs check
```

A sandbox tesztek a valódi válaszokat `tests/Fixtures/sandbox/` alá írják, és a unit tesztek ezeket játsszák vissza. A `raw_*.json` fixture-ök a SimplePay nyers, byte-szintű válaszát rögzítik; a mellettük lévő, azonos nevű (előtag nélküli) fixture-ök a csomag saját DTO-in keresztül szerializált, ember által olvasható összefoglalók — a regressziót a nyers fixture-ök ellenőrzik, ezek nem a csomag saját szerializálását parsolnák vissza.

## Licenc

MIT
