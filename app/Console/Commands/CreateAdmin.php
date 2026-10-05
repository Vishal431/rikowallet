<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'riko:admin-create {email} {--name=Riko Admin} {--country=IN}';

    protected $description = 'Create a new administrator with a hidden password prompt (no demo funds or KYC approval)';

    public function handle(): int
    {
        $data = ['email' => strtolower(trim($this->argument('email'))), 'name' => $this->option('name'), 'country' => strtoupper($this->option('country'))];
        $validator = Validator::make($data, ['email' => 'required|email|max:255|unique:users,email', 'name' => 'required|string|max:255', 'country' => 'required|in:'.implode(',', array_keys(config('riko.countries')))]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('Use an interactive terminal to enter the password securely.');

            return self::FAILURE;
        }
        $password = $this->secret('New admin password (16+ characters, mixed case, number and symbol)');
        $confirmation = $this->secret('Confirm password');
        $validator = Validator::make(['password' => $password, 'password_confirmation' => $confirmation], ['password' => ['required', 'confirmed', Password::min(16)->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        do {
            $wallet = (string) random_int(1000000000, 9999999999);
        } while (User::where('wallet_id', $wallet)->exists());
        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->country = $data['country'];
        $user->wallet_id = $wallet;
        $user->password = $password;
        $user->role = 'admin';
        $user->save();
        $this->info('Administrator created: '.$user->email.' — sign in at /login then open /admin.');

        return self::SUCCESS;
    }
}
