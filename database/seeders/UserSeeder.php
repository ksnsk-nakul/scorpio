<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email    = filled(env('ADMIN_EMAIL'))    ? env('ADMIN_EMAIL')    : 'admin@example.com';
        $name     = filled(env('ADMIN_NAME'))     ? env('ADMIN_NAME')     : 'Nakul Sri Kuber';
        $password = filled(env('ADMIN_PASSWORD')) ? env('ADMIN_PASSWORD') : 'password';

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'password'          => \Illuminate\Support\Facades\Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Sync site branding on every seed run
        $admin->fill([
            'site_name' => $admin->site_name ?: 'KSNSK',
            'og_image'  => $admin->og_image  ?: '/images/profile.png',
        ])->save();

        if (empty($admin->username)) {
            $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
            $admin->update(['username' => \App\Models\User::uniqueUsername($base)]);
        }
    }
}
