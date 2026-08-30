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

Az `ipn()` egy harmadik, opcionális `?DateTimeImmutable $receivedAt` paramétert is elfogad. Ez nem protokoll-mező, hanem tesztelhetőségi seam: a válaszba beírt `receiveDate` időbélyegét adja meg — alapértelmezés szerint a hívás pillanata (`new DateTimeImmutable()`). Determinisztikus időbélyeget enged átadni, ahelyett hogy a rendszeridőt kellene mockolni.

### Visszatérés a fizetőoldalról

```php
$data = $client->parseReturn($_GET['r'], $_GET['s']);
```

**A visszatérési adat tájékoztató, nem bizonyíték.** Ez az az adat, ami a vásárló böngészőjén keresztül érkezik vissza; az aláírás miatt nem hamisítható, de attól még csak azt mondja meg, mit lát a vásárló. A rendelés állapotát mindig a `query()` vagy az IPN döntse el.

## Hibakezelés

A kivétel-hierarchia nem aszerint tagolódik, hol keletkezett a hiba, hanem aszerint, hogy a hívónak mit kell tennie vele:

| Kivétel | Mikor | Mit tegyen a hívó |
|---|---|---|
| `ConfigurationException` | hiányzó/rossz merchant, secret, üres/hiányzó kötelező mező (pl. `orderRef`, `urls`) | ember kell, kódot vagy konfigot javítani |
| `TransportException` | hálózat, időtúllépés, nem-JSON válasz a SimplePay-től | újrapróbálható |
| `SignatureException` | bejövő aláírás nem stimmel | soha ne próbáld újra, logolj |
| `UnexpectedResponseException` | hiányzó kötelező mező, ismeretlen státusz, értelmezhetetlen érték | a csomag hibája, jelenteni kell |
| `RequestException` | a SimplePay elutasította a kérést | hibakódtól függ |
| `DeveloperException` (a `RequestException` alatt) | a hibakód szerint a mi hibánk | kódot javítani |

Mind implementálja a `SimplePayException` interfészt, tehát egyetlen `catch` mindet elkapja. A `DeveloperException` azért a `RequestException` leszármazottja, hogy egy `catch (RequestException)` mindkettőt elkapja, de aki külön akarja kezelni a "ezt a kódban kell javítani" esetet, az is megtehesse.

```php
use CodeConjure\SimplePay\Exception\RequestException;
use CodeConjure\SimplePay\Exception\SignatureException;
use CodeConjure\SimplePay\Exception\SimplePayException;
use CodeConjure\SimplePay\Exception\TransportException;

try {
    $response = $client->start($startRequest);
} catch (TransportException $e) {
    // hálózat/időtúllépés — biztonságos később újrapróbálni
    throw $e;
} catch (RequestException $e) {
    // a SimplePay elutasította — $e->codes(), $e->errors() a hibakódokért/leírásokért
    foreach ($e->errors() as $error) {
        // $error->code, $error->description, $error->isDeveloperError
    }
} catch (SimplePayException $e) {
    // minden más SimplePay-specifikus hiba (aláírás, váratlan válasz, konfiguráció)
    throw $e;
}
```

**A csomag sosem próbál újra automatikusan.** Egy vak `/start` újrapróbálkozás dupla terhelést okozhatna a vásárló kártyáján — az újrapróbálás döntése (ha egyáltalán van) mindig a hívóé, sosem a csomagé.

**Az időtúllépés a beadott PSR-18 kliens felelőssége**, nem a csomagé. A `Client` konstruktorban kapott `ClientInterface` implementáció (pl. `symfony/http-client`) dönt arról, mennyi ideig vár egy válaszra — a csomag ezt nem konfigurálja és nem korlátozza.

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
| Fizetési mód: átutalás | — | ⚠️ a `WIRE` érték küldhető és visszafejthető, de az átutalásos folyamat élőben sosem lett kipróbálva |
| Pénznem: HUF, EUR, USD | — | ⚠️ HUF élő sandboxon ellenőrizve; EUR/USD implementálva és egységtesztelve, élőben sosem próbálva |
| Fizetőoldal nyelve: HU, EN, DE | — | ⚠️ HU élő sandboxon ellenőrizve; EN/DE implementálva és egységtesztelve, élőben sosem próbálva |
| További nyelvek | — | ⬜ |
| Lekérdezés jóváírásokkal (`query` `refunds:true`) | `POST /query` | ⬜ szándékosan kihagyva |
| Lekérdezés részletes adatokkal (`query` `detailed:true`) | `POST /query` | ⚠️ a kérés mindig `detailed:true`-t küld, a válasz extra mezői kihagyva |

A `refunds:true` valóban hiányzik: az alakja (`refundStatus`, `refunds[]`) sandboxból strukturálisan sosem figyelhető meg, mert jóváírást csak befejezett fizetésen lehet indítani, azt pedig a csomag tesztsuite-ja emberi kattintás nélkül nem tudja előállítani. A `detailed:true` viszont **minden `query()` hívással kimegy** — ez nem opcionális, hívó által kikapcsolható viselkedés, hanem a csomag belső, szándékos döntése a `currency` mező biztosítására (lásd fent). Amit ez a kapcsoló emellett hozna (`customer`, `customerEmail`, `invoice{}`, `delivery`, `twoStep`, `shippingCost`, `discount`, és egy nem dokumentált `currencyEnum` mező), azt a csomag válasz-DTO-i továbbra sem olvassák ki.

**A kártya, a HUF és a HU nyelv az egyetlen kombináció, amit az élő sandbox kontraktus-tesztek ténylegesen elküldtek a SimplePay-nek.** A `StartContractTest` a `StartRequest` alapértelmezéseit használja (`methods: [PaymentMethod::Card]`, `language: Language::Hu`), a teszt-kereskedő (`PUBLICTESTHUF`) pedig csak HUF-ot fogad. A `WIRE` fizetési mód, az `EUR`/`USD` pénznem és az `EN`/`DE` nyelv mindegyike helyesen szerializálódik és értelmeződik vissza — egységtesztekkel lefedve —, de a SimplePay sosem látta őket egyetlen kérésben sem: sem az nem ismert, hogy a szolgáltatás elfogadja-e, sem az, hogy az átutalásos fizetés speciális, a kártyás folyamattól eltérő, a beérkezésig nyitva maradó folyamatát a csomag helyesen kezelné-e — erről a csomag jelenleg semmit nem modellez.

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

A sandbox tesztek a valódi válaszokat `tests/Fixtures/sandbox/` alá írják, és a unit tesztek ezeket játsszák vissza. A `raw_*.json` fixture-ök a SimplePay nyers, byte-szintű válaszát rögzítik (érzékeny mezők — `customer`, `customerEmail`, `invoice`, `salt` — nélkül, lásd a design spec 13. fejezetét); a mellettük lévő, azonos nevű (előtag nélküli) fixture-ök a csomag saját DTO-in keresztül szerializált, ember által olvasható összefoglalók — a regressziót a nyers fixture-ök ellenőrzik, ezek nem a csomag saját szerializálását parsolnák vissza.

### A nightly sandbox job — mit csinál, és mit nem

A `.github/workflows/sandbox.yaml` minden éjjel (és kézzel is indítható) lefuttatja a `sandbox` csoportot egy eldobható CI-runneren. **A job sosem ír vissza semmit a repóba** — a runner fájlrendszere a job végén megsemmisül. Amit ténylegesen csinál:

1. Lefuttatja a kontraktus-teszteket — ezek a runner lemezén felülírják/létrehozzák a fixture-fájlokat a friss SimplePay-válaszokkal.
2. A frissen rögzített fixture-könyvtárat build artifactként feltölti, hogy egy ember megnézhesse, mi változott.
3. Összeveti a runner lemezén lévő (frissen rögzített) állapotot a repóban committolt állapottal — módosult és vadonatúj fájlokkal együtt. Ha van eltérés, **a job hangosan elbukik** — ez a nightly jelzés.

**A fixture repóba kerülése emberi lépés marad**: valaki letölti a job artifactját (vagy lokálisan futtatja a sandbox csoportot), megnézi a diffet, és ha helyesnek ítéli, commitolja. Csak ezután, a következő rendes CI-futáson kezdhet el bukni a gyors unit suite, ha a frissített fixture már nem illeszkedik a válasz-osztályok elvárásaihoz.

## Licenc

MIT
