<?php

namespace App\Payments;

use Illuminate\Validation\ValidationException;

class ProviderRegistry
{
    public function get(?string $name): PaymentProvider
    {
        $provider = $name ? config('payments.providers.'.$name) : null;
        if (! is_array($provider) || ! ($provider['enabled'] ?? false) || ! isset($provider['driver'])) {
            throw ValidationException::withMessages(['provider' => 'This payment provider is not connected. No new funds were moved.']);
        }
        $driver = app($provider['driver']);
        if (! $driver instanceof PaymentProvider) {
            throw new \LogicException('Invalid payment driver.');
        }

        return $driver;
    }
}
