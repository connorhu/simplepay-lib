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
            'url' => $this->urls->toPayload(),
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
