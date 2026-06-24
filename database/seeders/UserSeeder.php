<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@portfolio.test'],
            ['name' => 'Admin', 'email_verified_at' => now()]
        );
        $admin->assignRole('admin');
    }
}
