<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Request;

/**
 * Mind az öt cím kötelező. Az `ipn` a SimplePay felé `dn` néven megy ki — ez az
 * a cím, ahová a fizetési értesítés érkezik; enélkül a bolt sosem értesül a
 * fizetés véglegesüléséről.
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
