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
