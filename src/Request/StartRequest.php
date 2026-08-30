<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

use CodeConjure\SimplePay\Exception\ConfigurationException;
use CodeConjure\SimplePay\Language;
use CodeConjure\SimplePay\Money;
use CodeConjure\SimplePay\PaymentMethod;

final readonly class StartRequest
{
    /** @var non-empty-list<PaymentMethod> */
    public array $methods;

    /**
     * A `$methods` konstruktor-paraméter szándékosan csak `list<PaymentMethod>`,
     * nem `non-empty-list` — az üres tömb egy futásidejű ellenőrzéssel dobódik
     * el lent, nem a típusrendszer szintjén. A property-n (fent) a
     * `non-empty-list` marad, mert az már egy validált, garantált állapotot ír
     * le; a paraméteren viszont ez a garancia még nem áll fenn, és egy
     * `non-empty-list` docblock itt elfedné a lenti ellenőrzést (PHPStan a
     * docblock-típust venné készpénznek, és holt kódnak jelezné az `[] ===
     * $methods` ágat) — pont azt a hibát ismételné meg, amit ez a javítás
     * orvosol: egy dokumentált, de sosem kikényszerített garanciát.
     *
     * @param list<PaymentMethod> $methods
     */
    public function __construct(
        public string $orderRef,
        public Money $total,
        public string $customerEmail,
        public Invoice $invoice,
        public Urls $urls,
        public Language $language = Language::Hu,
        array $methods = [PaymentMethod::Card],
        public ?\DateTimeImmutable $timeout = null,
        public ?string $customer = null,
    ) {
        foreach ([
            'orderRef' => $orderRef,
            'customerEmail' => $customerEmail,
        ] as $field => $value) {
            if ('' === $value) {
                throw new ConfigurationException(sprintf(
                    'A StartRequest "%s" mezője nem lehet üres.',
                    $field,
                ));
            }
        }

        if ([] === $methods) {
            throw new ConfigurationException(
                'A StartRequest "methods" mezője nem lehet üres — legalább egy fizetési módot meg kell adni.',
            );
        }

        $this->methods = $methods;
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
