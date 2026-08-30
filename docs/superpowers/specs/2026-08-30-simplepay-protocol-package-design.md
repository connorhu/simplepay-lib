# `codeconjure/simplepay` — protokoll-csomag design

> **Státusz:** Jóváhagyva.
> **Dátum:** 2026-08-30
> **Fázis:** 1 / 2. A Payum-adapter és a Sylius plugin külön specet kap, a jelen csomag elkészülte után.
> **Elsődleges forrás:** *SimplePay v2.1 fejlesztői dokumentáció* — `SimplePay_2.x_Payment_HU_260504.pdf`
> (2026.05.04-es revízió), elérhető a https://simplepay.hu/fejlesztoknek/ oldalon
> (közvetlen letöltés: `https://simplepartner.hu/download.php?target=v21dochu`). Ahol ez a spec
> protokoll-tényt állít, az erre a dokumentumra vezethető vissza; az élő sandbox mérés (Task 13)
> megerősítés, nem elsődleges forrás. A rövidebb hivatkozásokban lent: „a hivatalos dokumentáció”.

---

## 1. Cél

Önálló, Payum- és keretrendszer-mentes Composer csomag, amely az OTP SimplePay v2 REST API protokollját implementálja: aláírás, endpointok, kérés- és válaszalakok, hibakódok, IPN és a visszatérési adat ellenőrzése.

Ez a három rétegre bontott SimplePay integráció legalsó rétege:

```
codeconjure/simplepay                  ← ez a spec
  CodeConjure\SimplePay\
  függ: psr/http-client, psr/http-factory, psr/http-message
  Payum: NINCS. Sylius: NINCS. Symfony: NINCS.
        ↓
codeconjure/simplepay-payum
  CodeConjure\SimplePayPayum\
  Payum actionök, GatewayFactory, Symfony Bundle
        ↓
codeconjure/simplepay-sylius-plugin
  CodeConjure\SimplePaySyliusPlugin\
  admin form, Payment→payload leképezés, visszatérési controller
```

## 2. Kontextus: miért készül

A `egyhazzene.hu/bolt` appban ma `src/Components/Payum/SimplePay/` alatt él egy 2270 soros SimplePay integráció, és létezik egy `connorhu/simplepay-payum` GitHub repo 460 soros implementációval. Mindkettőt felmértük. A megállapított hibák:

**Csak a meglévő libben:**

1. HMAC-SHA256 aláírás a kötelező SHA-384 helyett — minden hívás visszapattanna.
2. Éles URL `simplepay.otpbank.hu`, ami nem válaszol. A helyes `secure.simplepay.hu`.
3. Kitalált státusznevek: `COMPLETE`, `WAITING`, `IN_PAYMENT`, `CARD_DECLINED` — a SimplePay ilyeneket nem küld.

**Csak az appban:**

4. A `NotifyAction` halott kód: a modellből olvas egy `simplepay_webhook_payload` kulcsot, amit soha semmi nem tölt fel. Nincs `GetHttpRequest`, nincs nyers törzs.
5. snake_case/camelCase keveredés: a payload factory `orderRef`-et gyárt, a `CaptureAction` `order_ref`-et olvas; a `StatusAction` `transaction_id`/`order_ref` kulcsokkal kérdez le a `transactionIds`/`orderRefs` helyett.
6. A bejövő webhook aláírását a dekódolt tömb újrakódolásával ellenőrzi, nem a kapott byte-okon.
7. Az `r`/`s` visszatérési paramétereket nem ellenőrzi.

**Mindkettőben:**

8. A `/query` válasz `transactions[]` tömböt ad, a státusz azon belül van; mindkettő a legfelső szinten keresi, tehát sosem találja.
9. Az IPN-re nem megy vissza a kötelező `receiveDate`-tel kiegészített, aláírt válasz, ezért a SimplePay újraküldözgetné az értesítést.
10. Az app csak egyetlen közös `url` mezőt küld a `start` kérésben (a SimplePay dokumentált, érvényes, string alakú formája, de nem ad kimenetelenként — siker/hiba/megszakítás/időtúllépés — differenciált visszairányítást). A dokumentált differenciált forma az `urls` objektum (`success`/`fail`/`cancel`/`timeout`), amit az app nem használ.
    > **Korrekció (Task 13, élő sandbox kontraktus-teszt, 2026-08-30):** ez a pont eredetileg úgy volt megfogalmazva, hogy „az `urls` blokk (`success`/`fail`/`cancel`/`timeout`/`dn`) hiányzik […] és az IPN célcím (`dn`) sosem megy ki”. Két dolog volt benne pontatlan. Először: `url` nem hibás vagy hiányzó mező — ez a SimplePay hivatalos, string alakú, egyszerű formájának a neve; a hiány nem az, hogy `url` létezik-e, hanem hogy az app nem él a differenciált `urls` alternatívával. Másodszor és súlyosabban: nincs per-request IPN-cím mező a dokumentált API-ban, sem `url`, sem `urls` alatt — tehát „a `dn` sosem megy ki” állítás értelmetlen volt, mert nincs is olyan mező, aminek ki kellene mennie. Az IPN célcím kizárólag a kereskedői admin felületen, fiókszinten állítható be. Mindkét tévedés a jelen csomag saját `Urls`/`StartRequest` tervébe is átkerült (lásd 7. fejezet): a terv felvett egy nemlétező `ipn`/`dn` mezőt, ÉS az objektumot tévedésből a string-et váró `url` kulcs alá csomagolta ahelyett, hogy a differenciált formához tartozó `urls` kulcsot használta volna. Csak az élő sandbox buktatta le: mivel `url` stringet vár, nem objektumot, a szolgáltatás 5321-es hibakóddal („Formátumhiba / érvénytelen JSON string”) utasította el a start-kérést, aláírási hiba nélkül. Javítva: a payload `urls` alatt megy ki, az `ipn`/`dn` mező pedig törölve, mert a valódi API-ban nincs ilyen. A hivatalos dokumentáció (a projekt tulajdonosa által idézett szöveg) azt is rögzíti, hogy ha egy tranzakcióban mindkét mező (`url` és `urls`) jelen van, az `url` figyelmen kívül marad — ezért a `StartRequest::toPayload()` szándékosan sosem emittál `url` kulcsot `urls` mellett; ezt a `tests/Unit/Request/StartRequestTest.php::testUrlsAreSentAsAMapUnderTheUrlsKey` explicit `assertArrayNotHasKey('url', ...)` hívása pinneli le, nem csak a sandbox futás alkalmi megfigyelése.

**A közös gyökér:** egyik implementáció sem beszélt soha az igazi SimplePay sandboxszal. Mindkettő mockolt HTTP-kliens ellen tesztelt, így a mockba beleírt téves feltevés zöld tesztként jelent meg. A jelen csomag tesztstratégiája (13. fejezet) elsősorban ezt a gyökeret célozza.

Élesben nincs forgalom, a bolt fejlesztés alatt áll, ezért **adatmigrációra nincs szükség** és a kulcsnevek szabadon törhetők.

## 3. Csomag-azonosítók

| | |
|---|---|
| Composer név | `codeconjure/simplepay` |
| Namespace | `CodeConjure\SimplePay` |
| Teszt namespace | `CodeConjure\SimplePay\Tests` |
| Licenc | MIT |
| Helye | `/server/www/egyhazzene.hu/incubator/simplepay/` |
| Git | https://github.com/connorhu/simplepay-lib |

## 4. Követelmények

**Futásidő:**

- `php: ^8.4` — a 8.4 és a 8.5 az aktívan támogatott ág; az app is 8.4-en fut
- `psr/http-client: ^1.0`
- `psr/http-factory: ^1.0`
- `psr/http-message: ^2.0`

`php-http/discovery` **nem** függősége ennek a csomagnak. A HTTP-klienst, request- és stream-factoryt a hívó adja be konstruktorban. Az automatikus felderítés a Payum-csomag gyárának a dolga, ahol amúgy is konfigurációból épül minden.

**Fejlesztés:**

- `phpunit/phpunit: ^12.0`
- `php-http/mock-client: ^1.6`
- `nyholm/psr7: ^1.8`
- `phpstan/phpstan: ^2.0`
- `sylius-labs/coding-standard: ^4.4` (ECS, az apppal azonos konvenció)

## 5. Fájlstruktúra

```
codeconjure/simplepay
├── src/
│   ├── Client.php
│   ├── Config.php
│   ├── Environment.php              enum
│   ├── Signature.php
│   ├── SaltGenerator.php
│   ├── Currency.php                 enum
│   ├── Language.php                 enum
│   ├── Money.php
│   ├── PaymentMethod.php            enum
│   ├── TransactionStatus.php        enum
│   ├── ReturnEvent.php              enum
│   ├── Request/
│   │   ├── StartRequest.php
│   │   ├── QueryRequest.php
│   │   ├── RefundRequest.php
│   │   ├── Invoice.php
│   │   └── Urls.php
│   ├── Response/
│   │   ├── StartResponse.php
│   │   ├── QueryResponse.php
│   │   ├── Transaction.php
│   │   ├── RefundResponse.php
│   │   └── ReturnData.php
│   ├── Ipn/
│   │   ├── IpnMessage.php
│   │   └── IpnConfirmation.php
│   ├── Error/
│   │   ├── SimplePayError.php
│   │   └── ErrorCatalog.php
│   └── Exception/
│       ├── SimplePayException.php   interface
│       ├── ConfigurationException.php
│       ├── TransportException.php
│       ├── SignatureException.php
│       ├── UnexpectedResponseException.php
│       ├── RequestException.php
│       └── DeveloperException.php
├── tests/
│   ├── Unit/…
│   ├── Sandbox/…                    #[Group('sandbox')]
│   └── Fixtures/sandbox/*.json      a sandbox futás írja, a unit tesztek olvassák
├── .github/workflows/ci.yaml
├── .github/workflows/sandbox.yaml
├── composer.json
├── ecs.php
├── phpstan.dist.neon
├── phpunit.xml.dist
└── README.md
```

## 6. Hatókör

**Benne:**

- `POST /start` — egylépéses fizetés indítása
- `POST /query` — tranzakció lekérdezés
- `POST /refund` — teljes és részleges jóváírás
- IPN fogadás, aláírás-ellenőrzés és a kötelező `receiveDate`-es válasz felépítése
- Visszatérési adat (`r`/`s`) aláírás-ellenőrzése és dekódolása
- Kártyás fizetési mód, HUF / EUR / USD pénznem

**Kívül, a feature mátrixban „még nem" jelöléssel:**

- Kétlépéses fizetés (`POST /finish`)
- Ismétlődő fizetés (`POST /do`, `POST /auto`)
- Tárolt kártya és token kezelés
- Átutalásos fizetési mód

**Elvi határ:** ez a csomag nem ismer fizetés-állapotgépet. Hogy melyik státuszból melyikbe szabad lépni, hogy egy duplán érkező értesítést el kell dobni, és hogy mit írunk audit logba — mindez a Payum-csomagba tartozik. Ez a réteg csak azt tudja, mit mond a SimplePay.

## 7. Publikus felület

### Client

```php
final readonly class Client
{
    public function __construct(
        private Config $config,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private SaltGenerator $saltGenerator = new SaltGenerator(),
    ) {}

    public function start(StartRequest $request): StartResponse;
    public function query(QueryRequest $request): QueryResponse;
    public function refund(RefundRequest $request): RefundResponse;

    public function ipn(string $rawBody, string $signatureHeader): IpnConfirmation;
    public function parseReturn(string $r, string $s): ReturnData;
}
```

### Config és Environment

```php
final readonly class Config
{
    public function __construct(
        public string $merchant,
        public string $secretKey,
        public Environment $environment,
    ) {}   // üres merchant vagy secretKey → ConfigurationException
}

enum Environment: string
{
    case Sandbox    = 'sandbox';
    case Production = 'production';

    public function baseUrl(): string;
    // Sandbox:    https://sandbox.simplepay.hu/payment/v2/
    // Production: https://secure.simplepay.hu/payment/v2/
}
```

A `secure.simplepay.hu` hosztot HTTP-próbával ellenőriztük: válaszol. A meglévő lib `simplepay.otpbank.hu` hosztja nem válaszol.

### Money és Currency

```php
enum Currency: string
{
    case HUF = 'HUF';
    case EUR = 'EUR';
    case USD = 'USD';

    public function exponent(): int;   // HUF: 0, EUR: 2, USD: 2
}

final readonly class Money
{
    public static function fromMinorUnits(int $amount, Currency $currency): self;
    public static function fromDecimalString(string $amount, Currency $currency): self;

    public function toApiValue(): string;   // a pénznem kitevője szerint formázva
}
```

A `total` mező elrontása néma és drága hiba. Az app ma minden pénznemre `->div(100, 2)`-t számol, tehát HUF-nál is két tizedest gyárt. A `Currency` ismeri a saját kitevőjét, a `Money` eszerint formáz, így a hívó nem tud rossz tizedesszámot előállítani.

**Határ:** a `fromMinorUnits()` a *pénznem valódi kitevője* szerinti alegységet vár — HUF esetén tehát egész forintot. A Sylius belső, pénznemtől független kétszázados ábrázolásából való átváltás a Sylius plugin dolga, nem ezé a rétegé.

### StartRequest és társai

```php
final readonly class StartRequest
{
    public function __construct(
        public string $orderRef,
        public Money $total,
        public string $customerEmail,
        public Invoice $invoice,
        public Urls $urls,
        public Language $language = Language::Hu,
        /** @var non-empty-list<PaymentMethod> */
        public array $methods = [PaymentMethod::Card],
        public ?DateTimeImmutable $timeout = null,
        public ?string $customer = null,
    ) {}
}

final readonly class Urls
{
    public function __construct(
        public string $success,
        public string $fail,
        public string $cancel,
        public string $timeout,
    ) {}
}

final readonly class Invoice
{
    public function __construct(
        public string $name,
        public string $country,
        public string $city,
        public string $zip,
        public string $address,
        public ?string $address2 = null,
        public ?string $state = null,
        public ?string $phone = null,
    ) {}
}
```

Az `Urls` mind a négy címet kötelezővé teszi. A hivatalos SimplePay dokumentáció szerint a `start` kérés kétféle formát fogad el: egy string `url` mezőt (egyetlen közös visszairányítási cím minden kimenetelre), vagy egy objektum `urls` mezőt a differenciált success/fail/cancel/timeout címekkel — és ha mindkettő jelen van egy tranzakcióban, az `url` figyelmen kívül marad. Ez a csomag szándékosan mindig a differenciált formát küldi, és kizárólag azt — a hívónak többet ér tudni, hogy a vásárló sikeresen fizetett, elutasították, megszakította vagy időtúllépés érte, mint a közös URL egyszerűsége —, ezért a payload mindig `urls` alatt megy ki, és sosem emittál `url` kulcsot mellé, hogy ne maradjon kétséges, melyik mező érvényesülne (ezt a `StartRequestTest::testUrlsAreSentAsAMapUnderTheUrlsKey` explicit `assertArrayNotHasKey('url', …)` hívása pinneli le). `url` maga **nem hibás vagy hiányzó kulcs**: ha objektumot csomagolunk alá string helyett, azt a SimplePay 5321-es hibakóddal ("Formátumhiba / érvénytelen JSON string") jogosan utasítja el — ez volt az app és e csomag korábbi terve közös hibája: nem rossz mezőnevet használtak, hanem rossz típust írtak egy valódi mezőbe. **Nincs per-request IPN-cím mező** — sem `url`, sem `urls` alatt, semmilyen néven. A SimplePay v2 API-ban az IPN célcímet a kereskedői admin felület ("Technikai adatok" fül) adja meg, fiókszinten — ezt az osztályt nem terheli meg vele. (Ez a bekezdés eredetileg öt kötelező címet és egy `ipn`/`dn` mezőt írt elő, és tévesen úgy fogalmazott, mintha `url` maga lenne hibás kulcs; a hivatalos dokumentáció mezőlistája — a projekt tulajdonosa által idézve — és az élő sandbox kontraktus-teszt együtt pontosították, lásd a 2. fejezet 10. pontjának korrekcióját.)

### QueryRequest és RefundRequest

```php
final readonly class QueryRequest
{
    /** @param list<string> $transactionIds @param list<string> $orderRefs */
    public function __construct(
        public array $transactionIds = [],
        public array $orderRefs = [],
        public bool $detailed = false,
        public bool $refunds = false,
    ) {}   // mindkét lista üres → ConfigurationException
}

final readonly class RefundRequest
{
    public function __construct(
        public Money $refundTotal,
        public ?string $orderRef = null,
        public ?string $transactionId = null,
    ) {}   // mindkettő null → ConfigurationException
}
```

A SimplePay `transactionIds` és `orderRefs` néven **listát** vár. Az app ma skalár `transaction_id`/`order_ref` kulcsokat küld — kétszeresen hibás, névben és típusban is.

### Válasz-DTO-k

```php
final readonly class StartResponse
{
    public string $salt, $merchant, $orderRef, $transactionId, $paymentUrl;
    public Money $total;
    public DateTimeImmutable $timeout;
}

final readonly class QueryResponse
{
    /** @var list<Transaction> */
    public array $transactions;
    public int $totalCount;

    public function first(): ?Transaction;
    public function byOrderRef(string $orderRef): ?Transaction;
}

final readonly class Transaction
{
    public string $merchant, $orderRef, $transactionId;
    public TransactionStatus $status;
    public Money $total;
    public ?DateTimeImmutable $paymentDate;
    public ?PaymentMethod $method;
}

final readonly class RefundResponse
{
    public string $merchant, $orderRef, $transactionId;
    public ?string $refundTransactionId;
    public ?TransactionStatus $status;
}
```

A `QueryResponse` szerkezete a `transactions[]` tömb köré épül — pontosan az, amit mindkét mai implementáció félreolvas.

> **Dokumentum-egyeztetés (Task 13, 2026-08-30, a hivatalos dokumentáció `3.13 query` és `3.16 refund` szakasza alapján):** a fenti `Transaction` és `RefundResponse` a válasz egy részhalmazát modellezi, nem a teljeset. A dokumentált `/query` válasz minden tranzakción tartalmaz egy `resultCode` (pl. `"OK"`, sosem magyarázva) és egy `remainingTotal` mezőt is, valamint külön `finishDate`-et a `paymentDate` mellett — ezeket a `Transaction` jelenleg nem olvassa ki, csendben eldobja. Ha a lekérdezés a `refunds` paraméterrel megy ki, minden tranzakció kaphat egy `refundStatus` (pl. `"PARTIAL"`) és egy `refunds` listát (`transactionId`, `refundTotal`, `refundDate`, `status` elemekkel) is — ezt a `Transaction`/`QueryResponse` jelenleg egyáltalán nem modellezi. A dokumentált `/refund` válasz mezői — `merchant`, `orderRef`, `currency`, `transactionId`, `refundTransactionId`, `refundTotal`, `remainingTotal` — nem tartalmaznak `status` mezőt (a `RefundResponse.status` jelenleg mindig `null` marad éles válaszon), viszont a `RefundResponse` nem olvassa ki a dokumentált `currency`, `refundTotal` és `remainingTotal` mezőket. Ez utóbbi — a visszatérítés utáni fennmaradó, még visszatéríthető összeg — pénzügyi szempontból releváns adat, amit a hívó jelenleg csak egy külön `query(refunds: true)` hívással tudna megszerezni. **Ez egy talált, de NEM javított eltérés** — a döntést, hogy a `Transaction`/`RefundResponse` bővüljön-e ezekkel a mezőkkel, szándékosan a projekt tulajdonosára hagyva, mert ez nyilvános DTO-alak-bővítés, nem a Task 13 fixture/teszt-korrekciós hatókörébe tartozó javítás. Lásd a Task 13 jelentését a teljes idézetekért.

## 8. Aláírás és HTTP-réteg

### Az aláírás egyetlen szabálya

HMAC-SHA384 a **pontos byte-sorozat** felett, base64-elve, `Signature` fejlécben.

```php
final readonly class Signature
{
    public function sign(string $body): string;
    public function verify(string $body, string $signature): bool;   // hash_equals
}
```

`string` megy be, **nem `array`**. Ez a csomag legfontosabb szerkezeti döntése.

Az app ma tömböt ad át az aláírónak, az újrakódolja JSON-ná, és azt írja alá. Kimenő irányban ez véletlenül működik, mert ugyanazokkal a flagekkel kódol, mint a küldés. Bejövő irányban viszont eleve romlott: a SimplePay által küldött JSON byte-jait sosem látjuk, csak a saját újrakódolásunkat. Az első escape-elési eltérésnél — egy ékezet, egy perjel egy URL-ben — az ellenőrzés hamisan bukik, és a bolt eldobja a valódi fizetési értesítést.

Ha az aláíró csak stringet fogad, ez a hiba nem írható le. Kimenő irányban a `Client` egyszer sorosít egy `$body` változóba, azt írja alá és azt küldi el; nincs második kódolás, ami elcsúszhatna.

### Kimenő kérés

Minden kimenő kérés automatikusan kap `merchant`, `salt` és `sdkVersion` mezőt; a hívónak ezekkel nem kell foglalkoznia. A salt 32 karakter (a SimplePay 32–64 közötti hosszt fogad el, ezen kívül 5401-es hibát ad).

JSON sorosítás `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR` flagekkel. A flagek megválasztása szabad, mert a SimplePay a *nálunk keletkezett* byte-ok felett ellenőrzi az aláírásunkat.

### Bejövő válasz

Három lépés, ebben a sorrendben:

1. HTTP státuszkód — nem 2xx esetén a törzset még feldolgozzuk a hibakódokért, ha JSON
2. **A válasz `Signature` fejlécének ellenőrzése a kapott byte-okon** — ez ma egyik implementációban sincs meg
3. A törzsben érkező SimplePay hibakódok kiértékelése

Újrapróbálkozás nincs: a `/start` nem idempotens, egy vak retry dupla tranzakciót csinálhat. Az időtúllépés a beadott PSR-18 kliens dolga.

## 9. IPN

A SimplePay a kereskedői admin felületen ("Technikai adatok" oldal, "Rendszer értesítések" panel, fiókszintű beállítás — nincs per-request IPN-cím mező, lásd 7. fejezet) beállított IPN-címre POST-ol JSON-t `Signature` fejléccel, és elvárja, hogy a kapott JSON-t `receiveDate` mezővel kiegészítve, aláírva visszaküldd. Amíg ez nem megy vissza, a SimplePay úgy veszi, nem kaptuk meg, és újraküldi.

**A `receiveDate` formátuma (Task 13, a hivatalos dokumentáció alapján lezárva):** a dokumentáció kimondja, hogy „minden időpontot ISO 8601 szabvány szerinti stringként (2018-09-15T11:25:37+02:00) kell átadni” — ez az általános szabály, kettősponttal a időzóna-eltolásban, pontosan megegyezik a `\DateTimeInterface::ATOM` PHP-formátummal, amit a `Client::RECEIVE_DATE_FORMAT` már használ. A dokumentum egyetlen konkrét IPN-válasz mintája (3.14 szakasz) viszont kettőspont nélküli eltolást mutat (`"receiveDate":"2019-09-09T14:46:20+0200"`), ugyanígy a mellette lévő `finishDate`/`paymentDate` is — ez a dokumentum belső ellentmondása, nem új szabály: az összes többi, később dátumozott (2025–2026) példa a dokumentumban következetesen a kettőspontos formátumot használja. A kettőspont nélküli minta egy 2019-es, feltehetően azóta nem frissített illusztráció maradványa. Az `ATOM` formátum tehát marad — nincs kódmódosítás —, de ezt a belső dokumentum-ellentmondást érdemes tudni, ha valaha egy éles `receiveDate` visszautasításra kerülne.

```php
$confirmation = $client->ipn($rawBody, $signatureHeader);

$confirmation->message();            // IpnMessage — tipizált
$confirmation->responseBody();       // amit vissza kell küldeni
$confirmation->responseSignature();  // a Signature fejléc hozzá
```

A felület nem PSR-7 kérést fogad, hanem nyers törzset és fejlécet, mert így nem kell tudnia, melyik keretrendszer hogyan adja oda a body-t. A Payum-réteg csomagolja ki neki.

A `responseBody()` a **bejövő byte-okból** épül, nem egy újrasorosított tömbből: a `receiveDate` beszúrásán kívül semmi nem változik.

Hibás vagy hiányzó aláírás → `SignatureException`. A hívó ilyenkor 400-at ad vissza és nem dolgozza fel az üzenetet.

```php
final readonly class IpnMessage
{
    public string $salt, $merchant, $orderRef, $transactionId;
    public TransactionStatus $status;
    public ?PaymentMethod $method;
    public ?DateTimeImmutable $paymentDate, $finishDate;
}
```

## 10. Visszatérés a fizetésről

```php
$return = $client->parseReturn($r, $s);

$return->event;           // ReturnEvent: Success | Fail | Cancel | Timeout
$return->transactionId;
$return->orderRef;
$return->merchant;
```

Az `s` az `r` base64-sztring felett képzett aláírás. Eltérés → `SignatureException`.

**Dokumentált kikötés:** a visszatérési adat tájékoztató, nem bizonyíték. Ez az az adat, ami a vásárló böngészőjén keresztül érkezik vissza; az aláírás miatt nem hamisítható, de attól még csak azt mondja meg, mit lát a vásárló. A rendelés állapotát mindig a `/query` vagy az IPN dönti el. Ez a mondat bekerül a README-be is.

## 11. Hibakezelés

A kivétel-hierarchia nem aszerint tagolódik, hol keletkezett a hiba, hanem aszerint, hogy a hívónak mit kell tennie vele:

| Kivétel | Mikor | Mit tegyen a hívó |
|---|---|---|
| `ConfigurationException` | hiányzó/rossz merchant, secret, üres query | ember kell, kódot vagy konfigot javítani |
| `TransportException` | hálózat, időtúllépés, nem-JSON válasz | újrapróbálható |
| `SignatureException` | bejövő aláírás nem stimmel | soha ne próbáld újra, logolj |
| `UnexpectedResponseException` | hiányzó kötelező mező, ismeretlen státusz | a csomag hibája, jelenteni kell |
| `RequestException` | a SimplePay elutasította a kérést | hibakódtól függ |
| `DeveloperException` (a `RequestException` alatt) | a hibakód szerint a mi hibánk | kódot javítani |

Mind implementálja a `SimplePayException` interfészt, tehát egyetlen `catch` mindet elkapja.

A `DeveloperException` azért a `RequestException` leszármazottja, hogy egy `catch (RequestException)` mindkettőt elkapja, de aki külön akarja kezelni a „ezt a kódban kell javítani" esetet, az is megtehesse.

**Amiből nem lesz kivétel: az elutasított fizetés.** Ha a vásárló kártyáján nincs fedezet, az nem hiba, hanem eredmény — státuszként jön vissza, nem dobásként.

### Hibakód-katalógus

Az app ~300 soros magyar hibakód-tábláját átvisszük, de adatként, nem konstansként egy API-osztály közepén:

```php
final readonly class SimplePayError
{
    public int $code;
    public ?string $description;    // magyar, a SimplePay dokumentációjából
    public bool $isDeveloperError;
}

$e->errors();   // list<SimplePayError>
```

Ismeretlen hibakódnál a `description` `null`, de a szám mindig megvan — nem nyelhetünk el kódot csak azért, mert még nem láttuk. A leírások magyarul maradnak, mert a SimplePay saját dokumentációjából származnak és magyar kereskedőnek szólnak.

## 12. Státusz-enum

```php
enum TransactionStatus: string
{
    case Init          = 'INIT';
    case InPayment     = 'INPAYMENT';
    case Authorized    = 'AUTHORIZED';
    case Finished      = 'FINISHED';
    case Cancelled     = 'CANCELLED';
    case Timeout       = 'TIMEOUT';
    case NotAuthorized = 'NOTAUTHORIZED';
    case Fraud         = 'FRAUD';
    case InFraud       = 'INFRAUD';
    case Reversed      = 'REVERSED';
    case Refund        = 'REFUND';

    public function isFinal(): bool;
    public function isSuccessful(): bool;   // csak Finished
}
```

`isFinal()` igaz: `Finished`, `Cancelled`, `Timeout`, `NotAuthorized`, `Fraud`, `Reversed`, `Refund`.

**A néma degradáció megszüntetése.** Ma mindkét implementáció úgy van megírva, hogy az ismeretlen státuszból csendben `unknown` lesz. Ez pontosan az a szerkezet, ami miatt a hiba rejtve maradhat: a `FINISHED` egyik lista kulcsai közt sincs ott, tehát egy sikeres fizetés ma `unknown`-ra képződik le, és a kód ezt nem panaszolja, csak függőben hagyja a rendelést.

Az új viselkedés: ismeretlen státuszérték `UnexpectedResponseException`-t dob, a konkrét értéket megnevezve. Hangos hiba egy elrejtett helyett.

A fenti tagok a dokumentáció alapján kerültek fel; a végleges listát a sandbox kontraktus-teszt rögzíti (13. fejezet).

> **Dokumentum-egyeztetés (Task 13, 2026-08-30):** a hivatalos dokumentáció „2 Tranzakció és státuszai” szakasza egy explicit, kilenc soros táblázatban sorolja fel a `query`-vel lekérdezhető státuszokat: `INIT`, `TIMEOUT`, `CANCELLED`, `NOTAUTHORIZED`, `INPAYMENT`, `INFRAUD`, `AUTHORIZED`, `REVERSED`, `FINISHED`. A fenti enumban szereplő **`FRAUD` és `REFUND` egyike sem szerepel ebben a táblázatban**, sem máshol a dokumentum 95 oldalán önálló tranzakció-státuszként (az „INFRAUD” és a „fraud monitoring”/„fraud-vizsgálat” kifejezések igen, de azok nem azonosak a `FRAUD` állapottal). A `Refund` és `Fraud` esetek eredete a terv tervezési fázisából ered (lásd Task 6 brief), nem ebből a dokumentumból, és a sandbox élő futása során (Task 13) sem került elő egyik sem — a megfigyelt egyetlen valódi státusz `INIT` volt. **Nem törlöm őket**, mert a dokumentáció hiánya nem bizonyítja a nemlétezésüket (lehet elavult vagy nem kimerítő a lista, és mindkettő valós SimplePay-koncepció — csalásgyanú lezárása, illetve visszatérítés — még ha a `query` válasz „státusz” mezőjeként a dokumentáció szerint sosem jelennek is meg), de prominensen jelzem: **kilenc dokumentált érték áll szemben tizeneggyel a kódban**, és ha valaha éles válaszban egyik sem fordul elő, azt érdemes lenne felülvizsgálni, hogy tényleg a `TransactionStatus` enumhoz tartoznak-e, vagy egy másik mező (pl. a `refundStatus`, lásd 7. fejezet) értékei keveredtek ide tervezéskor.

## 13. Tesztstratégia

### A visszacsatolási hurok

A kétszintű tesztelés lényege nem a két szint, hanem hogyan kapcsolódnak. Ha a mockok kézzel írt fixture-öket használnak, ugyanoda jutunk, ahol most vagyunk: a mockba beleírt téves feltevés zöld tesztként ragyog.

```
phpunit --group sandbox    élő hívás → ellenőriz ÉS kiírja a választ
                                       tests/Fixtures/sandbox/*.json
                                             ↓
phpunit                    a unit tesztek EZEKET a fixture-öket játsszák vissza
```

A mockok tehát nem kitalált, hanem rögzített valóságot játszanak vissza. Ha a SimplePay megváltoztat egy válaszmezőt, a nightly sandbox futás frissíti a fixture-t, és a unit tesztek elkezdenek bukni — pont a kívánt helyen. A fixture-ök a repóban verziózva vannak, tehát a változás diffként látszik.

### Unit tesztek (alapértelmezett futás)

`php-http/mock-client` ellen, gyors, külső hívás nélkül:

- Aláírás: ismert bemenet → ismert kimenet vektorok, `verify()` pozitív és negatív eset
- DTO sorosítás: minden `Request` osztály → várt JSON kulcsok és típusok
- Válasz-parsolás a rögzített sandbox fixture-ökből
- Hibakód → kivételtípus leképezés, beleértve a `DeveloperException` szétválasztást
- Ismeretlen státusz → `UnexpectedResponseException`
- IPN: aláírás-ellenőrzés, `receiveDate` beszúrás, válasz-aláírás
- `parseReturn()`: érvényes és hamisított `r`/`s` pár
- `Money`: HUF 0 tizedes, EUR/USD 2 tizedes, kerekítés

### Sandbox kontraktus-tesztek

`#[Group('sandbox')]`, a `phpunit.xml.dist` alapértelmezésben kizárja. Publikus SimplePay teszt-merchanttal futnak, ezért a hozzáférési adatok a repóban lehetnek.

Amit rögzítenek — konkrétan az az öt dolog, amit ma egyik implementáció sem tud helyesen:

1. Elfogadja-e a sandbox a SHA-384 aláírásunkat a `/start`-on
2. A `/start` válasz tényleges mezőkészlete
3. A `/query` válasz szerkezete — a `transactions[]` tömb és a benne lévő mezők
4. A ténylegesen visszakapott státusz-sztringek — az enum teljességének próbája
5. Jelen van-e és stimmel-e a válaszok `Signature` fejléce

Minden sandbox teszt a kapott nyers választ kiírja `tests/Fixtures/sandbox/` alá, érzékeny mezők nélkül.

### Statikus elemzés

phpstan level 9 az `src/` és `tests/` felett, plusz ECS a `sylius-labs/coding-standard` szabálykészletével — ugyanaz a konvenció, mint az appban.

## 14. CI

Két workflow:

- **`ci.yaml`** — minden push és PR: unit tesztek, phpstan level 9, ECS. A `sandbox` csoport itt **nem fut**: külső szolgáltatástól nem függhet, hogy mergelhető-e egy változtatás.
- **`sandbox.yaml`** — nightly ütemezve és kézi indításra: a `sandbox` csoport. Bukás esetén önálló jelzés, nem blokkolja a fejlesztést.

## 15. Feature mátrix

A `README.md` állandó szekciója, ez adja a fogódzót a későbbi bővítéshez:

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

**Miért `urls`, és nem `url`?** A SimplePay hivatalos dokumentációja mindkettőt elfogadja a `start` kérésben: egy string `url` mezőt (egyetlen közös visszairányítási cím sikerre, hibára, megszakításra és időtúllépésre egyaránt), vagy egy objektum `urls` mezőt, amiben ez a négy kimenetel külön címet kap — szó szerint idézve: „ha nem egy közös URL-en, hanem az eseménynek megfelelő külön helyen dolgozza fel a kereskedő rendszere az authorizáció eredményét, akkor itt minden eseményhez külön URL adható meg […] Abban az esetben, ha az »url« és az »urls« is küldve van egy tranzakcióban, akkor az »url« nem lesz figyelembe véve.” Ez a csomag **szándékosan mindig a differenciált `urls` formát küldi, és soha nem az `url` mezőt** — annak tudása, hogy a vásárló sikeresen fizetett, elutasították, megszakította vagy időtúllépés érte, önmagában eldönti, mit mutasson a bolt a visszatérő vásárlónak, ahelyett hogy egyetlen közös oldalon kellene ezt utólag kitalálni a `query()` hívásból. Mivel a payload sosem tartalmazza mindkettőt, nincs kétértelműség afelől, melyik mező érvényesül — ezt kód szintjén a `StartRequestTest::testUrlsAreSentAsAMapUnderTheUrlsKey` explicit `assertArrayNotHasKey('url', …)` hívása is lepinneli. Aki a csomag kimenő payloadját a hivatalos mezőlistával veti össze, azért látja `url` helyett `urls`-t.

## 16. Ismert bizonytalanságok

Ez a szekció is a README része, nem csak a specé.

**A `receiveDate` pontos formátuma — a dokumentum-oldala lezárva (Task 13, 2026-08-30).** A hivatalos dokumentáció (`SimplePay_2.x_Payment_HU_260504.pdf`, lásd a spec elején) kimondja: „minden időpontot ISO 8601 szabvány szerinti stringként (2018-09-15T11:25:37+02:00) kell átadni” — ez megegyezik a `\DateTimeInterface::ATOM`-mal, amit a `Client::RECEIVE_DATE_FORMAT` már használ (részletek: 9. fejezet). Ami továbbra sem ellenőrizhető: hogy egy valódi, élesben küldött `receiveDate` visszaigazolást a SimplePay ténylegesen elfogad-e — ehhez kívülről elérhető URL kell, amit egy csomag tesztsuite-ja nem tud előállítani. Amit az 1. fázisban tesztelni tudunk: az aláírás-ellenőrzést és a válasz felépítését egy kézzel összerakott üzeneten. Az empirikus megerősítést a 2. fázis zárja le, amikor a Payum- és Sylius-réteg már a valódi boltban fut: az első igazi sandbox-fizetés IPN-jét fixture-ként rögzítjük.

**Egy sikeres jóváírás (`refund`) válaszának pontos alakja — részben lezárva dokumentum alapján, élőben még nem ellenőrizve (Task 13, 2026-08-30).** A hivatalos dokumentáció (3.16 szakasz) megadja a mezőket: `salt`, `merchant`, `orderRef`, `currency`, `transactionId`, `refundTransactionId`, `refundTotal`, `remainingTotal` — **nincs közöttük `status` mező**. A jelen `RefundResponse` viszont fordítva hiányos: van `status` mezője (ami dokumentáció szerint sosem kap értéket éles válaszon), de nem olvassa ki a dokumentált `currency`, `refundTotal` és `remainingTotal` mezőket (részletek: 7. fejezet). Ez utóbbi eltérést a dokumentáció már lezárja — nem kell hozzá élő adat —, de **szándékosan nem javítottam**, mert nyilvános DTO-alak-bővítés, amit a projekt tulajdonosának kell eldöntenie, nem a Task 13 hatóköre. Ami továbbra is csak sandboxból deríthető ki: hogy a dokumentált alak pontosan így érkezik-e meg élesben (mezőnevek, típusok), mert jóváírni csak egy már befejezett fizetést lehet, azt pedig a kontraktus-teszt emberi kattintás nélkül nem tudja előállítani — ugyanaz a szerkezeti korlát, mint a `receiveDate`-nél. A Task 13 sandbox kontraktus-tesztje ezért csak az elutasítás alakját (`errorCodes`) tudta rögzíteni (`tests/Fixtures/sandbox/refund_error.json`), a sikeres válasz alakját nem. A 2. fázisban, az első valódi sandbox-fizetés jóváírásakor ezt is fixture-ként kell rögzíteni, és a `FixtureConformanceTest`-nek a `refund.json`-on át kell ereszteni a `RefundResponse::fromPayload()`-ot.

**A `TransactionStatus` enum két, dokumentumban nem talált tagja.** (Task 13, 2026-08-30.) A hivatalos dokumentáció kilenc tranzakció-státuszt sorol fel; a kódban tizenegy van — `FRAUD` és `REFUND` nincs a dokumentált listában (részletek: 12. fejezet). Nem törölve, csak jelezve.

**A `/query` és `/refund` válasz-DTO-k dokumentált, de ki nem olvasott mezői.** (Task 13, 2026-08-30.) `resultCode`, `finishDate`, `remainingTotal` a `Transaction`-ből, `refundStatus`/`refunds[]` a `QueryResponse`-ból (ha `refunds: true`), `currency`/`refundTotal`/`remainingTotal` a `RefundResponse`-ból — mind dokumentált, egyik sincs kiolvasva (részletek: 7. fejezet). Talált, nem javított; a `Money`/pénznem-kezelés és a visszatérítés-utáni egyenleg lekérdezhetősége szempontjából ez a legrelevánsabb.

Ha később a dokumentáció és a sandbox között további eltérés derül ki, az is ide kerül, nem egy commit-üzenetbe.

## 17. Elfogadási kritériumok

A csomag akkor kész, ha:

1. `phpunit` zöld, külső hálózati hívás nélkül
2. `phpunit --group sandbox` zöld a valódi SimplePay sandbox ellen
3. A sandbox futás rögzítette a fixture-öket, és a unit tesztek ezeket használják
4. phpstan level 9 hibamentes az `src/` és `tests/` felett
5. ECS hibamentes
6. A `TransactionStatus` enum minden, a sandboxtól ténylegesen visszakapott státuszt tartalmaz
7. A `README.md` tartalmazza a feature mátrixot, az ismert bizonytalanságok listáját, és a visszatérési adatra vonatkozó „tájékoztató, nem bizonyíték" kikötést
8. A csomag `composer.json`-ja nem hivatkozik Payumra, Syliusra vagy Symfonyra

## 18. Hatókörön kívül

- A Payum actionök, a `GatewayFactory` és a Symfony Bundle — a `codeconjure/simplepay-payum` csomag, 2. fázis
- A Sylius admin konfigurációs form, a `Payment`→payload leképezés és a visszatérési controller — a `codeconjure/simplepay-sylius-plugin` csomag, 2. fázis
- Az app `src/Components/Payum/SimplePay/` könyvtárának törlése — 2. fázis
- Packagistra publikálás — külön döntés, a csomag elkészülte után
