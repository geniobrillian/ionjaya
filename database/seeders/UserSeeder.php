<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun Super Admin (Pemilik Usaha)
        User::create([
            'username' => 'superadmin',
            'name' => 'Pemilik Toko',
            'password' => Hash::make('superadmin123'),
            'role' => 'super_admin',
        ]);

        // Akun Admin (Kasir)
        User::create([
            'username' => 'admin',
            'name' => 'Kasir Toko',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);
    }
}
