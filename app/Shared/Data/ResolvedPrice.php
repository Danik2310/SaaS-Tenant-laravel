<?php

declare(strict_types=1);

namespace App\Shared\Data;

readonly class ResolvedPrice
{
    public function __construct(
        public string $amount,
        public string $currency,
        public string $source,
        public string $formatted,
    ) {}

    /**
     * @return array{amount: string, currency: string, source: string, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'source' => $this->source,
            'formatted' => $this->formatted,
        ];
    }
}
