<?php

namespace Tests\Feature;

use App\Payments\ProviderRegistry;
use App\Payments\ProviderResult;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProviderBoundaryTest extends TestCase
{
    public function test_unconfigured_provider_cannot_be_resolved(): void
    {
        $this->expectException(ValidationException::class);
        app(ProviderRegistry::class)->get('unapproved');
    }

    public function test_unknown_provider_status_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProviderResult('external-id', 'anything', 'USD', '10', null, 'merchant-reference');
    }
}
