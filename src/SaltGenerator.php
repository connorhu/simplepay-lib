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
