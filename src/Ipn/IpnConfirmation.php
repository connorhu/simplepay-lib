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
