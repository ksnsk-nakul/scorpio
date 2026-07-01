<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        if (Page::exists()) {
            return;
        }

        $adminEmail = filled(env('ADMIN_EMAIL')) ? env('ADMIN_EMAIL') : 'admin@example.com';
        $adminId    = \App\Models\User::where('email', $adminEmail)->value('id');
        abort_if(is_null($adminId), 1, "Admin user not found for [{$adminEmail}] — run UserSeeder first.");

        // Create a blank draft home page — the admin fills in their own content
        Page::create([
            'name'     => 'Home',
            'slug'     => 'home',
            'user_id'  => $adminId,
            'is_home'  => true,
            'template' => 'hero_cards',
            'status'   => 'draft',
            'blocks'   => [],
        ]);
    }
}
