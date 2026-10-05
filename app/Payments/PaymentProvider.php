<?php

namespace App\Payments;

interface PaymentProvider
{
    /** Check supported country, currency, network and beneficiary BEFORE reserving funds. */
    public function validate(object $payment, string $country): void;

    /** Must pass payment.id as the provider idempotency / merchant reference key. */
    public function create(object $payment): ProviderResult;

    /** Authenticated API lookup, never a client-provided status. */
    public function fetch(object $payment): ProviderResult;

    /** Verify native provider signature over the raw request; return external payment ID only. */
    public function verifyWebhook(string $body, array $headers): string;
}
