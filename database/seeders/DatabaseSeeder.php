<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Ledger;
use App\Services\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! config('riko.sandbox')) {
            throw new \RuntimeException('Demo seeding is local sandbox only.');
        }
        if (User::exists()) {
            return;
        }
        $password = bin2hex(random_bytes(10)).'Aa1!';
        foreach ([['Alex Morgan', 'demo@rikowallet.test', '5826149037', 'user'], ['Sara Chen', 'sara@rikowallet.test', '7391026485', 'user'], ['Riko Admin', 'admin@rikowallet.test', '9012345678', 'admin']] as [$name,$email,$wallet,$role]) {
            $u = new User;
            $u->name = $name;
            $u->email = $email;
            $u->wallet_id = $wallet;
            $u->password = $password;
            $u->country = 'IN';
            $u->role = $role;
            $u->kyc_status = 'approved';
            $u->kyc_expires_at = now()->addYear();
            $u->email_verified_at = now();
            $u->save();
        }
        file_put_contents(storage_path('app/local-demo-credentials.txt'), "LOCAL SANDBOX ONLY\nUser: demo@rikowallet.test\nAdmin: admin@rikowallet.test\nPassword: ".$password."\n");
        $l = app(Ledger::class);
        $settings = app(Settings::class);
        $settings->put('policy', $settings->policy());
        $settings->put('prices', ['USD' => '1', 'INR' => '0.012', 'EUR' => '1.08', 'BTC' => '65000', 'TRX' => '0.12', 'USDT' => '1']);
        foreach (['USD' => '1250', 'INR' => '18500', 'EUR' => '320', 'BTC' => '0.0034', 'TRX' => '250', 'USDT' => '145.5', 'RC' => '2500'] as $asset => $amount) {
            $l->post('demo:user:'.$asset, 'sandbox_funding', 1, $asset, $amount, [$l->account('sandbox:source', $asset) => bcsub('0', $amount, 8), $l->account('user:1', $asset, 1) => $amount]);
        }
        foreach (['USD' => '10000', 'INR' => '100000', 'EUR' => '10000', 'BTC' => '1', 'TRX' => '10000', 'USDT' => '10000'] as $asset => $amount) {
            $l->post('demo:inventory:'.$asset, 'sandbox_funding', null, $asset, $amount, [$l->account('sandbox:source', $asset) => bcsub('0', $amount, 8), $l->account('company:inventory', $asset) => $amount]);
        }
        foreach ([['company:rewards', 'RC', '1000000'], ['company:redemption', 'USD', '10000'], ['company:adjustments', 'USD', '1000']] as [$owner,$asset,$amount]) {
            $l->post('demo:'.$owner, 'sandbox_funding', null, $asset, $amount, [$l->account('sandbox:source', $asset) => bcsub('0', $amount, 8), $l->account($owner, $asset) => $amount]);
        }
        foreach ([['UPI transfer', 'deposit', ['IN'], ['INR']], ['Bank transfer', 'deposit', ['IN', 'US', 'GB'], ['INR', 'USD']], ['Crypto network', 'deposit', ['IN', 'US', 'GB', 'BD', 'AE'], ['BTC', 'TRX', 'USDT']], ['Bank withdrawal', 'withdraw', ['IN', 'US'], ['INR', 'USD']], ['Crypto wallet', 'withdraw', ['IN', 'US', 'GB', 'BD', 'AE'], ['BTC', 'TRX', 'USDT']]] as [$name,$dir,$countries,$assets]) {
            DB::table('payment_methods')->insert(['name' => $name, 'direction' => $dir, 'countries' => json_encode($countries), 'assets' => json_encode($assets), 'enabled' => true, 'provider_ready' => false, 'instructions' => 'Provider setup pending. Never send real funds to sandbox.', 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([['Discover a new app', 'app_surf', '100'], ['Explore a partner website', 'web_surf', '50'], ['Try a new experience', 'app_install', '200'], ['Share your perspective', 'survey', '150']] as [$title,$category,$reward]) {
            DB::table('tasks')->insert(['title' => $title, 'category' => $category, 'instructions' => '1. Connect your earning code. 2. Follow the partner instructions. 3. Wait for server verification.', 'package_name' => 'com.partner.demo', 'url' => 'https://example.com', 'countries' => json_encode(['IN', 'US', 'GB', 'BD', 'AE', 'DE', 'SG']), 'devices' => json_encode(['android', 'ios', 'desktop']), 'channels' => json_encode(['web', 'app']), 'reward' => $reward, 'budget' => '10000', 'enabled' => true, 'provider_ready' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
