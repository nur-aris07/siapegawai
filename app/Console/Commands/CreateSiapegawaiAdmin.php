<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CreateSiapegawaiAdmin extends Command
{
    protected $signature = 'siapegawai:create-admin {--superadmin : Create initial superadmin (CLI only)}';
    protected $description = 'Create an administrator from a trusted console';

    public function handle(): int
    {
        $name = (string) $this->ask('Nama admin');
        $email = mb_strtolower(trim((string) $this->ask('Email admin')));
        $password = (string) $this->secret('Password admin (min 12 chars)');
        $rules = validator(['name' => $name, 'email' => $email, 'password' => $password], [
            'name' => ['required', 'min:2'], 'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(12)->letters()->numbers()],
        ]);
        if ($rules->fails()) { $this->error($rules->errors()->first()); return self::FAILURE; }
        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = $this->option('superadmin') ? 'superadmin' : 'operational_admin';
        $user->status = 'active';
        $user->activated_at = now();
        $user->email_verified_at = now(); // trusted console provisioning, not public account
        $user->save();
        $this->info('Akun '. $user->role .' dibuat. Gunakan kata sandi kuat dan kelola keamanan akun melalui panel admin.');
        return self::SUCCESS;
    }
}
