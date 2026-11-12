<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Artisan::command('spa:owner {username=owner}', function (string $username): int {
    $existing = DB::table('users')->where('username', $username)->first();

    if ($existing && ($existing->role !== 'owner' || $existing->deleted_at !== null)) {
        $this->error('Username tersebut sudah digunakan oleh akun lain.');
        return 1;
    }

    if ($existing && !$this->confirm('Atur ulang password owner yang sudah ada?')) {
        return 1;
    }

    $email = $existing->email ?? $this->ask('Email owner');
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/D', $username)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 100
        || (!$existing && DB::table('users')->where('email', $email)->exists())) {
        $this->error('Username atau email tidak valid atau sudah digunakan.');
        return 1;
    }

    $password = $this->secret('Password baru (minimal 8 karakter)');
    $confirmation = $this->secret('Ulangi password');
    if (!$password || strlen($password) < 8 || strlen($password) > 72 || $password !== $confirmation) {
        $this->error('Password harus berisi 8–72 byte dan cocok dengan konfirmasi.');
        return 1;
    }

    $data = ['password' => Hash::make($password), 'updated_at' => now()];
    if ($existing) {
        DB::table('users')->where('id', $existing->id)->update($data);
    } else {
        DB::table('users')->insert($data + [
            'username' => $username,
            'email' => $email,
            'full_name' => 'Spa Owner',
            'role' => 'owner',
            'is_active' => 1,
            'created_at' => now(),
        ]);
    }

    $this->info("Password akun $username sudah disimpan.");
    return 0;
})->purpose('Buat akun owner atau atur ulang password owner');
