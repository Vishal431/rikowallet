<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_admin_without_demo_funds_or_kyc_bypass(): void
    {
        $password = 'Test-only-Admin!4920';
        $this->artisan('riko:admin-create', ['email' => 'owner@example.test'])
            ->expectsQuestion('New admin password (16+ characters, mixed case, number and symbol)', $password)
            ->expectsQuestion('Confirm password', $password)->assertSuccessful();
        $user = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSame('not_started', $user->kyc_status);
        $this->assertDatabaseCount('operations', 0);
        $this->artisan('riko:admin-create', ['email' => 'owner@example.test'])->assertFailed();
    }

    public function test_rejects_mismatched_password(): void
    {
        $this->artisan('riko:admin-create', ['email' => 'owner@example.test'])
            ->expectsQuestion('New admin password (16+ characters, mixed case, number and symbol)', 'Test-only-Admin!4920')
            ->expectsQuestion('Confirm password', 'Different-password!4920')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
