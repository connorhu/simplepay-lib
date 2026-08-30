# `codeconjure/simplepay` protokoll-csomag — implementációs terv

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Egy Payum- és keretrendszer-mentes PHP csomag, amely az OTP SimplePay v2 REST API protokollját helyesen implementálja: aláírás, `/start`, `/query`, `/refund`, IPN és a visszatérési adat ellenőrzése.

**Architecture:** Alulról felfelé épül. Előbb az önálló értékosztályok (aláírás, pénz, enumok, kivételek), amelyek semmitől nem függenek; ezekre épülnek a kérés- és válasz-DTO-k; ezekre a `Client` transzport-rétege; végül az IPN és a visszatérési adat. A csomag semmilyen HTTP-implementációt nem hoz magával — PSR-18 klienst és PSR-17 factorykat kap konstruktorban. A tesztelés kétszintű: gyors mock-alapú unit tesztek, amelyek a valódi sandbox ellen futó kontraktus-tesztek által rögzített fixture-öket játsszák vissza.

**Tech Stack:** PHP 8.4, PSR-18 / PSR-17 / PSR-7, PHPUnit 12, PHPStan level 9, ECS (sylius-labs/coding-standard), `php-http/mock-client` és `nyholm/psr7` teszteléshez.

**Spec:** `docs/superpowers/specs/2026-08-30-simplepay-protocol-package-design.md`

## Global Constraints

Minden task követelményei implicit tartalmazzák ezt a szekciót.

- **Munkakönyvtár:** `/server/www/egyhazzene.hu/incubator/simplepay/` — minden útvonal ehhez képest relatív.
- **PHP alsó határ:** `^8.4`. A gépen `php` 8.5.9 (`/usr/bin/php`), a `composer` a `/usr/local/bin/composer`.
- **Namespace:** `CodeConjure\SimplePay\` → `src/`, `CodeConjure\SimplePay\Tests\` → `tests/`.
- **Tiltott függőség:** a `composer.json` nem hivatkozhat `payum/*`, `sylius/*` vagy `symfony/*` csomagra. A `php-http/discovery` sem függőség.
- **Engedélyezett futásidejű függőség:** `psr/http-client: ^1.0`, `psr/http-factory: ^1.0`, `psr/http-message: ^2.0`.
- **Aláírás:** HMAC-SHA384 a pontos byte-sorozat felett, base64-elve, `Signature` fejlécben. Az aláíró **soha nem fogad tömböt**, csak `string`-et.
- **Endpoint alap-URL:** sandbox `https://sandbox.simplepay.hu/payment/v2/`, éles `https://secure.simplepay.hu/payment/v2/`. (A `simplepay.otpbank.hu` nem válaszol, ne kerüljön be.)
- **Salt:** 32 karakter.
- **Hibaleírások nyelve:** magyar.
- **Statikus elemzés:** PHPStan level 9 az `src/` és `tests/` felett, hibamentesen. ECS hibamentesen.
- **Minden `final`:** minden osztály `final`, az adathordozók `readonly`.
- **Néma degradáció tilos:** ismeretlen enumérték vagy hiányzó kötelező válaszmező kivételt dob, nem alapértelmezett értéket ad.
- **Commit üzenetek magyarul**, Conventional Commits előtaggal (`feat:`, `test:`, `docs:`, `chore:`).

---

## Fájlstruktúra

Amit a terv létrehoz, felelősség szerint:

| Fájl | Felelősség |
|---|---|
| `src/Environment.php` | A két környezet és a hozzájuk tartozó alap-URL |
| `src/Config.php` | Merchant, titkos kulcs, környezet — és ezek érvényessége |
| `src/Signature.php` | HMAC-SHA384 aláírás készítése és ellenőrzése byte-sorozat felett |
| `src/SaltGenerator.php` | 32 karakteres salt előállítása |
| `src/Currency.php` | Pénznem és a hozzá tartozó tizedes-kitevő |
| `src/Money.php` | Összeg pénznemmel, a kitevő szerinti formázással |
| `src/Language.php` | Fizetőoldal nyelve |
| `src/PaymentMethod.php` | Fizetési mód |
| `src/TransactionStatus.php` | Tranzakció-státusz és annak véglegessége/sikeressége |
| `src/ReturnEvent.php` | A visszatérési adat eseménytípusa |
| `src/Error/SimplePayError.php` | Egy hibakód és leírása |
| `src/Error/ErrorCatalog.php` | Hibakód → magyar leírás és fejlesztői-hiba besorolás |
| `src/Exception/*.php` | A kivétel-hierarchia |
| `src/Internal/PayloadReader.php` | Tipizált mezőolvasás nyers tömbből, hiánynál dobva |
| `src/Request/*.php` | Kimenő kérések és összetevőik, `toPayload()`-dal |
| `src/Response/*.php` | Bejövő válaszok, `fromPayload()`-dal |
| `src/Ipn/*.php` | IPN üzenet és a visszaigazoló válasz |
| `src/Client.php` | A transzport és az öt publikus művelet |

---

## Task 1: Csomagváz és az `Environment` enum

**Files:**
- Create: `composer.json`, `.gitignore`, `phpunit.xml.dist`, `phpstan.dist.neon`, `ecs.php`
- Create: `src/Environment.php`
- Test: `tests/Unit/EnvironmentTest.php`

**Interfaces:**
- Consumes: semmit
- Produces: `CodeConjure\SimplePay\Environment` enum, esetei `Environment::Sandbox` és `Environment::Production`, metódusa `baseUrl(): string`

- [ ] **Step 1: Hozd létre a `composer.json`-t**

```json
{
    "name": "codeconjure/simplepay",
    "description": "OTP SimplePay v2 protocol client — framework and Payum independent",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.0",
        "psr/http-message": "^2.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^12.0",
        "php-http/mock-client": "^1.6",
        "nyholm/psr7": "^1.8",
        "phpstan/phpstan": "^2.0",
        "sylius-labs/coding-standard": "^4.4"
    },
    "suggest": {
        "symfony/http-client": "PSR-18 client implementation",
        "nyholm/psr7": "Lightweight PSR-7 and PSR-17 implementation"
    },
    "autoload": {
        "psr-4": {
            "CodeConjure\\SimplePay\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "CodeConjure\\SimplePay\\Tests\\": "tests/"
        }
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "php-http/discovery": false
        }
    },
    "minimum-stability": "stable"
}
```

- [ ] **Step 2: Hozd létre a `.gitignore`-t**

```gitignore
/vendor/
/composer.lock
/.phpunit.cache/
/.phpunit.result.cache
```

- [ ] **Step 3: Hozd létre a `phpunit.xml.dist`-et**

A `sandbox` csoport alapértelmezésben ki van zárva — ez a Global Constraints része, és a CI is erre épül.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         cacheDirectory=".phpunit.cache"
         colors="true"
         failOnWarning="true"
         failOnRisky="true"
         failOnNotice="true">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="sandbox">
            <directory>tests/Sandbox</directory>
        </testsuite>
    </testsuites>

    <groups>
        <exclude>
            <group>sandbox</group>
        </exclude>
    </groups>

    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>

    <php>
        <env name="SIMPLEPAY_SANDBOX_MERCHANT" value="PUBLICTESTHUF"/>
        <env name="SIMPLEPAY_SANDBOX_SECRET" value="FxDa5w314kLlNseq2sKuVwaqZshZT5d6"/>
    </php>
</phpunit>
```

- [ ] **Step 4: Hozd létre a `phpstan.dist.neon`-t**

```neon
parameters:
    level: 9
    paths:
        - src
        - tests
```

- [ ] **Step 5: Hozd létre az `ecs.php`-t**

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $config): void {
    $config->import('vendor/sylius-labs/coding-standard/ecs.php');
    $config->paths([__DIR__ . '/src', __DIR__ . '/tests']);
};
```

- [ ] **Step 6: Telepítsd a függőségeket**

Run: `composer install`
Expected: sikeres telepítés, létrejön a `vendor/` és a `composer.lock`.

- [ ] **Step 7: Írd meg a bukó tesztet**

`tests/Unit/EnvironmentTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    public function testSandboxBaseUrl(): void
    {
        self::assertSame('https://sandbox.simplepay.hu/payment/v2/', Environment::Sandbox->baseUrl());
    }

    public function testProductionBaseUrl(): void
    {
        self::assertSame('https://secure.simplepay.hu/payment/v2/', Environment::Production->baseUrl());
    }

    public function testBaseUrlAlwaysEndsWithSlash(): void
    {
        foreach (Environment::cases() as $environment) {
            self::assertStringEndsWith('/', $environment->baseUrl());
        }
    }
}
```

- [ ] **Step 8: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/EnvironmentTest.php`
Expected: FAIL — `Class "CodeConjure\SimplePay\Environment" not found`.

- [ ] **Step 9: Írd meg az implementációt**

`src/Environment.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

enum Environment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox => 'https://sandbox.simplepay.hu/payment/v2/',
            self::Production => 'https://secure.simplepay.hu/payment/v2/',
        };
    }
}
```

- [ ] **Step 10: Futtasd újra**

Run: `vendor/bin/phpunit tests/Unit/EnvironmentTest.php`
Expected: PASS, 3 teszt.

- [ ] **Step 11: Ellenőrizd a statikus elemzést**

Run: `vendor/bin/phpstan analyse -c phpstan.dist.neon`
Expected: `[OK] No errors`

- [ ] **Step 12: Commit**

```bash
git add composer.json composer.lock .gitignore phpunit.xml.dist phpstan.dist.neon ecs.php src/Environment.php tests/Unit/EnvironmentTest.php
git commit -m "feat: csomagvaz es az Environment enum a helyes eles URL-lel"
```

---

## Task 2: Kivétel-hierarchia és hibakód-katalógus

**Files:**
- Create: `src/Exception/SimplePayException.php`, `ConfigurationException.php`, `TransportException.php`, `SignatureException.php`, `UnexpectedResponseException.php`, `RequestException.php`, `DeveloperException.php`
- Create: `src/Error/SimplePayError.php`, `src/Error/ErrorCatalog.php`
- Test: `tests/Unit/Error/ErrorCatalogTest.php`, `tests/Unit/Exception/RequestExceptionTest.php`

**Interfaces:**
- Consumes: semmit
- Produces:
  - `SimplePayException` (interface, `extends \Throwable`)
  - `ConfigurationException`, `TransportException`, `SignatureException`, `UnexpectedResponseException` — mind `extends \RuntimeException implements SimplePayException`
  - `RequestException extends \RuntimeException implements SimplePayException`, konstruktora `__construct(list<SimplePayError> $errors)`, metódusai `errors(): list<SimplePayError>`, `codes(): list<int>`
  - `DeveloperException extends RequestException`
  - `RequestException::fromCodes(list<int> $codes): RequestException` — gyár, ami eldönti, `RequestException` vagy `DeveloperException` legyen
  - `SimplePayError` readonly: `public int $code`, `public ?string $description`, `public bool $isDeveloperError`
  - `ErrorCatalog::describe(int $code): ?string`, `ErrorCatalog::isDeveloperError(int $code): bool`

- [ ] **Step 1: Írd meg a bukó teszteket**

`tests/Unit/Error/ErrorCatalogTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Error;

use CodeConjure\SimplePay\Error\ErrorCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorCatalog::class)]
final class ErrorCatalogTest extends TestCase
{
    public function testKnownCodeHasHungarianDescription(): void
    {
        self::assertSame('Nincs elég fedezet a kártyán.', ErrorCatalog::describe(2013));
    }

    public function testUnknownCodeHasNoDescription(): void
    {
        self::assertNull(ErrorCatalog::describe(987654));
    }

    public function testDeveloperErrorIsRecognised(): void
    {
        self::assertTrue(ErrorCatalog::isDeveloperError(2003));
    }

    public function testNonDeveloperErrorIsNotFlagged(): void
    {
        self::assertFalse(ErrorCatalog::isDeveloperError(2013));
    }

    public function testUnknownCodeIsNotADeveloperError(): void
    {
        self::assertFalse(ErrorCatalog::isDeveloperError(987654));
    }
}
```

`tests/Unit/Exception/RequestExceptionTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Exception;

use CodeConjure\SimplePay\Exception\DeveloperException;
use CodeConjure\SimplePay\Exception\RequestException;
use CodeConjure\SimplePay\Exception\SimplePayException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestException::class)]
#[CoversClass(DeveloperException::class)]
final class RequestExceptionTest extends TestCase
{
    public function testDeveloperCodeProducesDeveloperException(): void
    {
        self::assertInstanceOf(DeveloperException::class, RequestException::fromCodes([2003]));
    }

    public function testNonDeveloperCodeProducesPlainRequestException(): void
    {
        $exception = RequestException::fromCodes([2013]);

        self::assertInstanceOf(RequestException::class, $exception);
        self::assertNotInstanceOf(DeveloperException::class, $exception);
    }

    public function testAnySingleDeveloperCodeMakesTheWholeFailureDeveloperFacing(): void
    {
        self::assertInstanceOf(DeveloperException::class, RequestException::fromCodes([2013, 2003]));
    }

    public function testErrorsCarryCodeAndDescription(): void
    {
        $errors = RequestException::fromCodes([2013])->errors();

        self::assertCount(1, $errors);
        self::assertSame(2013, $errors[0]->code);
        self::assertSame('Nincs elég fedezet a kártyán.', $errors[0]->description);
    }

    public function testUnknownCodeSurvivesWithoutDescription(): void
    {
        $errors = RequestException::fromCodes([987654])->errors();

        self::assertSame(987654, $errors[0]->code);
        self::assertNull($errors[0]->description);
    }

    public function testMessageNamesEveryCode(): void
    {
        $message = RequestException::fromCodes([2013, 987654])->getMessage();

        self::assertStringContainsString('2013', $message);
        self::assertStringContainsString('987654', $message);
    }

    public function testEverySimplePayExceptionShareTheMarkerInterface(): void
    {
        self::assertInstanceOf(SimplePayException::class, RequestException::fromCodes([2013]));
    }
}
```

- [ ] **Step 2: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/Error tests/Unit/Exception`
Expected: FAIL — hiányzó osztályok.

- [ ] **Step 3: Írd meg a kivétel-hierarchiát**

`src/Exception/SimplePayException.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Exception;

interface SimplePayException extends \Throwable
{
}
```

`src/Exception/ConfigurationException.php` — és pontosan ugyanezzel a testtel, csak más osztálynévvel: `TransportException.php`, `SignatureException.php`, `UnexpectedResponseException.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Exception;

final class ConfigurationException extends \RuntimeException implements SimplePayException
{
}
```

- [ ] **Step 4: Írd meg a `SimplePayError`-t**

`src/Error/SimplePayError.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Error;

final readonly class SimplePayError
{
    public function __construct(
        public int $code,
        public ?string $description,
        public bool $isDeveloperError,
    ) {
    }

    public static function fromCode(int $code): self
    {
        return new self($code, ErrorCatalog::describe($code), ErrorCatalog::isDeveloperError($code));
    }

    public function __toString(): string
    {
        return null === $this->description
            ? sprintf('%d (ismeretlen hibakód)', $this->code)
            : sprintf('%d: %s', $this->code, $this->description);
    }
}
```

- [ ] **Step 5: Írd meg az `ErrorCatalog`-ot**

Az adattáblákat **másold át** az appból, ne gépeld újra:

- A leírások: `/server/www/egyhazzene.hu/bolt/src/Components/Payum/SimplePay/SimplePayApi.php` **22–317. sor** (`ERROR_DESCRIPTIONS` tömb, `kód => 'magyar leírás'` párok).
- A fejlesztői hibakódok: ugyanabban a fájlban a **322–340. sor** (`DEVELOPER_ERROR_CODES` lista).

`src/Error/ErrorCatalog.php` váza — a `…` helyére a fenti két tömb tartalma kerül változtatás nélkül:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Error;

final class ErrorCatalog
{
    /** @var array<int, string> */
    private const array DESCRIPTIONS = [
        999 => 'Általános hibakód.',
        // … az app 22–317. sorának teljes tartalma
        2013 => 'Nincs elég fedezet a kártyán.',
        // …
    ];

    /** @var list<int> */
    private const array DEVELOPER_CODES = [
        2003,
        // … az app 322–340. sorának teljes tartalma
    ];

    public static function describe(int $code): ?string
    {
        return self::DESCRIPTIONS[$code] ?? null;
    }

    public static function isDeveloperError(int $code): bool
    {
        return in_array($code, self::DEVELOPER_CODES, true);
    }
}
```

Ellenőrizd, hogy a `2013` leírása pontosan `'Nincs elég fedezet a kártyán.'` és hogy a `2003` szerepel a fejlesztői kódok között — a tesztek ezekre épülnek. Ha az appban más a szöveg, a tesztet igazítsd az apphoz, ne fordítva.

- [ ] **Step 6: Írd meg a `RequestException`-t és a `DeveloperException`-t**

`src/Exception/RequestException.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Exception;

use CodeConjure\SimplePay\Error\SimplePayError;

class RequestException extends \RuntimeException implements SimplePayException
{
    /** @param list<SimplePayError> $errors */
    final public function __construct(private readonly array $errors)
    {
        parent::__construct(sprintf(
            'A SimplePay elutasította a kérést — %s',
            implode('; ', array_map(strval(...), $errors)),
        ));
    }

    /** @param list<int> $codes */
    public static function fromCodes(array $codes): self
    {
        $errors = array_map(SimplePayError::fromCode(...), $codes);

        foreach ($errors as $error) {
            if ($error->isDeveloperError) {
                return new DeveloperException($errors);
            }
        }

        return new self($errors);
    }

    /** @return list<SimplePayError> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<int> */
    public function codes(): array
    {
        return array_map(static fn (SimplePayError $error): int => $error->code, $this->errors);
    }
}
```

`src/Exception/DeveloperException.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Exception;

final class DeveloperException extends RequestException
{
}
```

Megjegyzés: a `RequestException` szándékosan nem `final` — a `DeveloperException` örököl belőle. A konstruktor viszont `final`, hogy a `fromCodes()` gyár aláírása mindkét ágon azonos maradjon.

- [ ] **Step 7: Futtasd a teszteket**

Run: `vendor/bin/phpunit tests/Unit/Error tests/Unit/Exception`
Expected: PASS, 12 teszt.

- [ ] **Step 8: Statikus elemzés**

Run: `vendor/bin/phpstan analyse -c phpstan.dist.neon`
Expected: `[OK] No errors`

- [ ] **Step 9: Commit**

```bash
git add src/Exception src/Error tests/Unit/Exception tests/Unit/Error
git commit -m "feat: kivetel-hierarchia es magyar hibakod-katalogus"
```

---

## Task 3: `Signature` — a csomag legfontosabb egysége

**Files:**
- Create: `src/Signature.php`
- Test: `tests/Unit/SignatureTest.php`

**Interfaces:**
- Consumes: semmit
- Produces: `Signature` readonly, `__construct(string $secretKey)`, `sign(string $body): string`, `verify(string $body, string $signature): bool`

Ez az az egység, ahol a felmérés két hibája is lakott: a lib SHA-256-ot használt SHA-384 helyett, az app pedig tömböt írt alá byte-sorozat helyett. Az itt következő elvárt értékek **valódi, kiszámolt HMAC-SHA384 vektorok**, nem kitaláltak.

- [ ] **Step 1: Írd meg a bukó tesztet**

`tests/Unit/SignatureTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Signature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Signature::class)]
final class SignatureTest extends TestCase
{
    private const string SECRET = 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6';

    private const string BODY = '{"salt":"abcdefghijklmnopqrstuvwxyz012345","merchant":"PUBLICTESTHUF"}';

    private const string EXPECTED = '2jhhXDkc6/cJna/lMvut1qRt+a1t1AakfzqiovFTkuweGmMTsj3qSjYzfpcNcWU2';

    public function testSignProducesTheKnownVector(): void
    {
        self::assertSame(self::EXPECTED, new Signature(self::SECRET)->sign(self::BODY));
    }

    public function testSignatureIsBase64OfA48ByteDigest(): void
    {
        $raw = base64_decode(new Signature(self::SECRET)->sign(self::BODY), true);

        self::assertIsString($raw);
        self::assertSame(48, strlen($raw), 'A SHA-384 lenyomat 48 byte — ha 32, akkor SHA-256-ot számolunk.');
    }

    public function testVerifyAcceptsTheMatchingSignature(): void
    {
        self::assertTrue(new Signature(self::SECRET)->verify(self::BODY, self::EXPECTED));
    }

    public function testVerifyRejectsATamperedBody(): void
    {
        self::assertFalse(new Signature(self::SECRET)->verify(self::BODY . ' ', self::EXPECTED));
    }

    public function testVerifyRejectsAWrongSignature(): void
    {
        self::assertFalse(new Signature(self::SECRET)->verify(self::BODY, 'bm9wZQ=='));
    }

    public function testVerifyRejectsAnEmptySignature(): void
    {
        self::assertFalse(new Signature(self::SECRET)->verify(self::BODY, ''));
    }

    public function testVerifyToleratesSurroundingWhitespaceInTheHeader(): void
    {
        self::assertTrue(new Signature(self::SECRET)->verify(self::BODY, " \t" . self::EXPECTED . "\r\n"));
    }

    public function testADifferentKeyProducesADifferentSignature(): void
    {
        self::assertNotSame(self::EXPECTED, new Signature('masik-kulcs')->sign(self::BODY));
    }

    /**
     * A hivatalos SimplePay PHP SDK (2.1.5, 2026-06-27) a saját
     * getSignature()-jében `trim($key)`-t hív, mind aláíráskor, mind
     * ellenőrzéskor. Enélkül egy vezető/záró szóközzel bemásolt kulcs
     * csendben más aláírást adna, mint amit a SimplePay ténylegesen vár.
     */
    public function testALeadingOrTrailingWhitespaceInTheKeyIsTrimmedBeforeSigning(): void
    {
        self::assertSame(
            self::EXPECTED,
            new Signature(' 	' . self::SECRET . '
')->sign(self::BODY),
        );
    }
}
```

- [ ] **Step 2: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/SignatureTest.php`
Expected: FAIL — `Class "CodeConjure\SimplePay\Signature" not found`.

- [ ] **Step 3: Írd meg az implementációt**

`src/Signature.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

/**
 * HMAC-SHA384 aláírás a pontos byte-sorozat felett.
 *
 * A metódusok szándékosan `string`-et fogadnak és nem tömböt: a bejövő üzenetet
 * mindig a kapott byte-okon kell ellenőrizni, sosem egy újrakódolt változatán.
 *
 * A secretKey-t trim()-eljük, mielőtt aláírásra használnánk — a hivatalos
 * SimplePay PHP SDK (2.1.5, 2026-06-27, `src/SimplePayV21.php`) is
 * `trim($key)`-t hív a saját `getSignature()`-jében, mind aláíráskor, mind
 * ellenőrzéskor. Enélkül egy vezető/záró szóközzel bemásolt kulcs csendben
 * más aláírást adna, mint amit a SimplePay (és a saját SDK-juk) számolna —
 * minden hívás elutasításra kerülne egy olyan hibával, ami sehol nem
 * mutatna a valódi okra.
 */
final readonly class Signature
{
    private const string ALGORITHM = 'sha384';

    private string $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = trim($secretKey);
    }

    public function sign(string $body): string
    {
        return base64_encode(hash_hmac(self::ALGORITHM, $body, $this->secretKey, true));
    }

    public function verify(string $body, string $signature): bool
    {
        return hash_equals($this->sign($body), trim($signature));
    }
}
```

- [ ] **Step 4: Futtasd újra**

Run: `vendor/bin/phpunit tests/Unit/SignatureTest.php`
Expected: PASS, 8 teszt.

- [ ] **Step 5: Commit**

```bash
git add src/Signature.php tests/Unit/SignatureTest.php
git commit -m "feat: HMAC-SHA384 alairas byte-sorozat felett"
```

**Fix round (Task 13, 2026-08-30) — a secretKey mostantól trim()-elve.** A hivatalos SimplePay PHP SDK (2.1.5, 2026-06-27) `getSignature()`-je `trim($key)`-t hív, mielőtt aláírásra használná a kulcsot, mind aláíráskor, mind ellenőrzéskor. A `Signature` eredetileg nem trimmelt — egy vezető/záró szóközzel bemásolt kulcs csendben más aláírást adott volna, mint amit a SimplePay (és a saját SDK-juk) számol. Javítva: a konstruktor `trim()`-eli a `secretKey`-t; új teszt (`testALeadingOrTrailingWhitespaceInTheKeyIsTrimmedBeforeSigning`) pinneli le, hogy egy whitespace-szel bővített kulcs ugyanazt az aláírást adja, mint a trimmelt.

---

## Task 4: `Config` és `SaltGenerator`

**Files:**
- Create: `src/Config.php`, `src/SaltGenerator.php`
- Test: `tests/Unit/ConfigTest.php`, `tests/Unit/SaltGeneratorTest.php`

**Interfaces:**
- Consumes: `Environment` (Task 1), `ConfigurationException` (Task 2), `Signature` (Task 3)
- Produces:
  - `Config` readonly, `__construct(string $merchant, string $secretKey, Environment $environment)`, publikus property-k `$merchant`, `$secretKey`, `$environment`; metódusok `baseUrl(): string`, `signature(): Signature`
  - `SaltGenerator` readonly, `__construct(Randomizer $randomizer = new Randomizer())`, `generate(): string` — 32 hexadecimális karakter

- [ ] **Step 1: Írd meg a bukó teszteket**

`tests/Unit/ConfigTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Environment;
use CodeConjure\SimplePay\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    public function testItKeepsTheGivenValues(): void
    {
        $config = new Config('PUBLICTESTHUF', 'titok', Environment::Sandbox);

        self::assertSame('PUBLICTESTHUF', $config->merchant);
        self::assertSame('titok', $config->secretKey);
        self::assertSame(Environment::Sandbox, $config->environment);
    }

    public function testBaseUrlComesFromTheEnvironment(): void
    {
        self::assertSame(
            'https://secure.simplepay.hu/payment/v2/',
            new Config('M', 's', Environment::Production)->baseUrl(),
        );
    }

    public function testSignatureUsesTheSecretKey(): void
    {
        $config = new Config('PUBLICTESTHUF', 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6', Environment::Sandbox);

        self::assertSame(
            '2jhhXDkc6/cJna/lMvut1qRt+a1t1AakfzqiovFTkuweGmMTsj3qSjYzfpcNcWU2',
            $config->signature()->sign('{"salt":"abcdefghijklmnopqrstuvwxyz012345","merchant":"PUBLICTESTHUF"}'),
        );
    }

    public function testEmptyMerchantIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('merchant');

        new Config('   ', 'titok', Environment::Sandbox);
    }

    public function testEmptySecretKeyIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('secretKey');

        new Config('PUBLICTESTHUF', '', Environment::Sandbox);
    }
}
```

`tests/Unit/SaltGeneratorTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\SaltGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(SaltGenerator::class)]
final class SaltGeneratorTest extends TestCase
{
    public function testSaltIs32CharactersLong(): void
    {
        self::assertSame(32, strlen(new SaltGenerator()->generate()));
    }

    public function testSaltIsAlphanumeric(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', new SaltGenerator()->generate());
    }

    public function testTwoSaltsDiffer(): void
    {
        $generator = new SaltGenerator();

        self::assertNotSame($generator->generate(), $generator->generate());
    }

    public function testSaltIsReproducibleForASeededRandomizer(): void
    {
        $first = new SaltGenerator(new Randomizer(new Xoshiro256StarStar(1234)))->generate();
        $second = new SaltGenerator(new Randomizer(new Xoshiro256StarStar(1234)))->generate();

        self::assertSame($first, $second);
    }
}
```

- [ ] **Step 2: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/ConfigTest.php tests/Unit/SaltGeneratorTest.php`
Expected: FAIL — hiányzó osztályok.

- [ ] **Step 3: Írd meg a `Config`-ot**

`src/Config.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\ConfigurationException;

final readonly class Config
{
    public function __construct(
        public string $merchant,
        public string $secretKey,
        public Environment $environment,
    ) {
        if ('' === trim($merchant)) {
            throw new ConfigurationException('A SimplePay kliens nem indítható üres merchant azonosítóval.');
        }

        if ('' === trim($secretKey)) {
            throw new ConfigurationException('A SimplePay kliens nem indítható üres secretKey értékkel.');
        }
    }

    public function baseUrl(): string
    {
        return $this->environment->baseUrl();
    }

    public function signature(): Signature
    {
        return new Signature($this->secretKey);
    }
}
```

- [ ] **Step 4: Írd meg a `SaltGenerator`-t**

`src/SaltGenerator.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use Random\Randomizer;

/**
 * A SimplePay minden kérésben saltot vár, és a 32–64 karakteres tartományon
 * kívüli értéket 5401-es hibakóddal utasítja el.
 */
final readonly class SaltGenerator
{
    public function __construct(private Randomizer $randomizer = new Randomizer())
    {
    }

    public function generate(): string
    {
        return bin2hex($this->randomizer->getBytes(16));
    }
}
```

- [ ] **Step 5: Futtasd újra**

Run: `vendor/bin/phpunit tests/Unit/ConfigTest.php tests/Unit/SaltGeneratorTest.php`
Expected: PASS, 9 teszt.

- [ ] **Step 6: Statikus elemzés**

Run: `vendor/bin/phpstan analyse -c phpstan.dist.neon`
Expected: `[OK] No errors`

- [ ] **Step 7: Commit**

```bash
git add src/Config.php src/SaltGenerator.php tests/Unit/ConfigTest.php tests/Unit/SaltGeneratorTest.php
git commit -m "feat: Config es SaltGenerator"
```

---

## Task 5: `Currency` és `Money`

**Files:**
- Create: `src/Currency.php`, `src/Money.php`
- Test: `tests/Unit/MoneyTest.php`

**Interfaces:**
- Consumes: `UnexpectedResponseException`, `ConfigurationException` (Task 2)
- Produces:
  - `Currency` enum: `HUF`, `EUR`, `USD`; `exponent(): int`; `fromApi(string $value): self`
  - `Money` readonly: `fromMinorUnits(int $amount, Currency $currency): self`, `fromDecimalString(string $amount, Currency $currency): self`, `fromApiValue(string|int|float $value, Currency $currency): self`, `toApiValue(): string`, publikus `$minorUnits`, `$currency`

A `fromMinorUnits()` a **pénznem valódi kitevője** szerinti alegységet várja: HUF esetén egész forintot, EUR és USD esetén centet. A Sylius pénznemtől független kétszázados ábrázolásából az átváltás a Sylius plugin dolga, nem ezé a rétegé.

- [ ] **Step 1: Írd meg a bukó tesztet**

`tests/Unit/MoneyTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Money::class)]
#[CoversClass(Currency::class)]
final class MoneyTest extends TestCase
{
    public function testHufHasNoDecimals(): void
    {
        self::assertSame(0, Currency::HUF->exponent());
        self::assertSame('1000', Money::fromMinorUnits(1000, Currency::HUF)->toApiValue());
    }

    public function testEuroHasTwoDecimals(): void
    {
        self::assertSame(2, Currency::EUR->exponent());
        self::assertSame('10.50', Money::fromMinorUnits(1050, Currency::EUR)->toApiValue());
    }

    public function testEuroPadsASingleDecimal(): void
    {
        self::assertSame('10.05', Money::fromMinorUnits(1005, Currency::EUR)->toApiValue());
    }

    public function testEuroKeepsWholeAmountsFormatted(): void
    {
        self::assertSame('10.00', Money::fromMinorUnits(1000, Currency::EUR)->toApiValue());
    }

    public function testZeroIsFormattedForBothExponents(): void
    {
        self::assertSame('0', Money::fromMinorUnits(0, Currency::HUF)->toApiValue());
        self::assertSame('0.00', Money::fromMinorUnits(0, Currency::EUR)->toApiValue());
    }

    public function testDecimalStringRoundtripsForEuro(): void
    {
        $money = Money::fromDecimalString('10.50', Currency::EUR);

        self::assertSame(1050, $money->minorUnits);
        self::assertSame('10.50', $money->toApiValue());
    }

    public function testDecimalStringWithoutFractionWorksForHuf(): void
    {
        self::assertSame(1000, Money::fromDecimalString('1000', Currency::HUF)->minorUnits);
    }

    public function testHufRejectsAFractionalAmount(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        Money::fromDecimalString('1000.50', Currency::HUF);
    }

    public function testApiValueAcceptsAnInteger(): void
    {
        self::assertSame(1000, Money::fromApiValue(1000, Currency::HUF)->minorUnits);
    }

    public function testApiValueAcceptsADecimalString(): void
    {
        self::assertSame(1050, Money::fromApiValue('10.50', Currency::EUR)->minorUnits);
    }

    public function testApiValueRejectsGarbage(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        Money::fromApiValue('sok pénz', Currency::HUF);
    }

    public function testCurrencyFromApiRejectsAnUnknownCode(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('GBP');

        Currency::fromApi('GBP');
    }
}
```

- [ ] **Step 2: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/MoneyTest.php`
Expected: FAIL — hiányzó osztályok.

- [ ] **Step 3: Írd meg a `Currency`-t**

`src/Currency.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

enum Currency: string
{
    case HUF = 'HUF';
    case EUR = 'EUR';
    case USD = 'USD';

    public function exponent(): int
    {
        return match ($this) {
            self::HUF => 0,
            self::EUR, self::USD => 2,
        };
    }

    public static function fromApi(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new UnexpectedResponseException(sprintf(
                'A SimplePay ismeretlen pénznemet küldött: "%s". A támogatottak: %s.',
                $value,
                implode(', ', array_column(self::cases(), 'value')),
            ));
    }
}
```

- [ ] **Step 4: Írd meg a `Money`-t**

`src/Money.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

final readonly class Money
{
    private function __construct(
        public int $minorUnits,
        public Currency $currency,
    ) {
    }

    public static function fromMinorUnits(int $amount, Currency $currency): self
    {
        return new self($amount, $currency);
    }

    public static function fromDecimalString(string $amount, Currency $currency): self
    {
        $trimmed = trim($amount);

        if (1 !== preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $trimmed, $matches)) {
            throw new UnexpectedResponseException(sprintf('Nem értelmezhető összeg: "%s".', $amount));
        }

        $exponent = $currency->exponent();
        $fraction = $matches[3] ?? '';

        if (strlen($fraction) > $exponent) {
            throw new UnexpectedResponseException(sprintf(
                'A(z) %s legfeljebb %d tizedesjegyet enged, kapott: "%s".',
                $currency->value,
                $exponent,
                $amount,
            ));
        }

        $minorUnits = (int) ($matches[2] . str_pad($fraction, $exponent, '0'));

        return new self('-' === $matches[1] ? -$minorUnits : $minorUnits, $currency);
    }

    public static function fromApiValue(string|int|float $value, Currency $currency): self
    {
        if (is_int($value)) {
            return self::fromDecimalString((string) $value, $currency);
        }

        if (is_float($value)) {
            $formatted = number_format($value, $currency->exponent(), '.', '');
            if ((float) $formatted !== $value) {
                throw new UnexpectedResponseException(sprintf(
                    'A(z) %s legfeljebb %d tizedesjegyet enged, kapott: "%s".',
                    $currency->value,
                    $currency->exponent(),
                    $value,
                ));
            }

            return self::fromDecimalString($formatted, $currency);
        }

        return self::fromDecimalString($value, $currency);
    }

    public function toApiValue(): string
    {
        $exponent = $this->currency->exponent();

        if (0 === $exponent) {
            return (string) $this->minorUnits;
        }

        $sign = $this->minorUnits < 0 ? '-' : '';
        $absolute = abs($this->minorUnits);
        $divisor = 10 ** $exponent;

        return sprintf(
            '%s%d.%s',
            $sign,
            intdiv($absolute, $divisor),
            str_pad((string) ($absolute % $divisor), $exponent, '0', STR_PAD_LEFT),
        );
    }
}
```

- [ ] **Step 5: Futtasd újra**

Run: `vendor/bin/phpunit tests/Unit/MoneyTest.php`
Expected: PASS, 12 teszt.

- [ ] **Step 6: Statikus elemzés**

Run: `vendor/bin/phpstan analyse -c phpstan.dist.neon`
Expected: `[OK] No errors`

- [ ] **Step 7: Commit**

```bash
git add src/Currency.php src/Money.php tests/Unit/MoneyTest.php
git commit -m "feat: Currency es Money a penznem kitevoje szerinti formazassal"
```

---

## Task 6: A maradék enumok — `TransactionStatus`, `PaymentMethod`, `Language`, `ReturnEvent`

**Files:**
- Create: `src/TransactionStatus.php`, `src/PaymentMethod.php`, `src/Language.php`, `src/ReturnEvent.php`
- Test: `tests/Unit/TransactionStatusTest.php`, `tests/Unit/EnumParsingTest.php`

**Interfaces:**
- Consumes: `UnexpectedResponseException` (Task 2)
- Produces:
  - `TransactionStatus` enum: `Init`, `InPayment`, `Authorized`, `Finished`, `Cancelled`, `Timeout`, `NotAuthorized`, `Fraud`, `InFraud`, `Reversed`, `Refund`; metódusok `isFinal(): bool`, `isSuccessful(): bool`, `fromApi(string $value): self`
  - `PaymentMethod` enum: `Card` = `'CARD'`, `Wire` = `'WIRE'`; `fromApi(string $value): self`
  - `Language` enum: `Hu` = `'HU'`, `En` = `'EN'`, `De` = `'DE'`
  - `ReturnEvent` enum: `Success` = `'SUCCESS'`, `Fail` = `'FAIL'`, `Cancel` = `'CANCEL'`, `Timeout` = `'TIMEOUT'`; `fromApi(string $value): self`

**Szűkítés a spechez képest, tudatosan:** a `Language` csak `HU`, `EN`, `DE` értékeket vesz fel. A SimplePay ennél több nyelvet is támogat, de a boltnak ez a három kell, és nem akarunk ellenőrizetlen listát felvenni. A README feature mátrixába (Task 14) ezért kerül egy sor: „Fizetőoldal nyelve: HU, EN, DE ✅ / további nyelvek ⬜".

- [ ] **Step 1: Írd meg a bukó teszteket**

`tests/Unit/TransactionStatusTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\TransactionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionStatus::class)]
final class TransactionStatusTest extends TestCase
{
    public function testFinishedIsTheOnlySuccessfulStatus(): void
    {
        foreach (TransactionStatus::cases() as $status) {
            self::assertSame(
                TransactionStatus::Finished === $status,
                $status->isSuccessful(),
                sprintf('%s sikeressége', $status->value),
            );
        }
    }

    /** @return iterable<string, array{TransactionStatus, bool}> */
    public static function finality(): iterable
    {
        yield 'INIT' => [TransactionStatus::Init, false];
        yield 'INPAYMENT' => [TransactionStatus::InPayment, false];
        yield 'AUTHORIZED' => [TransactionStatus::Authorized, false];
        yield 'INFRAUD' => [TransactionStatus::InFraud, false];
        yield 'FINISHED' => [TransactionStatus::Finished, true];
        yield 'CANCELLED' => [TransactionStatus::Cancelled, true];
        yield 'TIMEOUT' => [TransactionStatus::Timeout, true];
        yield 'NOTAUTHORIZED' => [TransactionStatus::NotAuthorized, true];
        yield 'FRAUD' => [TransactionStatus::Fraud, true];
        yield 'REVERSED' => [TransactionStatus::Reversed, true];
        yield 'REFUND' => [TransactionStatus::Refund, true];
    }

    #[DataProvider('finality')]
    public function testFinality(TransactionStatus $status, bool $expected): void
    {
        self::assertSame($expected, $status->isFinal());
    }

    public function testFinishedParsesFromTheApiValue(): void
    {
        self::assertSame(TransactionStatus::Finished, TransactionStatus::fromApi('FINISHED'));
    }

    public function testAnUnknownStatusIsLoudNotSilent(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('COMPLETE');

        TransactionStatus::fromApi('COMPLETE');
    }

    public function testTheExceptionListsTheKnownStatuses(): void
    {
        try {
            TransactionStatus::fromApi('WAITING');
            self::fail('Kivételt vártunk.');
        } catch (UnexpectedResponseException $exception) {
            self::assertStringContainsString('FINISHED', $exception->getMessage());
        }
    }
}
```

`tests/Unit/EnumParsingTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Language;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\ReturnEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentMethod::class)]
#[CoversClass(Language::class)]
#[CoversClass(ReturnEvent::class)]
final class EnumParsingTest extends TestCase
{
    public function testPaymentMethodParses(): void
    {
        self::assertSame(PaymentMethod::Card, PaymentMethod::fromApi('CARD'));
        self::assertSame(PaymentMethod::Wire, PaymentMethod::fromApi('WIRE'));
    }

    public function testUnknownPaymentMethodThrows(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        PaymentMethod::fromApi('BITCOIN');
    }

    public function testReturnEventParses(): void
    {
        self::assertSame(ReturnEvent::Success, ReturnEvent::fromApi('SUCCESS'));
        self::assertSame(ReturnEvent::Timeout, ReturnEvent::fromApi('TIMEOUT'));
    }

    public function testUnknownReturnEventThrows(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        ReturnEvent::fromApi('MAYBE');
    }

    public function testLanguagesAreUppercaseTwoLetterCodes(): void
    {
        foreach (Language::cases() as $language) {
            self::assertMatchesRegularExpression('/^[A-Z]{2}$/', $language->value);
        }
    }

    public function testHungarianIsAvailable(): void
    {
        self::assertSame('HU', Language::Hu->value);
    }
}
```

- [ ] **Step 2: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/TransactionStatusTest.php tests/Unit/EnumParsingTest.php`
Expected: FAIL — hiányzó osztályok.

- [ ] **Step 3: Írd meg a `TransactionStatus`-t**

`src/TransactionStatus.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

enum TransactionStatus: string
{
    case Init = 'INIT';
    case InPayment = 'INPAYMENT';
    case Authorized = 'AUTHORIZED';
    case InFraud = 'INFRAUD';
    case Finished = 'FINISHED';
    case Cancelled = 'CANCELLED';
    case Timeout = 'TIMEOUT';
    case NotAuthorized = 'NOTAUTHORIZED';
    case Fraud = 'FRAUD';
    case Reversed = 'REVERSED';
    case Refund = 'REFUND';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Init, self::InPayment, self::Authorized, self::InFraud => false,
            self::Finished, self::Cancelled, self::Timeout, self::NotAuthorized, self::Fraud, self::Reversed, self::Refund => true,
        };
    }

    public function isSuccessful(): bool
    {
        return self::Finished === $this;
    }

    public static function fromApi(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new UnexpectedResponseException(sprintf(
                'A SimplePay ismeretlen tranzakció-státuszt küldött: "%s". Az ismertek: %s.',
                $value,
                implode(', ', array_column(self::cases(), 'value')),
            ));
    }
}
```

- [ ] **Step 4: Írd meg a maradék három enumot**

`src/PaymentMethod.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

enum PaymentMethod: string
{
    case Card = 'CARD';
    case Wire = 'WIRE';

    public static function fromApi(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new UnexpectedResponseException(sprintf(
                'A SimplePay ismeretlen fizetési módot küldött: "%s".',
                $value,
            ));
    }
}
```

`src/ReturnEvent.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

enum ReturnEvent: string
{
    case Success = 'SUCCESS';
    case Fail = 'FAIL';
    case Cancel = 'CANCEL';
    case Timeout = 'TIMEOUT';

    public static function fromApi(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new UnexpectedResponseException(sprintf(
                'A SimplePay ismeretlen visszatérési eseményt küldött: "%s".',
                $value,
            ));
    }
}
```

`src/Language.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

enum Language: string
{
    case Hu = 'HU';
    case En = 'EN';
    case De = 'DE';
}
```

- [ ] **Step 5: Futtasd újra**

Run: `vendor/bin/phpunit tests/Unit/TransactionStatusTest.php tests/Unit/EnumParsingTest.php`
Expected: PASS, 20 teszt.

- [ ] **Step 6: Statikus elemzés**

Run: `vendor/bin/phpstan analyse -c phpstan.dist.neon`
Expected: `[OK] No errors`

- [ ] **Step 7: Commit**

```bash
git add src/TransactionStatus.php src/PaymentMethod.php src/Language.php src/ReturnEvent.php tests/Unit/TransactionStatusTest.php tests/Unit/EnumParsingTest.php
git commit -m "feat: statusz- es egyeb enumok, ismeretlen ertek eseten hangos hibaval"
```

---

## Task 7: Kimenő kérés-DTO-k

**Files:**
- Create: `src/Request/Invoice.php`, `src/Request/Urls.php`, `src/Request/StartRequest.php`, `src/Request/QueryRequest.php`, `src/Request/RefundRequest.php`
- Test: `tests/Unit/Request/StartRequestTest.php`, `tests/Unit/Request/QueryRequestTest.php`, `tests/Unit/Request/RefundRequestTest.php`

**Interfaces:**
- Consumes: `Money`, `Currency` (Task 5), `Language`, `PaymentMethod` (Task 6), `ConfigurationException` (Task 2)
- Produces: mindegyik osztálynak `toPayload(): array<string, mixed>` metódusa van, ami a SimplePay mezőneveit adja vissza. A `merchant`, `salt` és `sdkVersion` mezőket **nem** tartalmazza — azokat a `Client` teszi hozzá (Task 9).

- [ ] **Step 1: Írd meg a bukó teszteket**

`tests/Unit/Request/StartRequestTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Request;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\ConfigurationException;
use CodeConjure\SimplePay\Language;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\Request\Invoice;
use CodeConjure\SimplePay\Request\StartRequest;
use CodeConjure\SimplePay\Request\Urls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StartRequest::class)]
#[CoversClass(Invoice::class)]
#[CoversClass(Urls::class)]
final class StartRequestTest extends TestCase
{
    private static function urls(): Urls
    {
        return new Urls(
            success: 'https://bolt.hu/vissza?e=success',
            fail: 'https://bolt.hu/vissza?e=fail',
            cancel: 'https://bolt.hu/vissza?e=cancel',
            timeout: 'https://bolt.hu/vissza?e=timeout',
        );
    }

    private static function invoice(): Invoice
    {
        return new Invoice(
            name: 'Teszt Elek',
            country: 'HU',
            city: 'Budapest',
            zip: '1011',
            address: 'Fő utca 1.',
        );
    }

    private static function request(): StartRequest
    {
        return new StartRequest(
            orderRef: 'ORDER-1',
            total: Money::fromMinorUnits(1000, Currency::HUF),
            customerEmail: 'teszt@example.com',
            invoice: self::invoice(),
            urls: self::urls(),
        );
    }

    public function testPayloadUsesCamelCaseSimplePayFieldNames(): void
    {
        $payload = self::request()->toPayload();

        self::assertSame('ORDER-1', $payload['orderRef']);
        self::assertSame('teszt@example.com', $payload['customerEmail']);
        self::assertSame('HUF', $payload['currency']);
        self::assertSame('1000', $payload['total']);
        self::assertSame('HU', $payload['language']);
        self::assertSame(['CARD'], $payload['methods']);
    }

    public function testPayloadCarriesNoSnakeCaseKeys(): void
    {
        $payload = self::request()->toPayload();
        $topLevelKeyCount = count($payload);

        $totalKeyCount = self::assertNoSnakeCaseKeysRecursively($payload);

        // A historikus hiba a beágyazott map-ekben (invoice, urls) élt, nem a
        // felső szinten — ha a rekurzió nem néz bele azokba, ez a teszt
        // üresen futna át egy snake_case kulcson is. Ezért bizonyítjuk, hogy
        // ténylegesen több kulcsot vizsgáltunk meg, mint amennyi a felső
        // szinten van.
        self::assertGreaterThan(
            $topLevelKeyCount,
            $totalKeyCount,
            'A rekurzív ellenőrzésnek be kell néznie a beágyazott map-ekbe is, nem csak a felső szintre.',
        );
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return int a megvizsgált kulcsok száma, beleértve a beágyazottakat is
     */
    private static function assertNoSnakeCaseKeysRecursively(array $payload): int
    {
        $count = 0;

        foreach ($payload as $key => $value) {
            self::assertStringNotContainsString('_', (string) $key);
            ++$count;

            if (is_array($value)) {
                $count += self::assertNoSnakeCaseKeysRecursively($value);
            }
        }

        return $count;
    }

    public function testPayloadDoesNotCarryClientManagedFields(): void
    {
        $payload = self::request()->toPayload();

        self::assertArrayNotHasKey('merchant', $payload);
        self::assertArrayNotHasKey('salt', $payload);
        self::assertArrayNotHasKey('sdkVersion', $payload);
    }

    /**
     * A hivatalos SimplePay dokumentáció szerint a `start` kérés vagy egy
     * string `url` mezőt fogad el (egyetlen közös visszairányítási cím),
     * vagy egy objektum `urls` mezőt a differenciált success/fail/cancel/
     * timeout címekkel — és ha mindkettő jelen van, az `url` figyelmen
     * kívül marad. Ez a csomag szándékosan mindig a differenciált formát
     * küldi, és kizárólag azt: sosem emittál `url` kulcsot `urls` mellett,
     * hogy ne maradjon kétséges, melyik payload-mező érvényesülne. A
     * korábbi hiba (Task 13, sandbox kontraktus-teszttel felfedve) az volt,
     * hogy az objektumot tévedésből az `url` (stringet váró) kulcs alá
     * csomagoltuk — erre a sandbox 5321-es hibakóddal ("Formátumhiba /
     * érvénytelen JSON string") válaszolt, mert `url` valódi mező, csak
     * stringet vár, nem objektumot. Nincs per-request IPN mező sem (nincs
     * `dn` kulcs, sem itt, sem az `url` alatt): a hivatalos dokumentáció
     * szerint az IPN cím kizárólag a kereskedői admin felületen állítható
     * be, ezért a `Urls` osztály sem hordoz ilyen adatot.
     */
    public function testUrlsAreSentAsAMapUnderTheUrlsKey(): void
    {
        $payload = self::request()->toPayload();

        self::assertArrayNotHasKey(
            'url',
            $payload,
            'A csomag kizárólag "urls"-t küldi, "url"-t soha — a dokumentáció szerint mindkettő '
            . 'együttes jelenlétekor az "url" figyelmen kívül maradna, ami kétértelművé tenné a payloadot.',
        );

        $urls = $payload['urls'];

        self::assertIsArray($urls);
        self::assertSame(
            ['success', 'fail', 'cancel', 'timeout'],
            array_keys($urls),
            'Nincs per-request IPN mező — az IPN címet a kereskedői admin felület adja.',
        );
        self::assertSame('https://bolt.hu/vissza?e=success', $urls['success']);
        self::assertSame('https://bolt.hu/vissza?e=fail', $urls['fail']);
        self::assertSame('https://bolt.hu/vissza?e=cancel', $urls['cancel']);
        self::assertSame('https://bolt.hu/vissza?e=timeout', $urls['timeout']);
    }

    public function testInvoiceIsNested(): void
    {
        $invoice = self::request()->toPayload()['invoice'];

        self::assertIsArray($invoice);
        self::assertSame('Teszt Elek', $invoice['name']);
        self::assertSame('HU', $invoice['country']);
        self::assertSame('1011', $invoice['zip']);
    }

    public function testOptionalInvoiceFieldsAreOmittedWhenNull(): void
    {
        $invoice = self::request()->toPayload()['invoice'];

        self::assertIsArray($invoice);
        self::assertArrayNotHasKey('address2', $invoice);
        self::assertArrayNotHasKey('phone', $invoice);
        self::assertArrayNotHasKey('state', $invoice);
    }

    public function testTimeoutIsOmittedWhenNotGiven(): void
    {
        self::assertArrayNotHasKey('timeout', self::request()->toPayload());
    }

    public function testTimeoutIsSerialisedAsIso8601(): void
    {
        $request = new StartRequest(
            orderRef: 'ORDER-1',
            total: Money::fromMinorUnits(1000, Currency::HUF),
            customerEmail: 'teszt@example.com',
            invoice: self::invoice(),
            urls: self::urls(),
            timeout: new \DateTimeImmutable('2026-08-30T12:00:00+02:00'),
        );

        self::assertSame('2026-08-30T12:00:00+02:00', $request->toPayload()['timeout']);
    }

    public function testWireMethodIsSerialised(): void
    {
        $request = new StartRequest(
            orderRef: 'ORDER-1',
            total: Money::fromMinorUnits(1000, Currency::HUF),
            customerEmail: 'teszt@example.com',
            invoice: self::invoice(),
            urls: self::urls(),
            language: Language::En,
            methods: [PaymentMethod::Card, PaymentMethod::Wire],
        );

        self::assertSame(['CARD', 'WIRE'], $request->toPayload()['methods']);
        self::assertSame('EN', $request->toPayload()['language']);
    }

    public function testInvoiceRejectsABlankRequiredField(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/zip/');

        new Invoice(
            name: 'Teszt Elek',
            country: 'HU',
            city: 'Budapest',
            zip: '',
            address: 'Fő utca 1.',
        );
    }

    public function testInvoiceKeepsAZeroPostcode(): void
    {
        $invoice = new Invoice(
            name: 'Teszt Elek',
            country: 'HU',
            city: 'Budapest',
            zip: '0',
            address: 'Fő utca 1.',
        );

        self::assertSame('0', $invoice->toPayload()['zip']);
    }
}
```

`tests/Unit/Request/QueryRequestTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Request;

use CodeConjure\SimplePay\Exception\ConfigurationException;
use CodeConjure\SimplePay\Request\QueryRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryRequest::class)]
final class QueryRequestTest extends TestCase
{
    public function testTransactionIdsAreSentAsAList(): void
    {
        $payload = new QueryRequest(transactionIds: ['99999999'])->toPayload();

        self::assertSame(['99999999'], $payload['transactionIds']);
        self::assertArrayNotHasKey('orderRefs', $payload);
    }

    public function testOrderRefsAreSentAsAList(): void
    {
        $payload = new QueryRequest(orderRefs: ['ORDER-1', 'ORDER-2'])->toPayload();

        self::assertSame(['ORDER-1', 'ORDER-2'], $payload['orderRefs']);
        self::assertArrayNotHasKey('transactionIds', $payload);
    }

    /**
     * A `refunds` kapcsoló szándékosan hiányzik a `QueryRequest`-ből (lásd
     * az osztály docblockját): a hozott extra mezőket a válasz-oldal nem
     * olvassa ki, tehát a kapcsoló bekapcsolása néma ígéret lenne. Ez a
     * teszt lepinneli, hogy a payload sosem tartalmazhat ilyen kulcsot.
     */
    public function testPayloadNeverCarriesTheRefundsFlag(): void
    {
        $payload = new QueryRequest(orderRefs: ['ORDER-1'])->toPayload();

        self::assertArrayNotHasKey('refunds', $payload);
    }

    /**
     * A `detailed: true`-t a `toPayload()` mindig kiküldi, nem publikus
     * opcióként, hanem mert enélkül a SimplePay a `total`/`remainingTotal`
     * mezőket `currency` nélkül küldi vissza (élő sandboxon megfigyelve,
     * Task 13) — a `Transaction::fromPayload()` pedig jogosan hangos hibát
     * dob egy pénznem nélküli összegre. Ez a teszt lepinneli, hogy ez a
     * belső részlet nem vész el egy jövőbeli refaktornál.
     */
    public function testDetailedIsAlwaysSentToGuaranteeCurrency(): void
    {
        $payload = new QueryRequest(orderRefs: ['ORDER-1'])->toPayload();

        self::assertTrue($payload['detailed']);
    }

    public function testAnEmptyQueryIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        new QueryRequest();
    }

    public function testAListOfOnlyBlankTransactionIdsIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        new QueryRequest(transactionIds: ['']);
    }

    public function testBlankEntriesAreDroppedButUsableOnesSurvive(): void
    {
        $payload = new QueryRequest(orderRefs: ['', 'ORDER-1'])->toPayload();

        self::assertSame(['ORDER-1'], $payload['orderRefs']);
    }
}
```

`tests/Unit/Request/RefundRequestTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Request;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\ConfigurationException;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\Request\RefundRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundRequest::class)]
final class RefundRequestTest extends TestCase
{
    public function testPayloadCarriesRefundTotalAndCurrency(): void
    {
        $payload = new RefundRequest(
            refundTotal: Money::fromMinorUnits(1000, Currency::HUF),
            orderRef: 'ORDER-1',
        )->toPayload();

        self::assertSame('1000', $payload['refundTotal']);
        self::assertSame('HUF', $payload['currency']);
        self::assertSame('ORDER-1', $payload['orderRef']);
        self::assertArrayNotHasKey('transactionId', $payload);
    }

    public function testTransactionIdAloneIsEnough(): void
    {
        $payload = new RefundRequest(
            refundTotal: Money::fromMinorUnits(500, Currency::HUF),
            transactionId: '99999999',
        )->toPayload();

        self::assertSame('99999999', $payload['transactionId']);
        self::assertArrayNotHasKey('orderRef', $payload);
    }

    public function testAtLeastOneIdentifierIsRequired(): void
    {
        $this->expectException(ConfigurationException::class);

        new RefundRequest(refundTotal: Money::fromMinorUnits(500, Currency::HUF));
    }
}
```

- [ ] **Step 2: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/Request`
Expected: FAIL — hiányzó osztályok.

- [ ] **Step 3: Írd meg az `Invoice`-t és az `Urls`-t**

`src/Request/Invoice.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

use CodeConjure\SimplePay\Exception\ConfigurationException;

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
    ) {
        foreach ([
            'name' => $name,
            'country' => $country,
            'city' => $city,
            'zip' => $zip,
            'address' => $address,
        ] as $field => $value) {
            if ('' === $value) {
                throw new ConfigurationException(sprintf(
                    'A számlázási cím "%s" mezője nem lehet üres.',
                    $field,
                ));
            }
        }
    }

    /** @return array<string, string> */
    public function toPayload(): array
    {
        return array_filter([
            'name' => $this->name,
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'zip' => $this->zip,
            'address' => $this->address,
            'address2' => $this->address2,
            'phone' => $this->phone,
        ], static fn (?string $value): bool => null !== $value && '' !== $value);
    }
}
```

`src/Request/Urls.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

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
```

- [ ] **Step 4: Írd meg a `StartRequest`-et**

`src/Request/StartRequest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

use CodeConjure\SimplePay\Language;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\PaymentMethod;

final readonly class StartRequest
{
    /** @param non-empty-list<PaymentMethod> $methods */
    public function __construct(
        public string $orderRef,
        public Money $total,
        public string $customerEmail,
        public Invoice $invoice,
        public Urls $urls,
        public Language $language = Language::Hu,
        public array $methods = [PaymentMethod::Card],
        public ?\DateTimeImmutable $timeout = null,
        public ?string $customer = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        $payload = [
            'orderRef' => $this->orderRef,
            'total' => $this->total->toApiValue(),
            'currency' => $this->total->currency->value,
            'customerEmail' => $this->customerEmail,
            'language' => $this->language->value,
            'methods' => array_map(
                static fn (PaymentMethod $method): string => $method->value,
                $this->methods,
            ),
            'invoice' => $this->invoice->toPayload(),
            'urls' => $this->urls->toPayload(),
        ];

        if (null !== $this->timeout) {
            $payload['timeout'] = $this->timeout->format(\DateTimeInterface::ATOM);
        }

        if (null !== $this->customer && '' !== $this->customer) {
            $payload['customer'] = $this->customer;
        }

        return $payload;
    }
}
```

- [ ] **Step 5: Írd meg a `QueryRequest`-et és a `RefundRequest`-et**

`src/Request/QueryRequest.php`:

```php
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
```

`src/Request/RefundRequest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

use CodeConjure\SimplePay\Exception\ConfigurationException;
use CodeConjure\SimplePay\Money;

final readonly class RefundRequest
{
    public function __construct(
        public Money $refundTotal,
        public ?string $orderRef = null,
        public ?string $transactionId = null,
    ) {
        if (!self::isPresent($orderRef) && !self::isPresent($transactionId)) {
            throw new ConfigurationException(
                'A jóváíráshoz orderRef vagy transactionId kell.',
            );
        }
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        $payload = [
            'refundTotal' => $this->refundTotal->toApiValue(),
            'currency' => $this->refundTotal->currency->value,
        ];

        if (self::isPresent($this->orderRef)) {
            $payload['orderRef'] = $this->orderRef;
        }

        if (self::isPresent($this->transactionId)) {
            $payload['transactionId'] = $this->transactionId;
        }

        return $payload;
    }

    /**
     * Egy `null` vagy üres string nem azonosít semmit — a hívó gyakran
     * hiányzó adatot `''`-re redukál, ezt itt kell elkapni, nem az API-nál.
     */
    private static function isPresent(?string $value): bool
    {
        return null !== $value && '' !== $value;
    }
}
```

- [ ] **Step 6: Futtasd újra**

Run: `vendor/bin/phpunit tests/Unit/Request`
Expected: PASS, 17 teszt.

- [ ] **Step 7: Statikus elemzés**

Run: `vendor/bin/phpstan analyse -c phpstan.dist.neon`
Expected: `[OK] No errors`

- [ ] **Step 8: Commit**

```bash
git add src/Request tests/Unit/Request
git commit -m "feat: kimeno keres-DTO-k a SimplePay camelCase mezoneveivel"
```

---

## Task 8: `PayloadReader` és a bejövő válasz-DTO-k

**Files:**
- Create: `src/Internal/PayloadReader.php`
- Create: `src/Response/StartResponse.php`, `src/Response/Transaction.php`, `src/Response/QueryResponse.php`, `src/Response/RefundResponse.php`
- Test: `tests/Unit/Internal/PayloadReaderTest.php`, `tests/Unit/Response/StartResponseTest.php`, `tests/Unit/Response/QueryResponseTest.php`, `tests/Unit/Response/RefundResponseTest.php`

**Interfaces:**
- Consumes: `Money`, `Currency` (Task 5), `TransactionStatus`, `PaymentMethod` (Task 6), `UnexpectedResponseException` (Task 2)
- Produces:
  - `Internal\PayloadReader` — statikus metódusok: `string(array $payload, string $key): string`, `nullableString(array $payload, string $key): ?string`, `int(array $payload, string $key): int`, `scalarAmount(array $payload, string $key): string|int|float`, `dateTime(array $payload, string $key): \DateTimeImmutable`, `nullableDateTime(array $payload, string $key): ?\DateTimeImmutable`, `mapList(array $payload, string $key): list<array<string, mixed>>`
  - `Response\StartResponse` readonly: `$salt`, `$merchant`, `$orderRef`, `$transactionId`, `$paymentUrl` (string), `$total` (Money), `$timeout` (`?\DateTimeImmutable`); `fromPayload(array $payload): self`
  - `Response\Transaction` readonly: `$merchant`, `$orderRef`, `$transactionId` (string), `$status` (TransactionStatus), `$total` (`?Money`), `$paymentDate` (`?\DateTimeImmutable`), `$method` (`?PaymentMethod`); `fromPayload(array $payload): self`
  - `Response\QueryResponse` readonly: `$transactions` (`list<Transaction>`), `$totalCount` (int); `fromPayload(array $payload): self`, `first(): ?Transaction`, `byOrderRef(string $orderRef): ?Transaction`
  - `Response\RefundResponse` readonly: `$merchant`, `$orderRef`, `$transactionId` (string), `$refundTransactionId` (`?string`), `$status` (`?TransactionStatus`); `fromPayload(array $payload): self`

- [ ] **Step 1: Írd meg a `PayloadReader` bukó tesztjét**

`tests/Unit/Internal/PayloadReaderTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Internal;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Internal\PayloadReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayloadReader::class)]
final class PayloadReaderTest extends TestCase
{
    public function testStringReadsAString(): void
    {
        self::assertSame('érték', PayloadReader::string(['kulcs' => 'érték'], 'kulcs'));
    }

    public function testStringCastsAnInteger(): void
    {
        self::assertSame('99999999', PayloadReader::string(['t' => 99999999], 't'));
    }

    public function testMissingStringNamesTheKey(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('orderRef');

        PayloadReader::string([], 'orderRef');
    }

    public function testNullableStringReturnsNullWhenAbsent(): void
    {
        self::assertNull(PayloadReader::nullableString([], 'nincs'));
    }

    public function testIntReadsAnInteger(): void
    {
        self::assertSame(3, PayloadReader::int(['totalCount' => 3], 'totalCount'));
    }

    public function testIntAcceptsANumericString(): void
    {
        self::assertSame(3, PayloadReader::int(['totalCount' => '3'], 'totalCount'));
    }

    public function testIntRejectsNonNumeric(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        PayloadReader::int(['totalCount' => 'három'], 'totalCount');
    }

    public function testDateTimeParsesIso8601(): void
    {
        $date = PayloadReader::dateTime(['paymentDate' => '2026-08-30T12:00:00+02:00'], 'paymentDate');

        self::assertSame('2026-08-30T12:00:00+02:00', $date->format(\DateTimeInterface::ATOM));
    }

    public function testDateTimeRejectsGarbage(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        PayloadReader::dateTime(['paymentDate' => 'tegnap'], 'paymentDate');
    }

    public function testNullableDateTimeReturnsNullWhenAbsent(): void
    {
        self::assertNull(PayloadReader::nullableDateTime([], 'paymentDate'));
    }

    public function testMapListReadsAListOfMaps(): void
    {
        $list = PayloadReader::mapList(['transactions' => [['a' => 1], ['b' => 2]]], 'transactions');

        self::assertCount(2, $list);
        self::assertSame(1, $list[0]['a']);
    }

    public function testMapListReturnsEmptyWhenAbsent(): void
    {
        self::assertSame([], PayloadReader::mapList([], 'transactions'));
    }

    public function testMapListRejectsAScalarElement(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        PayloadReader::mapList(['transactions' => ['nem tömb']], 'transactions');
    }
}
```

- [ ] **Step 2: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/Internal`
Expected: FAIL — `Class "CodeConjure\SimplePay\Internal\PayloadReader" not found`.

- [ ] **Step 3: Írd meg a `PayloadReader`-t**

`src/Internal/PayloadReader.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Internal;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;

/**
 * Tipizált mezőolvasás a SimplePay nyers válaszaiból.
 *
 * Minden hiányzó vagy rossz típusú kötelező mező kivételt dob — soha nem
 * ad csendben alapértelmezett értéket.
 *
 * @internal
 */
final class PayloadReader
{
    /** @param array<string, mixed> $payload */
    public static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (!is_scalar($value) || '' === (string) $value) {
            throw self::missing($key, $value);
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $payload */
    public static function nullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (null === $value || !is_scalar($value) || '' === (string) $value) {
            return null;
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $payload */
    public static function int(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        if (!is_int($value) && !(is_string($value) && 1 === preg_match('/^-?\d+$/', $value))) {
            throw self::missing($key, $value);
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $payload */
    public static function scalarAmount(array $payload, string $key): string|int|float
    {
        $value = $payload[$key] ?? null;

        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw self::missing($key, $value);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public static function dateTime(array $payload, string $key): \DateTimeImmutable
    {
        $raw = self::string($payload, $key);

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception $exception) {
            throw new UnexpectedResponseException(
                sprintf('A SimplePay "%s" mezője nem értelmezhető dátum: "%s".', $key, $raw),
                previous: $exception,
            );
        }
    }

    /** @param array<string, mixed> $payload */
    public static function nullableDateTime(array $payload, string $key): ?\DateTimeImmutable
    {
        return null === self::nullableString($payload, $key) ? null : self::dateTime($payload, $key);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    public static function mapList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        if (!is_array($value)) {
            throw self::missing($key, $value);
        }

        $list = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new UnexpectedResponseException(sprintf(
                    'A SimplePay "%s" listájának minden eleme objektum kell legyen.',
                    $key,
                ));
            }

            /** @var array<string, mixed> $item */
            $list[] = $item;
        }

        return $list;
    }

    private static function missing(string $key, mixed $value): UnexpectedResponseException
    {
        return new UnexpectedResponseException(sprintf(
            'A SimplePay válaszából hiányzik vagy rossz típusú a "%s" mező (kapott: %s).',
            $key,
            get_debug_type($value),
        ));
    }
}
```

- [ ] **Step 4: Futtasd a `PayloadReader` tesztjeit**

Run: `vendor/bin/phpunit tests/Unit/Internal`
Expected: PASS, 13 teszt.

- [ ] **Step 5: Írd meg a válasz-DTO-k bukó tesztjeit**

`tests/Unit/Response/StartResponseTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Response;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Response\StartResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StartResponse::class)]
final class StartResponseTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function payload(): array
    {
        return [
            'salt' => 'abcdefghijklmnopqrstuvwxyz012345',
            'merchant' => 'PUBLICTESTHUF',
            'orderRef' => 'ORDER-1',
            'currency' => 'HUF',
            'transactionId' => 99999999,
            'timeout' => '2026-08-30T12:30:00+02:00',
            'total' => 1000,
            'paymentUrl' => 'https://sandbox.simplepay.hu/pay/pay/xyz',
        ];
    }

    public function testItReadsEveryField(): void
    {
        $response = StartResponse::fromPayload(self::payload());

        self::assertSame('PUBLICTESTHUF', $response->merchant);
        self::assertSame('ORDER-1', $response->orderRef);
        self::assertSame('99999999', $response->transactionId);
        self::assertSame('https://sandbox.simplepay.hu/pay/pay/xyz', $response->paymentUrl);
        self::assertSame(1000, $response->total->minorUnits);
        self::assertSame(Currency::HUF, $response->total->currency);
    }

    public function testTransactionIdBecomesAString(): void
    {
        self::assertIsString(StartResponse::fromPayload(self::payload())->transactionId);
    }

    public function testAMissingPaymentUrlIsLoud(): void
    {
        $payload = self::payload();
        unset($payload['paymentUrl']);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('paymentUrl');

        StartResponse::fromPayload($payload);
    }

    public function testAMissingTimeoutIsTolerated(): void
    {
        $payload = self::payload();
        unset($payload['timeout']);

        self::assertNull(StartResponse::fromPayload($payload)->timeout);
    }
}
```

`tests/Unit/Response/QueryResponseTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Response;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\Response\QueryResponse;
use CodeConjure\SimplePay\TransactionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryResponse::class)]
final class QueryResponseTest extends TestCase
{
    /**
     * @return array{
     *     salt: string,
     *     merchant: string,
     *     totalCount: int,
     *     transactions: list<array<string, mixed>>,
     * }
     */
    private static function payload(): array
    {
        return [
            'salt' => 'abcdefghijklmnopqrstuvwxyz012345',
            'merchant' => 'PUBLICTESTHUF',
            'totalCount' => 2,
            'transactions' => [
                [
                    'merchant' => 'PUBLICTESTHUF',
                    'orderRef' => 'ORDER-1',
                    'transactionId' => 99999999,
                    'status' => 'FINISHED',
                    'resultCode' => 'OK',
                    'total' => 1000,
                    'remainingTotal' => 0,
                    'currency' => 'HUF',
                    'paymentDate' => '2026-08-30T12:05:00+02:00',
                    'finishDate' => '2026-08-30T12:05:30+02:00',
                    'method' => 'CARD',
                ],
                [
                    'merchant' => 'PUBLICTESTHUF',
                    'orderRef' => 'ORDER-2',
                    'transactionId' => 99999998,
                    'status' => 'CANCELLED',
                    'currency' => 'HUF',
                ],
            ],
        ];
    }

    public function testTheStatusComesFromInsideTheTransactionsList(): void
    {
        $response = QueryResponse::fromPayload(self::payload());

        self::assertCount(2, $response->transactions);
        self::assertSame(TransactionStatus::Finished, $response->transactions[0]->status);
        self::assertSame(TransactionStatus::Cancelled, $response->transactions[1]->status);
    }

    public function testTotalCountIsRead(): void
    {
        self::assertSame(2, QueryResponse::fromPayload(self::payload())->totalCount);
    }

    public function testFirstReturnsTheFirstTransaction(): void
    {
        self::assertSame('ORDER-1', QueryResponse::fromPayload(self::payload())->first()?->orderRef);
    }

    public function testByOrderRefFindsTheMatchingTransaction(): void
    {
        self::assertSame(
            TransactionStatus::Cancelled,
            QueryResponse::fromPayload(self::payload())->byOrderRef('ORDER-2')?->status,
        );
    }

    public function testByOrderRefReturnsNullWhenAbsent(): void
    {
        self::assertNull(QueryResponse::fromPayload(self::payload())->byOrderRef('ORDER-9'));
    }

    public function testOptionalTransactionFieldsMayBeMissing(): void
    {
        $second = QueryResponse::fromPayload(self::payload())->transactions[1];

        self::assertNull($second->paymentDate);
        self::assertNull($second->finishDate);
        self::assertNull($second->method);
        self::assertNull($second->total);
        self::assertNull($second->remainingTotal);
        self::assertNull($second->resultCode);
    }

    public function testMethodIsParsed(): void
    {
        self::assertSame(PaymentMethod::Card, QueryResponse::fromPayload(self::payload())->transactions[0]->method);
    }

    public function testResultCodeIsParsed(): void
    {
        self::assertSame('OK', QueryResponse::fromPayload(self::payload())->transactions[0]->resultCode);
    }

    public function testFinishDateIsParsed(): void
    {
        $finishDate = QueryResponse::fromPayload(self::payload())->transactions[0]->finishDate;

        self::assertNotNull($finishDate);
        self::assertSame('2026-08-30T12:05:30+02:00', $finishDate->format(\DateTimeInterface::ATOM));
    }

    public function testRemainingTotalIsParsedAsMoney(): void
    {
        $remainingTotal = QueryResponse::fromPayload(self::payload())->transactions[0]->remainingTotal;

        self::assertNotNull($remainingTotal);
        self::assertSame(0, $remainingTotal->minorUnits);
    }

    public function testAnEmptyResultIsValid(): void
    {
        $response = QueryResponse::fromPayload(['totalCount' => 0, 'transactions' => []]);

        self::assertSame([], $response->transactions);
        self::assertNull($response->first());
    }

    public function testAnUnknownStatusInsideTheListIsLoud(): void
    {
        $payload = self::payload();
        $payload['transactions'][0]['status'] = 'COMPLETE';

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('COMPLETE');

        QueryResponse::fromPayload($payload);
    }

    public function testATotalWithoutACurrencyIsLoud(): void
    {
        $payload = self::payload();
        unset($payload['transactions'][0]['currency']);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('99999999');

        QueryResponse::fromPayload($payload);
    }

    public function testARemainingTotalWithoutACurrencyIsLoud(): void
    {
        $payload = self::payload();
        unset($payload['transactions'][0]['currency'], $payload['transactions'][0]['total']);
        $payload['transactions'][0]['remainingTotal'] = 0;

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('99999999');

        QueryResponse::fromPayload($payload);
    }

    public function testACurrencyWithoutATotalConstructsWithNullTotal(): void
    {
        $second = QueryResponse::fromPayload(self::payload())->transactions[1];

        self::assertNull($second->total);
    }

    public function testBothCurrencyAndTotalAbsentLeavesTotalNull(): void
    {
        $payload = self::payload();
        unset($payload['transactions'][1]['currency']);

        $second = QueryResponse::fromPayload($payload)->transactions[1];

        self::assertNull($second->total);
    }
}
```

`tests/Unit/Response/RefundResponseTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Response;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Response\RefundResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundResponse::class)]
final class RefundResponseTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function payload(): array
    {
        return [
            'salt' => 'abcdefghijklmnopqrstuvwxyz012345',
            'merchant' => 'PUBLICTESTHUF',
            'orderRef' => 'ORDER-1',
            'currency' => 'HUF',
            'transactionId' => 99999999,
            'refundTransactionId' => 88888888,
            'refundTotal' => 5,
            'remainingTotal' => 10,
        ];
    }

    public function testItReadsTheRefundAmountsAsMoney(): void
    {
        $response = RefundResponse::fromPayload(self::payload());

        self::assertSame('99999999', $response->transactionId);
        self::assertSame('88888888', $response->refundTransactionId);
        self::assertSame(5, $response->refundTotal->minorUnits);
        self::assertSame(Currency::HUF, $response->refundTotal->currency);
        self::assertSame(10, $response->remainingTotal->minorUnits);
        self::assertSame(Currency::HUF, $response->remainingTotal->currency);
    }

    public function testRefundTransactionIdMayBeMissing(): void
    {
        $payload = self::payload();
        unset($payload['refundTransactionId']);

        self::assertNull(RefundResponse::fromPayload($payload)->refundTransactionId);
    }

    public function testAMissingCurrencyIsLoud(): void
    {
        $payload = self::payload();
        unset($payload['currency']);

        $this->expectException(UnexpectedResponseException::class);

        RefundResponse::fromPayload($payload);
    }

    public function testAMissingRefundTotalIsLoud(): void
    {
        $payload = self::payload();
        unset($payload['refundTotal']);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('refundTotal');

        RefundResponse::fromPayload($payload);
    }

    public function testAMissingRemainingTotalIsLoud(): void
    {
        $payload = self::payload();
        unset($payload['remainingTotal']);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('remainingTotal');

        RefundResponse::fromPayload($payload);
    }
}
```

- [ ] **Step 6: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/Response`
Expected: FAIL — hiányzó osztályok.

- [ ] **Step 7: Írd meg a `StartResponse`-t**

`src/Response/StartResponse.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\Money;

final readonly class StartResponse
{
    public function __construct(
        public string $merchant,
        public string $orderRef,
        public string $transactionId,
        public string $paymentUrl,
        public Money $total,
        public ?string $salt = null,
        public ?\DateTimeImmutable $timeout = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $currency = Currency::fromApi(PayloadReader::string($payload, 'currency'));

        return new self(
            merchant: PayloadReader::string($payload, 'merchant'),
            orderRef: PayloadReader::string($payload, 'orderRef'),
            transactionId: PayloadReader::string($payload, 'transactionId'),
            paymentUrl: PayloadReader::string($payload, 'paymentUrl'),
            total: Money::fromApiValue(PayloadReader::scalarAmount($payload, 'total'), $currency),
            salt: PayloadReader::nullableString($payload, 'salt'),
            timeout: PayloadReader::nullableDateTime($payload, 'timeout'),
        );
    }
}
```

- [ ] **Step 8: Írd meg a `Transaction`-t és a `QueryResponse`-t**

`src/Response/Transaction.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\TransactionStatus;

final readonly class Transaction
{
    public function __construct(
        public string $merchant,
        public string $orderRef,
        public string $transactionId,
        public TransactionStatus $status,
        public ?Money $total = null,
        public ?Money $remainingTotal = null,
        public ?\DateTimeImmutable $paymentDate = null,
        public ?\DateTimeImmutable $finishDate = null,
        public ?PaymentMethod $method = null,
        public ?string $resultCode = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $currencyCode = PayloadReader::nullableString($payload, 'currency');
        // isset() kezeli az explicit `null` és a hiányzó kulcs esetét egyformán mindkét
        // összeg-mezőnél — a SimplePay nem szokott explicit nullt küldeni, így ez a
        // megkülönböztetés szándékosan nem számít itt.
        $hasTotal = isset($payload['total']);
        $hasRemainingTotal = isset($payload['remainingTotal']);

        $currency = null;

        if (null !== $currencyCode) {
            $currency = Currency::fromApi($currencyCode);
        } elseif ($hasTotal || $hasRemainingTotal) {
            $transactionId = PayloadReader::nullableString($payload, 'transactionId');

            throw new UnexpectedResponseException(sprintf(
                'A SimplePay tranzakció összeget küldött pénznem nélkül%s.',
                null !== $transactionId ? sprintf(' (tranzakcióazonosító: %s)', $transactionId) : '',
            ));
        }

        $method = PayloadReader::nullableString($payload, 'method');

        return new self(
            merchant: PayloadReader::string($payload, 'merchant'),
            orderRef: PayloadReader::string($payload, 'orderRef'),
            transactionId: PayloadReader::string($payload, 'transactionId'),
            status: TransactionStatus::fromApi(PayloadReader::string($payload, 'status')),
            total: ($hasTotal && null !== $currency)
                ? Money::fromApiValue(PayloadReader::scalarAmount($payload, 'total'), $currency)
                : null,
            remainingTotal: ($hasRemainingTotal && null !== $currency)
                ? Money::fromApiValue(PayloadReader::scalarAmount($payload, 'remainingTotal'), $currency)
                : null,
            paymentDate: PayloadReader::nullableDateTime($payload, 'paymentDate'),
            finishDate: PayloadReader::nullableDateTime($payload, 'finishDate'),
            method: null === $method ? null : PaymentMethod::fromApi($method),
            resultCode: PayloadReader::nullableString($payload, 'resultCode'),
        );
    }
}
```

`src/Response/QueryResponse.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Internal\PayloadReader;

/**
 * A SimplePay a lekérdezés eredményét a `transactions` tömbben adja vissza,
 * a státusz azon belül van — nem a válasz legfelső szintjén.
 */
final readonly class QueryResponse
{
    /** @param list<Transaction> $transactions */
    public function __construct(
        public array $transactions,
        public int $totalCount,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $transactions = array_map(
            Transaction::fromPayload(...),
            PayloadReader::mapList($payload, 'transactions'),
        );

        return new self($transactions, PayloadReader::int($payload, 'totalCount'));
    }

    public function first(): ?Transaction
    {
        return $this->transactions[0] ?? null;
    }

    public function byOrderRef(string $orderRef): ?Transaction
    {
        foreach ($this->transactions as $transaction) {
            if ($transaction->orderRef === $orderRef) {
                return $transaction;
            }
        }

        return null;
    }
}
```

- [ ] **Step 9: Írd meg a `RefundResponse`-t**

`src/Response/RefundResponse.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\Money;

final readonly class RefundResponse
{
    public function __construct(
        public string $merchant,
        public string $orderRef,
        public string $transactionId,
        public Money $refundTotal,
        public Money $remainingTotal,
        public ?string $refundTransactionId = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $currency = Currency::fromApi(PayloadReader::string($payload, 'currency'));

        return new self(
            merchant: PayloadReader::string($payload, 'merchant'),
            orderRef: PayloadReader::string($payload, 'orderRef'),
            transactionId: PayloadReader::string($payload, 'transactionId'),
            refundTotal: Money::fromApiValue(PayloadReader::scalarAmount($payload, 'refundTotal'), $currency),
            remainingTotal: Money::fromApiValue(PayloadReader::scalarAmount($payload, 'remainingTotal'), $currency),
            refundTransactionId: PayloadReader::nullableString($payload, 'refundTransactionId'),
        );
    }
}
```

- [ ] **Step 10: Futtasd az összes tesztet**

Run: `vendor/bin/phpunit`
Expected: PASS, minden eddigi teszt.

- [ ] **Step 11: Statikus elemzés**

Run: `vendor/bin/phpstan analyse -c phpstan.dist.neon`
Expected: `[OK] No errors`

- [ ] **Step 12: Commit**

```bash
git add src/Internal src/Response tests/Unit/Internal tests/Unit/Response
git commit -m "feat: valasz-DTO-k, a query statusza a transactions tombbol"
```

---

## Task 9: A `Client` transzport-rétege

**Files:**
- Create: `src/Client.php`
- Test: `tests/Unit/ClientTransportTest.php`

**Interfaces:**
- Consumes: `Config` (Task 4), `SaltGenerator` (Task 4), `Signature` (Task 3), a Task 2 kivételei, a Task 7 kérés-DTO-i, a Task 8 válasz-DTO-i
- Produces: `Client` readonly, `__construct(Config $config, ClientInterface $httpClient, RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory, SaltGenerator $saltGenerator = new SaltGenerator())`. Ebben a taskban a privát `post(string $endpoint, array $payload): array<string, mixed>` készül el, és a publikus `start()` metódus mint első használója.

A válaszfeldolgozás rögzített sorrendje — a tesztek pontosan ezt ellenőrzik:

1. `ClientExceptionInterface` a HTTP kliensből → `TransportException`
2. hiányzó `Signature` fejléc: nem-2xx státusz esetén `TransportException`, 2xx esetén `SignatureException`
3. nem stimmelő aláírás → `SignatureException`
4. sikertelen JSON dekódolás → `TransportException`
5. nem üres `errorCodes` → `RequestException::fromCodes()`
6. nem-2xx státusz idáig eljutva → `TransportException`
7. egyébként a dekódolt tömb

- [ ] **Step 1: Írd meg a bukó tesztet**

`tests/Unit/ClientTransportTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Client;
use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Environment;
use CodeConjure\SimplePay\Exception\DeveloperException;
use CodeConjure\SimplePay\Exception\RequestException;
use CodeConjure\SimplePay\Exception\SignatureException;
use CodeConjure\SimplePay\Exception\TransportException;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\Request\Invoice;
use CodeConjure\SimplePay\Request\StartRequest;
use CodeConjure\SimplePay\Request\Urls;
use CodeConjure\SimplePay\SaltGenerator;
use CodeConjure\SimplePay\Signature;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(Client::class)]
final class ClientTransportTest extends TestCase
{
    private const string SECRET = 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6';

    private MockClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockClient();
    }

    private function client(): Client
    {
        $factory = new Psr17Factory();

        return new Client(
            new Config('PUBLICTESTHUF', self::SECRET, Environment::Sandbox),
            $this->httpClient,
            $factory,
            $factory,
            new SaltGenerator(new Randomizer(new Xoshiro256StarStar(1234))),
        );
    }

    /** @param array<string, mixed> $payload */
    private function signedResponse(array $payload, int $status = 200): Response
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return new Response($status, ['Signature' => new Signature(self::SECRET)->sign($body)], $body);
    }

    private function startRequest(): StartRequest
    {
        return new StartRequest(
            orderRef: 'ORDER-1',
            total: Money::fromMinorUnits(1000, Currency::HUF),
            customerEmail: 'teszt@example.com',
            invoice: new Invoice('Teszt Elek', 'HU', 'Budapest', '1011', 'Fő utca 1.'),
            urls: new Urls(
                'https://bolt.hu/s',
                'https://bolt.hu/f',
                'https://bolt.hu/c',
                'https://bolt.hu/t',
            ),
        );
    }

    /** @return array<string, mixed> */
    private static function startPayload(): array
    {
        return [
            'merchant' => 'PUBLICTESTHUF',
            'orderRef' => 'ORDER-1',
            'currency' => 'HUF',
            'transactionId' => 99999999,
            'total' => 1000,
            'paymentUrl' => 'https://sandbox.simplepay.hu/pay/pay/xyz',
        ];
    }

    public function testItPostsToTheStartEndpoint(): void
    {
        $this->httpClient->addResponse($this->signedResponse(self::startPayload()));

        $this->client()->start($this->startRequest());

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://sandbox.simplepay.hu/payment/v2/start', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testTheSentBodyIsSignedExactlyAsSent(): void
    {
        $this->httpClient->addResponse($this->signedResponse(self::startPayload()));

        $this->client()->start($this->startRequest());

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        $body = (string) $request->getBody();

        self::assertTrue(
            new Signature(self::SECRET)->verify($body, $request->getHeaderLine('Signature')),
            'Az aláírásnak a ténylegesen elküldött byte-okra kell illeszkednie.',
        );
    }

    public function testTheClientAddsMerchantSaltAndSdkVersion(): void
    {
        $this->httpClient->addResponse($this->signedResponse(self::startPayload()));

        $this->client()->start($this->startRequest());

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        $sent = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($sent);
        self::assertSame('PUBLICTESTHUF', $sent['merchant']);
        self::assertSame(32, strlen((string) $sent['salt']));
        self::assertStringContainsString('CodeConjure', (string) $sent['sdkVersion']);
    }

    public function testAnUnsignedSuccessfulResponseIsRejected(): void
    {
        $this->httpClient->addResponse(new Response(200, [], json_encode(self::startPayload(), JSON_THROW_ON_ERROR)));

        $this->expectException(SignatureException::class);

        $this->client()->start($this->startRequest());
    }

    public function testATamperedResponseIsRejected(): void
    {
        $body = json_encode(self::startPayload(), JSON_THROW_ON_ERROR);
        $this->httpClient->addResponse(new Response(200, ['Signature' => 'aGFtaXM='], $body));

        $this->expectException(SignatureException::class);

        $this->client()->start($this->startRequest());
    }

    public function testAnUnsignedServerErrorBecomesATransportException(): void
    {
        $this->httpClient->addResponse(new Response(502, [], 'Bad Gateway'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('502');

        $this->client()->start($this->startRequest());
    }

    public function testAMalformedBodyBecomesATransportException(): void
    {
        $this->httpClient->addResponse(new Response(200, ['Signature' => new Signature(self::SECRET)->sign('nem json')], 'nem json'));

        $this->expectException(TransportException::class);

        $this->client()->start($this->startRequest());
    }

    public function testErrorCodesBecomeARequestException(): void
    {
        $this->httpClient->addResponse($this->signedResponse(['errorCodes' => [2013]], 400));

        try {
            $this->client()->start($this->startRequest());
            self::fail('Kivételt vártunk.');
        } catch (RequestException $exception) {
            self::assertSame([2013], $exception->codes());
            self::assertNotInstanceOf(DeveloperException::class, $exception);
        }
    }

    public function testDeveloperErrorCodesBecomeADeveloperException(): void
    {
        $this->httpClient->addResponse($this->signedResponse(['errorCodes' => [2003]], 400));

        $this->expectException(DeveloperException::class);

        $this->client()->start($this->startRequest());
    }

    public function testATransportFailureIsWrapped(): void
    {
        $this->httpClient->addException(new class extends \RuntimeException implements ClientExceptionInterface {});

        $this->expectException(TransportException::class);

        $this->client()->start($this->startRequest());
    }

    public function testStartReturnsATypedResponse(): void
    {
        $this->httpClient->addResponse($this->signedResponse(self::startPayload()));

        $response = $this->client()->start($this->startRequest());

        self::assertSame('https://sandbox.simplepay.hu/pay/pay/xyz', $response->paymentUrl);
        self::assertSame('99999999', $response->transactionId);
    }
}
```

- [ ] **Step 2: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/ClientTransportTest.php`
Expected: FAIL — `Class "CodeConjure\SimplePay\Client" not found`.

- [ ] **Step 3: Írd meg a `Client`-et a transzporttal és a `start()`-tal**

`src/Client.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay;

use CodeConjure\SimplePay\Exception\RequestException;
use CodeConjure\SimplePay\Exception\SignatureException;
use CodeConjure\SimplePay\Exception\TransportException;
use CodeConjure\SimplePay\Request\StartRequest;
use CodeConjure\SimplePay\Response\StartResponse;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class Client
{
    public const string SDK_VERSION = 'CodeConjure_SimplePay/1.0';

    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private Config $config,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private SaltGenerator $saltGenerator = new SaltGenerator(),
    ) {
    }

    public function start(StartRequest $request): StartResponse
    {
        return StartResponse::fromPayload($this->post('start', $request->toPayload()));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $payload): array
    {
        $payload['merchant'] = $this->config->merchant;
        $payload['salt'] = $this->saltGenerator->generate();
        $payload['sdkVersion'] = self::SDK_VERSION;

        $body = json_encode($payload, self::JSON_FLAGS);
        $signature = $this->config->signature();

        $httpRequest = $this->requestFactory
            ->createRequest('POST', $this->config->baseUrl() . $endpoint)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Signature', $signature->sign($body))
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($httpRequest);
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException(
                sprintf('A SimplePay "%s" hívása nem jutott el a szolgáltatóig.', $endpoint),
                previous: $exception,
            );
        }

        $status = $response->getStatusCode();
        $responseBody = (string) $response->getBody();
        $responseSignature = $response->getHeaderLine('Signature');

        if ('' === $responseSignature) {
            if ($status < 200 || $status >= 300) {
                throw new TransportException(sprintf(
                    'A SimplePay "%s" hívása %d státusszal, aláírás nélkül tért vissza.',
                    $endpoint,
                    $status,
                ));
            }

            throw new SignatureException(sprintf(
                'A SimplePay "%s" válaszából hiányzik a Signature fejléc.',
                $endpoint,
            ));
        }

        if (!$signature->verify($responseBody, $responseSignature)) {
            throw new SignatureException(sprintf(
                'A SimplePay "%s" válaszának aláírása nem stimmel.',
                $endpoint,
            ));
        }

        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new TransportException(
                sprintf('A SimplePay "%s" válasza nem értelmezhető JSON.', $endpoint),
                previous: $exception,
            );
        }

        if (!is_array($decoded)) {
            throw new TransportException(sprintf('A SimplePay "%s" válasza nem objektum.', $endpoint));
        }

        /** @var array<string, mixed> $decoded */
        $errorCodes = $this->extractErrorCodes($decoded);

        if ([] !== $errorCodes) {
            throw RequestException::fromCodes($errorCodes);
        }

        if ($status < 200 || $status >= 300) {
            throw new TransportException(sprintf(
                'A SimplePay "%s" hívása %d státusszal tért vissza, hibakód nélkül.',
                $endpoint,
                $status,
            ));
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<int>
     */
    private function extractErrorCodes(array $payload): array
    {
        $raw = $payload['errorCodes'] ?? null;

        if (!is_array($raw)) {
            return [];
        }

        $codes = [];

        foreach ($raw as $code) {
            if (is_int($code) || (is_string($code) && 1 === preg_match('/^\d+$/', $code))) {
                $codes[] = (int) $code;
            }
        }

        return $codes;
    }
}
```

- [ ] **Step 4: Futtasd újra**

Run: `vendor/bin/phpunit tests/Unit/ClientTransportTest.php`
Expected: PASS, 11 teszt.

- [ ] **Step 5: Statikus elemzés**

Run: `vendor/bin/phpstan analyse -c phpstan.dist.neon`
Expected: `[OK] No errors`

- [ ] **Step 6: Commit**

```bash
git add src/Client.php tests/Unit/ClientTransportTest.php
git commit -m "feat: Client transzport alairt keressel es valasz-alairas ellenorzessel"
```

---

## Task 10: `query()` és `refund()`

**Files:**
- Modify: `src/Client.php` — új publikus metódusok a `start()` mellé
- Test: `tests/Unit/ClientOperationsTest.php`

**Interfaces:**
- Consumes: a Task 9 `Client::post()` metódusa, a Task 7 `QueryRequest`/`RefundRequest` osztályai, a Task 8 `QueryResponse`/`RefundResponse` osztályai
- Produces: `Client::query(QueryRequest $request): QueryResponse`, `Client::refund(RefundRequest $request): RefundResponse`

- [ ] **Step 1: Írd meg a bukó tesztet**

`tests/Unit/ClientOperationsTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Client;
use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Environment;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\Request\QueryRequest;
use CodeConjure\SimplePay\Request\RefundRequest;
use CodeConjure\SimplePay\Signature;
use CodeConjure\SimplePay\TransactionStatus;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Client::class)]
final class ClientOperationsTest extends TestCase
{
    private const string SECRET = 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6';

    private MockClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockClient();
    }

    private function client(): Client
    {
        $factory = new Psr17Factory();

        return new Client(
            new Config('PUBLICTESTHUF', self::SECRET, Environment::Sandbox),
            $this->httpClient,
            $factory,
            $factory,
        );
    }

    /** @param array<string, mixed> $payload */
    private function signedResponse(array $payload): Response
    {
        $body = json_encode($payload, \JSON_THROW_ON_ERROR);

        return new Response(200, ['Signature' => new Signature(self::SECRET)->sign($body)], $body);
    }

    public function testQueryHitsTheQueryEndpoint(): void
    {
        $this->httpClient->addResponse($this->signedResponse(['totalCount' => 0, 'transactions' => []]));

        $this->client()->query(new QueryRequest(orderRefs: ['ORDER-1']));

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        self::assertSame('https://sandbox.simplepay.hu/payment/v2/query', (string) $request->getUri());
    }

    public function testQuerySendsOrderRefsAsAList(): void
    {
        $this->httpClient->addResponse($this->signedResponse(['totalCount' => 0, 'transactions' => []]));

        $this->client()->query(new QueryRequest(orderRefs: ['ORDER-1']));

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        $sent = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($sent);
        self::assertSame(['ORDER-1'], $sent['orderRefs']);
        self::assertArrayNotHasKey('order_ref', $sent);
        self::assertArrayNotHasKey('transaction_id', $sent);
    }

    public function testQueryReadsTheStatusFromTheTransactionsList(): void
    {
        $this->httpClient->addResponse($this->signedResponse([
            'totalCount' => 1,
            'transactions' => [[
                'merchant' => 'PUBLICTESTHUF',
                'orderRef' => 'ORDER-1',
                'transactionId' => 99999999,
                'status' => 'FINISHED',
                'total' => 1000,
                'currency' => 'HUF',
            ]],
        ]));

        $response = $this->client()->query(new QueryRequest(orderRefs: ['ORDER-1']));

        $transaction = $response->first();
        self::assertNotNull($transaction);
        self::assertSame(TransactionStatus::Finished, $transaction->status);
        self::assertTrue($transaction->status->isSuccessful());
    }

    public function testRefundHitsTheRefundEndpoint(): void
    {
        $this->httpClient->addResponse($this->signedResponse([
            'merchant' => 'PUBLICTESTHUF',
            'orderRef' => 'ORDER-1',
            'currency' => 'HUF',
            'transactionId' => 99999999,
            'refundTransactionId' => 88888888,
            'refundTotal' => 1000,
            'remainingTotal' => 0,
        ]));

        $response = $this->client()->refund(new RefundRequest(
            refundTotal: Money::fromMinorUnits(1000, Currency::HUF),
            orderRef: 'ORDER-1',
        ));

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        self::assertSame('https://sandbox.simplepay.hu/payment/v2/refund', (string) $request->getUri());
        self::assertSame('88888888', $response->refundTransactionId);
        self::assertSame(1000, $response->refundTotal->minorUnits);
        self::assertSame(0, $response->remainingTotal->minorUnits);
    }

    public function testRefundSendsRefundTotal(): void
    {
        $this->httpClient->addResponse($this->signedResponse([
            'merchant' => 'PUBLICTESTHUF',
            'orderRef' => 'ORDER-1',
            'currency' => 'HUF',
            'transactionId' => 99999999,
            'refundTotal' => 500,
            'remainingTotal' => 0,
        ]));

        $this->client()->refund(new RefundRequest(
            refundTotal: Money::fromMinorUnits(500, Currency::HUF),
            orderRef: 'ORDER-1',
        ));

        $request = $this->httpClient->getLastRequest();
        self::assertNotFalse($request);
        $sent = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($sent);
        self::assertSame('500', $sent['refundTotal']);
        self::assertSame('HUF', $sent['currency']);
    }
}
```

- [ ] **Step 2: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/ClientOperationsTest.php`
Expected: FAIL — `Call to undefined method CodeConjure\SimplePay\Client::query()`.

- [ ] **Step 3: Bővítsd a `Client`-et**

A `src/Client.php` `use` blokkját egészítsd ki:

```php
use CodeConjure\SimplePay\Request\QueryRequest;
use CodeConjure\SimplePay\Request\RefundRequest;
use CodeConjure\SimplePay\Response\QueryResponse;
use CodeConjure\SimplePay\Response\RefundResponse;
```

A `start()` metódus után vedd fel:

```php
    public function query(QueryRequest $request): QueryResponse
    {
        return QueryResponse::fromPayload($this->post('query', $request->toPayload()));
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        return RefundResponse::fromPayload($this->post('refund', $request->toPayload()));
    }
```

- [ ] **Step 4: Futtasd újra**

Run: `vendor/bin/phpunit tests/Unit/ClientOperationsTest.php`
Expected: PASS, 5 teszt.

- [ ] **Step 5: Statikus elemzés**

Run: `vendor/bin/phpstan analyse -c phpstan.dist.neon`
Expected: `[OK] No errors`

- [ ] **Step 6: Commit**

```bash
git add src/Client.php tests/Unit/ClientOperationsTest.php
git commit -m "feat: query es refund muvelet"
```

---

## Task 11: IPN fogadás és a `receiveDate`-es visszaigazolás

**Files:**
- Create: `src/Ipn/IpnMessage.php`, `src/Ipn/IpnConfirmation.php`
- Modify: `src/Client.php` — `ipn()` metódus
- Test: `tests/Unit/Ipn/IpnTest.php`

**Interfaces:**
- Consumes: `Signature` (Task 3), `PayloadReader` (Task 8), `TransactionStatus`/`PaymentMethod` (Task 6), `SignatureException`/`TransportException`/`UnexpectedResponseException` (Task 2)
- Produces:
  - `Ipn\IpnMessage` readonly: `$merchant`, `$orderRef`, `$transactionId` (string), `$status` (TransactionStatus), `$method` (`?PaymentMethod`), `$paymentDate`, `$finishDate` (`?\DateTimeImmutable`), `$salt` (`?string`); `fromPayload(array $payload): self`
  - `Ipn\IpnConfirmation` readonly: `message(): IpnMessage`, `responseBody(): string`, `responseSignature(): string`
  - `Client::ipn(string $rawBody, string $signatureHeader, ?\DateTimeImmutable $receivedAt = null): IpnConfirmation`

A `responseBody()` a **bejövő byte-okból** épül: a záró `}` elé beszúrjuk a `receiveDate` mezőt, mindent mást változatlanul hagyunk. A `receiveDate` formátuma `\DateTimeInterface::ATOM`, egyetlen konstansban — a spec 16. fejezete szerint ezt a 2. fázis első valódi IPN-je hitelesíti.

- [ ] **Step 1: Írd meg a bukó tesztet**

`tests/Unit/Ipn/IpnTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Ipn;

use CodeConjure\SimplePay\Client;
use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Environment;
use CodeConjure\SimplePay\Exception\SignatureException;
use CodeConjure\SimplePay\Exception\TransportException;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Ipn\IpnConfirmation;
use CodeConjure\SimplePay\Ipn\IpnMessage;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\Signature;
use CodeConjure\SimplePay\TransactionStatus;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IpnMessage::class)]
#[CoversClass(IpnConfirmation::class)]
#[CoversClass(Client::class)]
final class IpnTest extends TestCase
{
    private const string SECRET = 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6';

    private const string BODY = '{"salt":"abcdefghijklmnopqrstuvwxyz012345","orderRef":"ORDER-1","method":"CARD","merchant":"PUBLICTESTHUF","finishDate":"2026-08-30T12:05:00+02:00","paymentDate":"2026-08-30T12:04:00+02:00","transactionId":99999999,"status":"FINISHED"}';

    private function client(): Client
    {
        $factory = new Psr17Factory();

        return new Client(
            new Config('PUBLICTESTHUF', self::SECRET, Environment::Sandbox),
            new MockClient(),
            $factory,
            $factory,
        );
    }

    private static function signature(string $body): string
    {
        return new Signature(self::SECRET)->sign($body);
    }

    private static function receivedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-30T12:06:00+02:00');
    }

    public function testItParsesTheMessage(): void
    {
        $message = $this->client()->ipn(self::BODY, self::signature(self::BODY))->message();

        self::assertSame('ORDER-1', $message->orderRef);
        self::assertSame('99999999', $message->transactionId);
        self::assertSame(TransactionStatus::Finished, $message->status);
        self::assertSame(PaymentMethod::Card, $message->method);
        self::assertSame('2026-08-30T12:04:00+02:00', $message->paymentDate?->format(\DateTimeInterface::ATOM));
    }

    public function testABadSignatureIsRejected(): void
    {
        $this->expectException(SignatureException::class);

        $this->client()->ipn(self::BODY, 'aGFtaXM=');
    }

    public function testAnEmptySignatureIsRejected(): void
    {
        $this->expectException(SignatureException::class);

        $this->client()->ipn(self::BODY, '');
    }

    public function testATamperedBodyIsRejected(): void
    {
        $tampered = str_replace('"FINISHED"', '"CANCELLED"', self::BODY);

        self::assertNotSame(self::BODY, $tampered, 'A teszt csak akkor értelmes, ha tényleg módosult a törzs.');

        $this->expectException(SignatureException::class);

        $this->client()->ipn($tampered, self::signature(self::BODY));
    }

    public function testTheConfirmationAppendsReceiveDate(): void
    {
        $body = $this->client()->ipn(self::BODY, self::signature(self::BODY), self::receivedAt())->responseBody();

        self::assertStringContainsString('"receiveDate":"2026-08-30T12:06:00+02:00"', $body);
    }

    public function testTheConfirmationPreservesTheIncomingBytes(): void
    {
        $body = $this->client()->ipn(self::BODY, self::signature(self::BODY), self::receivedAt())->responseBody();

        self::assertStringStartsWith(substr(self::BODY, 0, -1), $body);
        self::assertStringEndsWith('}', $body);
    }

    public function testTheConfirmationIsValidJson(): void
    {
        $body = $this->client()->ipn(self::BODY, self::signature(self::BODY), self::receivedAt())->responseBody();
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame('ORDER-1', $decoded['orderRef']);
        self::assertSame('2026-08-30T12:06:00+02:00', $decoded['receiveDate']);
    }

    public function testTheConfirmationIsSignedOverItsOwnBody(): void
    {
        $confirmation = $this->client()->ipn(self::BODY, self::signature(self::BODY), self::receivedAt());

        self::assertTrue(
            new Signature(self::SECRET)->verify($confirmation->responseBody(), $confirmation->responseSignature()),
        );
    }

    public function testAMalformedBodyIsRejected(): void
    {
        $body = 'nem json';

        $this->expectException(TransportException::class);

        $this->client()->ipn($body, self::signature($body));
    }

    public function testABodyThatIsNotAJsonObjectIsRejected(): void
    {
        $body = '"csak egy string"';

        $this->expectException(UnexpectedResponseException::class);

        $this->client()->ipn($body, self::signature($body));
    }
}
```

- [ ] **Step 2: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/Ipn`
Expected: FAIL — hiányzó osztályok.

- [ ] **Step 3: Írd meg az `IpnMessage`-et**

`src/Ipn/IpnMessage.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Ipn;

use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\PaymentMethod;
use CodeConjure\SimplePay\TransactionStatus;

final readonly class IpnMessage
{
    public function __construct(
        public string $merchant,
        public string $orderRef,
        public string $transactionId,
        public TransactionStatus $status,
        public ?PaymentMethod $method = null,
        public ?\DateTimeImmutable $paymentDate = null,
        public ?\DateTimeImmutable $finishDate = null,
        public ?string $salt = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $method = PayloadReader::nullableString($payload, 'method');

        return new self(
            merchant: PayloadReader::string($payload, 'merchant'),
            orderRef: PayloadReader::string($payload, 'orderRef'),
            transactionId: PayloadReader::string($payload, 'transactionId'),
            status: TransactionStatus::fromApi(PayloadReader::string($payload, 'status')),
            method: null === $method ? null : PaymentMethod::fromApi($method),
            paymentDate: PayloadReader::nullableDateTime($payload, 'paymentDate'),
            finishDate: PayloadReader::nullableDateTime($payload, 'finishDate'),
            salt: PayloadReader::nullableString($payload, 'salt'),
        );
    }
}
```

- [ ] **Step 4: Írd meg az `IpnConfirmation`-t**

`src/Ipn/IpnConfirmation.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Ipn;

/**
 * A SimplePay addig ismétli az értesítést, amíg meg nem kapja a kapott üzenet
 * `receiveDate` mezővel kiegészített, aláírt visszaigazolását. A hívó dolga,
 * hogy a `responseBody()`-t 200-as válaszként, a `responseSignature()`-t pedig
 * `Signature` fejlécként küldje vissza.
 */
final readonly class IpnConfirmation
{
    public function __construct(
        private IpnMessage $message,
        private string $responseBody,
        private string $responseSignature,
    ) {
    }

    public function message(): IpnMessage
    {
        return $this->message;
    }

    public function responseBody(): string
    {
        return $this->responseBody;
    }

    public function responseSignature(): string
    {
        return $this->responseSignature;
    }
}
```

- [ ] **Step 5: Bővítsd a `Client`-et az `ipn()` metódussal**

A `use` blokkba:

```php
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Ipn\IpnConfirmation;
use CodeConjure\SimplePay\Ipn\IpnMessage;
```

Az osztály konstansai közé:

```php
    private const string RECEIVE_DATE_FORMAT = \DateTimeInterface::ATOM;
```

A `refund()` után:

```php
    public function ipn(
        string $rawBody,
        string $signatureHeader,
        ?\DateTimeImmutable $receivedAt = null,
    ): IpnConfirmation {
        $signature = $this->config->signature();

        if ('' === trim($signatureHeader) || !$signature->verify($rawBody, $signatureHeader)) {
            throw new SignatureException('A SimplePay értesítés aláírása nem stimmel.');
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new TransportException('A SimplePay értesítés nem értelmezhető JSON.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new UnexpectedResponseException('A SimplePay értesítés törzse nem objektum.');
        }

        /** @var array<string, mixed> $decoded */
        $message = IpnMessage::fromPayload($decoded);
        $responseBody = $this->appendReceiveDate($rawBody, $receivedAt ?? new \DateTimeImmutable());

        return new IpnConfirmation($message, $responseBody, $signature->sign($responseBody));
    }

    /**
     * A visszaigazolás a bejövő byte-okból épül: a záró kapcsos zárójel elé
     * szúrjuk be a receiveDate mezőt, minden mást változatlanul hagyva.
     */
    private function appendReceiveDate(string $rawBody, \DateTimeImmutable $receivedAt): string
    {
        $trimmed = trim($rawBody);

        if (!str_starts_with($trimmed, '{') || !str_ends_with($trimmed, '}')) {
            throw new UnexpectedResponseException('A SimplePay értesítés törzse nem JSON objektum.');
        }

        $receiveDate = sprintf(
            '"receiveDate":%s',
            json_encode($receivedAt->format(self::RECEIVE_DATE_FORMAT), self::JSON_FLAGS),
        );

        $inner = trim(substr($trimmed, 1, -1));

        return '' === $inner
            ? '{' . $receiveDate . '}'
            : substr($trimmed, 0, -1) . ',' . $receiveDate . '}';
    }
```

- [ ] **Step 6: Futtasd újra**

Run: `vendor/bin/phpunit tests/Unit/Ipn`
Expected: PASS, 10 teszt.

- [ ] **Step 7: Statikus elemzés**

Run: `vendor/bin/phpstan analyse -c phpstan.dist.neon`
Expected: `[OK] No errors`

- [ ] **Step 8: Commit**

```bash
git add src/Ipn src/Client.php tests/Unit/Ipn
git commit -m "feat: IPN fogadas es a kotelezo receiveDate-es visszaigazolas"
```

---

## Task 12: Visszatérési adat (`r`/`s`)

**Files:**
- Create: `src/Response/ReturnData.php`
- Modify: `src/Client.php` — `parseReturn()` metódus
- Test: `tests/Unit/Response/ReturnDataTest.php`

**Interfaces:**
- Consumes: `Signature` (Task 3), `ReturnEvent` (Task 6), `PayloadReader` (Task 8), `SignatureException`/`UnexpectedResponseException` (Task 2)
- Produces:
  - `Response\ReturnData` readonly: `$event` (ReturnEvent), `$transactionId`, `$orderRef`, `$merchant` (string), `$responseCode` (int); `fromPayload(array $payload): self`
  - `Client::parseReturn(string $r, string $s): ReturnData`

A SimplePay a `r` paraméterben base64-elt JSON-t küld a következő rövid kulcsokkal: `r` (válaszkód), `t` (transactionId), `e` (esemény), `m` (merchant), `o` (orderRef). Az `s` az `r` **base64-sztringje** felett képzett aláírás — nem a dekódolt tartalom felett.

- [ ] **Step 1: Írd meg a bukó tesztet**

`tests/Unit/Response/ReturnDataTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit\Response;

use CodeConjure\SimplePay\Client;
use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Environment;
use CodeConjure\SimplePay\Exception\SignatureException;
use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\Response\ReturnData;
use CodeConjure\SimplePay\ReturnEvent;
use CodeConjure\SimplePay\Signature;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReturnData::class)]
#[CoversClass(Client::class)]
final class ReturnDataTest extends TestCase
{
    private const string SECRET = 'FxDa5w314kLlNseq2sKuVwaqZshZT5d6';

    private const string R = 'eyJyIjowLCJ0Ijo5OTk5OTk5OSwiZSI6IlNVQ0NFU1MiLCJtIjoiUFVCTElDVEVTVEhVRiIsIm8iOiJPUkRFUi0xIn0=';
    private const string S = 'OMc+d/5aQ05SMXkrVfMIB9WL2SbSLyfcabqyifMr1n1ktdtDGygmTr8gJQiQwVJ7';

    private function client(): Client
    {
        $factory = new Psr17Factory();

        return new Client(
            new Config('PUBLICTESTHUF', self::SECRET, Environment::Sandbox),
            new MockClient(),
            $factory,
            $factory,
        );
    }

    public function testItParsesTheReturnPayload(): void
    {
        $data = $this->client()->parseReturn(self::R, self::S);

        self::assertSame(ReturnEvent::Success, $data->event);
        self::assertSame('99999999', $data->transactionId);
        self::assertSame('ORDER-1', $data->orderRef);
        self::assertSame('PUBLICTESTHUF', $data->merchant);
        self::assertSame(0, $data->responseCode);
    }

    public function testTheSignatureIsCheckedAgainstTheBase64String(): void
    {
        self::assertSame(ReturnEvent::Success, $this->client()->parseReturn(self::R, self::S)->event);
    }

    public function testAForgedSignatureIsRejected(): void
    {
        $this->expectException(SignatureException::class);

        $this->client()->parseReturn(self::R, 'aGFtaXM=');
    }

    public function testATamperedPayloadIsRejected(): void
    {
        $forged = base64_encode('{"r":0,"t":11111111,"e":"SUCCESS","m":"PUBLICTESTHUF","o":"ORDER-9"}');

        $this->expectException(SignatureException::class);

        $this->client()->parseReturn($forged, self::S);
    }

    public function testNonBase64InputIsRejected(): void
    {
        $r = '!!!nem base64!!!';
        $s = new Signature(self::SECRET)->sign($r);

        $this->expectException(UnexpectedResponseException::class);

        $this->client()->parseReturn($r, $s);
    }

    public function testAnUnknownEventIsLoud(): void
    {
        $r = base64_encode('{"r":0,"t":99999999,"e":"MAYBE","m":"PUBLICTESTHUF","o":"ORDER-1"}');
        $s = new Signature(self::SECRET)->sign($r);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('MAYBE');

        $this->client()->parseReturn($r, $s);
    }
}
```

- [ ] **Step 2: Futtasd, hogy lásd a bukást**

Run: `vendor/bin/phpunit tests/Unit/Response/ReturnDataTest.php`
Expected: FAIL — `Call to undefined method CodeConjure\SimplePay\Client::parseReturn()`.

- [ ] **Step 3: Írd meg a `ReturnData`-t**

`src/Response/ReturnData.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Response;

use CodeConjure\SimplePay\Internal\PayloadReader;
use CodeConjure\SimplePay\ReturnEvent;

/**
 * A fizetőoldalról a vásárló böngészőjén keresztül visszaérkező adat.
 *
 * Az aláírás miatt nem hamisítható, de attól még csak azt mondja meg, mit lát
 * a vásárló: tájékoztató, nem bizonyíték. A rendelés állapotát mindig a
 * lekérdezés vagy az értesítés dönti el.
 */
final readonly class ReturnData
{
    public function __construct(
        public ReturnEvent $event,
        public string $transactionId,
        public string $orderRef,
        public string $merchant,
        public int $responseCode,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            event: ReturnEvent::fromApi(PayloadReader::string($payload, 'e')),
            transactionId: PayloadReader::string($payload, 't'),
            orderRef: PayloadReader::string($payload, 'o'),
            merchant: PayloadReader::string($payload, 'm'),
            responseCode: PayloadReader::int($payload, 'r'),
        );
    }
}
```

- [ ] **Step 4: Bővítsd a `Client`-et**

A `use` blokkba:

```php
use CodeConjure\SimplePay\Response\ReturnData;
```

Az `ipn()` után:

```php
    public function parseReturn(string $r, string $s): ReturnData
    {
        if ('' === trim($s) || !$this->config->signature()->verify($r, $s)) {
            throw new SignatureException('A SimplePay visszatérési adat aláírása nem stimmel.');
        }

        $decodedJson = base64_decode($r, true);

        if (false === $decodedJson) {
            throw new UnexpectedResponseException('A SimplePay visszatérési adat nem base64.');
        }

        try {
            $decoded = json_decode($decodedJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new UnexpectedResponseException(
                'A SimplePay visszatérési adat nem értelmezhető JSON.',
                previous: $exception,
            );
        }

        if (!is_array($decoded)) {
            throw new UnexpectedResponseException('A SimplePay visszatérési adat nem objektum.');
        }

        /** @var array<string, mixed> $decoded */
        return ReturnData::fromPayload($decoded);
    }
```

- [ ] **Step 5: Futtasd újra**

Run: `vendor/bin/phpunit tests/Unit/Response/ReturnDataTest.php`
Expected: PASS, 6 teszt.

- [ ] **Step 6: Futtasd a teljes suite-ot és a statikus elemzést**

Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse -c phpstan.dist.neon && vendor/bin/ecs check`
Expected: minden zöld. Ha az ECS formázási eltérést jelez, futtasd `vendor/bin/ecs check --fix`-szel, majd ellenőrizd újra.

- [ ] **Step 7: Commit**

```bash
git add src/Response/ReturnData.php src/Client.php tests/Unit/Response/ReturnDataTest.php
git commit -m "feat: visszateresi adat alairas-ellenorzessel"
```

---

## Task 13: Sandbox kontraktus-tesztek és fixture-rögzítés

**Files:**
- Create: `tests/Sandbox/SandboxTestCase.php`, `tests/Sandbox/RecordingHttpClient.php`, `tests/Sandbox/StartContractTest.php`, `tests/Sandbox/QueryContractTest.php`, `tests/Sandbox/RefundContractTest.php`
- Create: `tests/Fixtures/sandbox/.gitkeep`
- Create (fix round, 2026-08-30): `tests/Unit/FixtureConformanceTest.php` — lásd az "Addition 2" jegyzetet lent

**Interfaces:**
- Consumes: a teljes publikus felület
- Produces: `tests/Fixtures/sandbox/start.json`, `query.json`, `refund_error.json` — DTO-összefoglalók, olvashatóság kedvéért; `raw_start.json`, `raw_query.json`, `raw_refund_error.json` — a nyers, dekódolatlan válasz-törzsek, ahogy a SimplePay ténylegesen elküldte őket. Csak az utóbbiak bizonyítanak bármit a valódi API-alakról — lásd a "Fix round 1" jegyzetet lent.

Ez az a task, ami a felmérés gyökérokát javítja: eddig egyik implementáció sem beszélt az igazi SimplePay sandboxszal, ezért a mockokba beleírt téves feltevések zöld tesztként jelentek meg.

**A `symfony/http-client` nem függősége a csomagnak**, ezért a sandbox tesztek a `php-http/curl-client`-et használják, amit dev függőségként adunk hozzá.

- [ ] **Step 1: Add hozzá a PSR-18 kliens dev függőséget**

Run: `composer require --dev php-http/curl-client:^2.3`
Expected: sikeres telepítés.

- [ ] **Step 1b (fix round 1, 2026-08-30 — utólag felvéve): Írd meg a rögzítő PSR-18 dekorátort**

A `Client` a válasz-törzset csak a saját DTO-in keresztül látja, tehát a `record()`-nak átadott DTO-összefoglalók a saját szerializálásunkat tükrözik vissza — egy renamelt kulcsot vagy megváltozott típust csak a `--group sandbox` futás pillanatában venne észre, a committolt fixture-ök soha többé. A `RecordingHttpClient` a valódi PSR-18 kliens elé ül és megőrzi a nyers válasz-törzs byte-jait, mielőtt bármi (Client, DTO) feldolgozná.

`tests/Sandbox/RecordingHttpClient.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Sandbox;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 dekorátor, ami a valódi kliens elé ül, kizárólag a sandbox
 * kontraktus-tesztek számára. Célja: a `Client` a válasz-törzset a saját
 * DTO-in keresztül olvassa, tehát semmi mást nem tudnánk elmondani arról,
 * mi jött ténylegesen a huzalon — ez a dekorátor megőrzi a nyers,
 * dekódolatlan válasz-törzs byte-jait, mielőtt bármi feldolgozná.
 *
 * A `Client` DTO-i és a fixture-be írt DTO-összefoglalók a saját
 * szerializálásunkat tükrözik vissza — hasznosak olvasáshoz, de nem
 * bizonyítanak semmit a valódi API-alakról. A `lastRawBody()` az, ami
 * bizonyíték: pontosan azok a byte-ok, amiket a SimplePay elküldött.
 */
final class RecordingHttpClient implements ClientInterface
{
    private ?string $lastRawBody = null;

    public function __construct(private readonly ClientInterface $inner)
    {
    }

    /** @throws ClientExceptionInterface */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->inner->sendRequest($request);
        $body = $response->getBody();

        $this->lastRawBody = (string) $body;

        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $response;
    }

    /**
     * A legutóbb kapott válasz nyers törzse, ahogy a SimplePay ténylegesen
     * elküldte — a Client/DTO rétegen még nem ment át.
     */
    public function lastRawBody(): ?string
    {
        return $this->lastRawBody;
    }
}
```

- [ ] **Step 2: Írd meg a közös ősosztályt**

`tests/Sandbox/SandboxTestCase.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Sandbox;

use CodeConjure\SimplePay\Client;
use CodeConjure\SimplePay\Config;
use CodeConjure\SimplePay\Environment;
use Http\Client\Curl\Client as CurlClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('sandbox')]
abstract class SandboxTestCase extends TestCase
{
    protected const string FIXTURE_DIR = __DIR__ . '/../Fixtures/sandbox';

    private ?RecordingHttpClient $recorder = null;

    protected function client(): Client
    {
        $merchant = getenv('SIMPLEPAY_SANDBOX_MERCHANT');
        $secret = getenv('SIMPLEPAY_SANDBOX_SECRET');

        if (!is_string($merchant) || !is_string($secret) || '' === $merchant || '' === $secret) {
            self::markTestSkipped('Nincs sandbox merchant vagy secret a környezetben.');
        }

        $factory = new Psr17Factory();
        $this->recorder = new RecordingHttpClient(new CurlClient($factory, $factory));

        return new Client(
            new Config($merchant, $secret, Environment::Sandbox),
            $this->recorder,
            $factory,
            $factory,
        );
    }

    /**
     * A legutóbb a sandboxtól kapott NYERS válasz-törzs, a Client/DTO
     * rétegen még át nem esve. Ez a fixture-ök bizonyító ereje: a
     * `record()`-nak átadott DTO-összefoglalók a saját szerializálásunkat
     * játsszák vissza, ez viszont a huzalon ténylegesen látott byte-okat.
     *
     * Csak azután hívható, hogy a `client()`-tel kapott klienssel legalább
     * egy hívás lezajlott — különben hangosan dob, nem ad vissza csendben
     * semmit.
     */
    protected function rawResponse(): string
    {
        if (null === $this->recorder) {
            throw new \LogicException(
                'rawResponse() a client() metódus előtt lett meghívva — nincs mit rögzíteni.',
            );
        }

        $raw = $this->recorder->lastRawBody();

        if (null === $raw) {
            throw new \LogicException(
                'rawResponse()-t hívtunk, de a client()-tel kapott kliensen keresztül még nem ment '
                . 'ki egyetlen hívás sem.',
            );
        }

        return $raw;
    }

    /**
     * A valódi választ fixture-ként rögzíti — DTO-összefoglalóként,
     * olvashatóság kedvéért. A bizonyító erejű rögzítéshez lásd
     * `recordRaw()`.
     *
     * A könyvtár létrehozása és a fájlírás sikerét explicit ellenőrizzük:
     * ha bármelyik csendben elhasalna, a korábbi fixture maradna a
     * lemezen, és a kontraktus-teszt zölden térne vissza úgy, hogy
     * valójában semmi nem lett rögzítve — pont azon az egy helyen, ahol a
     * fixture maga a bizonyíték.
     */
    protected function record(string $name, mixed $payload): void
    {
        self::ensureFixtureDirExists();

        $path = sprintf('%s/%s.json', self::FIXTURE_DIR, $name);
        $encoded = json_encode(
            $payload,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        ) . "\n";

        if (false === file_put_contents($path, $encoded)) {
            throw new \RuntimeException(sprintf('Nem sikerült a fixture-t kiírni: "%s".', $path));
        }
    }

    /**
     * A legutóbb kapott NYERS válasz-törzset rögzíti fixture-ként —
     * pontosan azt, amit a SimplePay a huzalon elküldött, a Client/DTO
     * rétegen való áthaladás előtt. Ez a fixture-fajta hordozza a
     * bizonyító erőt: a `FixtureConformanceTest` ezeken keresztül tudja
     * ellenőrizni, hogy a válasz-osztályaink a valódi API-alakot parsolják,
     * nem a saját korábbi szerializálásunkat.
     */
    protected function recordRaw(string $name): void
    {
        $decoded = json_decode($this->rawResponse(), true, 512, \JSON_THROW_ON_ERROR);

        $this->record($name, $decoded);
    }

    private static function ensureFixtureDirExists(): void
    {
        if (is_dir(self::FIXTURE_DIR)) {
            return;
        }

        if (!mkdir(self::FIXTURE_DIR, 0o775, true) && !is_dir(self::FIXTURE_DIR)) {
            throw new \RuntimeException(sprintf(
                'Nem sikerült létrehozni a fixture könyvtárat: "%s".',
                self::FIXTURE_DIR,
            ));
        }
    }

    protected function orderRef(): string
    {
        return sprintf('CONTRACT-%s-%s', date('Ymd-His'), bin2hex(random_bytes(3)));
    }
}
```

- [ ] **Step 3: Írd meg a `/start` kontraktus-tesztet**

`tests/Sandbox/StartContractTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Sandbox;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\Request\Invoice;
use CodeConjure\SimplePay\Request\StartRequest;
use CodeConjure\SimplePay\Request\Urls;
use PHPUnit\Framework\Attributes\Group;

#[Group('sandbox')]
final class StartContractTest extends SandboxTestCase
{
    public function testTheSandboxAcceptsOurSignatureAndReturnsAPaymentUrl(): void
    {
        $orderRef = $this->orderRef();

        $response = $this->client()->start(new StartRequest(
            orderRef: $orderRef,
            total: Money::fromMinorUnits(1000, Currency::HUF),
            customerEmail: 'contract-test@example.com',
            invoice: new Invoice('Teszt Elek', 'HU', 'Budapest', '1011', 'Fő utca 1.'),
            urls: new Urls(
                success: 'https://example.com/vissza?e=success',
                fail: 'https://example.com/vissza?e=fail',
                cancel: 'https://example.com/vissza?e=cancel',
                timeout: 'https://example.com/vissza?e=timeout',
            ),
        ));

        self::assertSame($orderRef, $response->orderRef);
        self::assertNotSame('', $response->transactionId);
        self::assertStringStartsWith('https://', $response->paymentUrl);
        self::assertSame(1000, $response->total->minorUnits);

        // A DTO-összefoglaló olvashatóság kedvéért marad, de a bizonyító erejű
        // fixture a nyers, dekódolatlan válasz-törzs — lásd recordRaw().
        $this->record('start', [
            'orderRef' => $response->orderRef,
            'transactionId' => $response->transactionId,
            'merchant' => $response->merchant,
            'paymentUrl' => $response->paymentUrl,
            'total' => $response->total->toApiValue(),
            'currency' => $response->total->currency->value,
            'timeout' => $response->timeout?->format(\DateTimeInterface::ATOM),
        ]);

        $this->recordRaw('raw_start');
    }
}
```

- [ ] **Step 4: Írd meg a `/query` kontraktus-tesztet**

`tests/Sandbox/QueryContractTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Sandbox;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\Request\Invoice;
use CodeConjure\SimplePay\Request\QueryRequest;
use CodeConjure\SimplePay\Request\StartRequest;
use CodeConjure\SimplePay\Request\Urls;
use CodeConjure\SimplePay\TransactionStatus;
use PHPUnit\Framework\Attributes\Group;

#[Group('sandbox')]
final class QueryContractTest extends SandboxTestCase
{
    public function testAFreshTransactionCanBeQueriedBackByOrderRef(): void
    {
        $client = $this->client();
        $orderRef = $this->orderRef();

        $client->start(new StartRequest(
            orderRef: $orderRef,
            total: Money::fromMinorUnits(1000, Currency::HUF),
            customerEmail: 'contract-test@example.com',
            invoice: new Invoice('Teszt Elek', 'HU', 'Budapest', '1011', 'Fő utca 1.'),
            urls: new Urls(
                success: 'https://example.com/vissza?e=success',
                fail: 'https://example.com/vissza?e=fail',
                cancel: 'https://example.com/vissza?e=cancel',
                timeout: 'https://example.com/vissza?e=timeout',
            ),
        ));

        $response = $client->query(new QueryRequest(orderRefs: [$orderRef]));

        self::assertGreaterThanOrEqual(1, $response->totalCount);

        $transaction = $response->byOrderRef($orderRef);
        self::assertNotNull($transaction, 'A lekérdezés a transactions tömbben adja vissza a tranzakciót.');
        self::assertInstanceOf(TransactionStatus::class, $transaction->status);

        // A DTO-összefoglaló olvashatóság kedvéért marad, de a bizonyító erejű
        // fixture a nyers, dekódolatlan válasz-törzs — lásd recordRaw().
        $this->record('query', [
            'totalCount' => $response->totalCount,
            'transactions' => array_map(
                static fn ($item): array => [
                    'merchant' => $item->merchant,
                    'orderRef' => $item->orderRef,
                    'transactionId' => $item->transactionId,
                    'status' => $item->status->value,
                    'resultCode' => $item->resultCode,
                    'total' => $item->total?->toApiValue(),
                    'remainingTotal' => $item->remainingTotal?->toApiValue(),
                    'currency' => $item->total?->currency->value ?? $item->remainingTotal?->currency->value,
                    'method' => $item->method?->value,
                    'paymentDate' => $item->paymentDate?->format(\DateTimeInterface::ATOM),
                    'finishDate' => $item->finishDate?->format(\DateTimeInterface::ATOM),
                ],
                $response->transactions,
            ),
        ]);

        $this->recordRaw('raw_query');
    }
}
```

- [ ] **Step 5: Írd meg a `/refund` kontraktus-tesztet**

`tests/Sandbox/RefundContractTest.php`:

```php
<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Sandbox;

use CodeConjure\SimplePay\Currency;
use CodeConjure\SimplePay\Exception\RequestException;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\Request\RefundRequest;
use PHPUnit\Framework\Attributes\Group;

#[Group('sandbox')]
final class RefundContractTest extends SandboxTestCase
{
    /**
     * Jóváírni csak befejezett fizetést lehet, azt viszont a tesztsuite nem tud
     * előállítani — a fizetőoldalon kattintás kell hozzá. Ezért a szerződés,
     * amit itt rögzítünk, az elutasítás alakja: a SimplePay hibakóddal válaszol,
     * és a hibakód eljut hozzánk. Ez ellenőrzi a refund végpont elérhetőségét,
     * az aláírásunk elfogadását és a hibakód-kicsomagolást.
     */
    public function testRefundingAnUnknownTransactionYieldsAReadableError(): void
    {
        try {
            $this->client()->refund(new RefundRequest(
                refundTotal: Money::fromMinorUnits(100, Currency::HUF),
                orderRef: 'NEM-LETEZO-' . bin2hex(random_bytes(4)),
            ));

            self::fail('Nem létező rendelésre jóváírást vártunk elutasítva.');
        } catch (RequestException $exception) {
            self::assertNotSame([], $exception->codes());

            // A DTO-összefoglaló olvashatóság kedvéért marad, de a bizonyító
            // erejű fixture a nyers, dekódolatlan válasz-törzs — lásd
            // recordRaw(). Ez a szó szerinti "errorCodes" kulcsot hordozza,
            // nem a mi kényelmi "codes" nevünket.
            $this->record('refund_error', [
                'codes' => $exception->codes(),
                'message' => $exception->getMessage(),
            ]);

            $this->recordRaw('raw_refund_error');
        }
    }
}
```

- [ ] **Step 6: Hozd létre a fixture könyvtárat**

Run: `mkdir -p tests/Fixtures/sandbox && touch tests/Fixtures/sandbox/.gitkeep`

- [ ] **Step 7: Ellenőrizd, hogy a sandbox csoport alapból nem fut**

Run: `vendor/bin/phpunit`
Expected: PASS, és a kimenetben **nem** szerepel a `StartContractTest`. Ha mégis fut, a `phpunit.xml.dist` `<groups><exclude>` blokkja hibás.

- [ ] **Step 8: Futtasd a sandbox csoportot**

Run: `vendor/bin/phpunit --group sandbox`
Expected: PASS.

Ha az aláírás miatt bukik (`errorCodes` az 5xxx tartományban), az azt jelenti, hogy a `phpunit.xml.dist`-ben szereplő publikus teszt-merchant adatai már nem érvényesek. Ilyenkor szerezz aktuális sandbox hozzáférést a SimplePay dokumentációjából, írd be a `phpunit.xml.dist`-be, és futtasd újra. **Ne** módosítsd az aláírás algoritmusát emiatt — a SHA-384-et a Task 3 vektorai rögzítik.

Ha a `TransactionStatus::fromApi()` dob ismeretlen státusszal, az **valódi eredmény**: vedd fel az enumba, egészítsd ki a `TransactionStatusTest::finality()` adatszolgáltatóját, és írd be a README feature mátrixa alá.

- [ ] **Step 9: Nézd meg, mit rögzítettek a tesztek**

Run: `cat tests/Fixtures/sandbox/*.json`
Expected: valódi sandbox válaszok. Vesd össze a Task 8 unit tesztjeinek fixture-jeivel: ha a mezőnevek vagy típusok eltérnek, **a unit teszteket igazítsd a rögzített valósághoz**, nem fordítva. Ez a task lényege.

- [ ] **Step 10: Commit**

```bash
git add composer.json composer.lock tests/Sandbox tests/Fixtures
git commit -m "test: sandbox kontraktus-tesztek es fixture-rogzites"
```

**Fix round 1 (2026-08-30, review után) — a fixture-ök nem voltak bizonyíték.** A `record()` a DTO-inkat írta vissza (`$response->total->toApiValue()` stb.), tehát a `FixtureConformanceTest` a saját szerializálásunkat parsolta vissza — tautológia. Javítva: `RecordingHttpClient` (Step 1b) a PSR-18 réteg alatt megőrzi a nyers válasz-törzset; `SandboxTestCase::recordRaw()` ezt írja ki `raw_start.json`/`raw_query.json`/`raw_refund_error.json` néven; a `FixtureConformanceTest` immár ezeken keresztül is ellenőrzi a válasz-osztályokat, data providerrel (egy rossz fixture nem rejti el a többit). A `record()` mkdir/file_put_contents hívásai mostantól hangosan dobnak siker helyett csendes visszatérés esetén. A `QueryRequest::$detailed`/`$refunds` eltávolítása a feature mátrixban félrevezetően "kihagyva"-ként szerepelt, holott a `detailed:true` mindig kimegy (csak a válasza modellezetlen) — a spec 15. fejezete pontosítva. A `Signature` mostantól trim()-eli a secretKey-t aláírás előtt, a hivatalos SimplePay PHP SDK (2.1.5, 2026-06-27) `getSignature()`-jével összhangban. Lásd a Task 13 jelentést a teljes indoklásért.

---

## Task 14: README és CI

**Files:**
- Create: `README.md`
- Create: `.github/workflows/ci.yaml`, `.github/workflows/sandbox.yaml`

**Interfaces:**
- Consumes: a teljes elkészült felület
- Produces: nincs kód-felület

- [ ] **Step 1: Írd meg a `README.md`-t**

```markdown
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

## Ismert bizonytalanságok

- **A `receiveDate` pontos formátuma.** Jelenleg ISO 8601 (`DateTimeInterface::ATOM`). Az IPN-hez a SimplePay hív minket, ahhoz kívülről elérhető URL kell, ezért ezt a csomag tesztsuite-ja nem tudja végigmérni. A formátumot az első valódi sandbox-fizetés IPN-je hitelesíti, a Payum- és Sylius-réteg üzembe helyezésekor.

## Tesztelés

```bash
vendor/bin/phpunit                 # gyors unit tesztek, hálózat nélkül
vendor/bin/phpunit --group sandbox # valódi SimplePay sandbox ellen
vendor/bin/phpstan analyse -c phpstan.dist.neon
vendor/bin/ecs check
```

A sandbox tesztek a valódi válaszokat `tests/Fixtures/sandbox/` alá írják, és a unit tesztek ezeket játsszák vissza. A mockok tehát rögzített valóságot tükröznek, nem feltevéseket.

## Licenc

MIT
```

- [ ] **Step 2: Írd meg a `ci.yaml`-t**

`.github/workflows/ci.yaml`:

```yaml
name: CI

on:
    push: ~
    pull_request: ~
    workflow_dispatch: ~

permissions:
    contents: read

jobs:
    checks:
        name: "Tesztek és statikus ellenőrzés (PHP ${{ matrix.php }})"
        runs-on: ubuntu-latest
        strategy:
            fail-fast: false
            matrix:
                php: ['8.4', '8.5']
        steps:
            - uses: actions/checkout@v4

            - uses: shivammathur/setup-php@v2
              with:
                  php-version: ${{ matrix.php }}
                  coverage: none

            - uses: ramsey/composer-install@v3

            - name: Unit tesztek
              run: vendor/bin/phpunit

            - name: PHPStan
              run: vendor/bin/phpstan analyse -c phpstan.dist.neon --no-progress

            - name: ECS
              run: vendor/bin/ecs check --no-progress-bar
```

- [ ] **Step 3: Írd meg a `sandbox.yaml`-t**

`.github/workflows/sandbox.yaml`:

```yaml
name: Sandbox kontraktus

on:
    schedule:
        - cron: '0 3 * * *'
    workflow_dispatch: ~

permissions:
    contents: read

jobs:
    contract:
        name: "SimplePay sandbox kontraktus-tesztek"
        runs-on: ubuntu-latest
        steps:
            - uses: actions/checkout@v4

            - uses: shivammathur/setup-php@v2
              with:
                  php-version: '8.4'
                  coverage: none

            - uses: ramsey/composer-install@v3

            - name: Kontraktus-tesztek a valódi sandbox ellen
              run: vendor/bin/phpunit --group sandbox

            - name: A rögzített fixture-ök eltérésének kimutatása
              if: always()
              run: git --no-pager diff --stat -- tests/Fixtures/sandbox
```

- [ ] **Step 4: Ellenőrizd, hogy minden zöld**

Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse -c phpstan.dist.neon && vendor/bin/ecs check`
Expected: minden zöld.

- [ ] **Step 5: Ellenőrizd a tiltott függőségeket**

Run: `grep -E '"(payum|sylius|symfony)/' composer.json`
Expected: nincs találat a `require` és `require-dev` blokkban. (A `suggest` blokkban szereplő `symfony/http-client` rendben van, az nem függőség.)

- [ ] **Step 6: Commit**

```bash
git add README.md .github
git commit -m "docs: README feature matrixszal es ismert bizonytalansagokkal, CI workflowk"
```

---

## Önellenőrzés a spec ellen

A terv megírása után visszanézve a specre:

**Spec-lefedettség.** Minden spec-fejezethez tartozik task: 4. követelmények → Task 1; 5. fájlstruktúra → mind; 6. hatókör → Task 7–12; 7. publikus felület → Task 1, 4, 5, 6, 7, 8; 8. aláírás és HTTP → Task 3, 9; 9. IPN → Task 11; 10. visszatérés → Task 12; 11. hibakezelés → Task 2; 12. státusz-enum → Task 6; 13. tesztstratégia → Task 13; 14. CI → Task 14; 15. feature mátrix → Task 14; 16. ismert bizonytalanságok → Task 11, 14; 17. elfogadási kritériumok → Task 13, 14 utolsó lépései.

**Eltérések a spectől, tudatosan:**

1. A spec nem sorolta fel az `Internal\PayloadReader`-t. Bevezettük, mert a „hiányzó mező esetén hangos hiba" követelmény máshogy minden DTO-ban külön megismétlődne.
2. A `Language` enum három nyelvre szűkült (`HU`, `EN`, `DE`). A README mátrixában külön sor jelzi, mi nincs benne.
3. A `StartResponse::$salt` és `$timeout` opcionális lett, mert a `/start` válasz mezőkészletét csak a Task 13 kontraktus-tesztje hitelesíti.
