<?php

namespace App\Payments;

final readonly class ProviderResult
{
    public function __construct(
        public string $externalId,
        public string $status,
        public string $asset,
        public string $amount,
        public ?string $network,
        public string $merchantReference,
        public array $instructions = [],
    ) {
        if (! in_array($status, ['pending', 'completed', 'failed']) || $externalId === '' || strlen($externalId) > 191) {
            throw new \InvalidArgumentException('Invalid normalized provider response.');
        }
    }
}
