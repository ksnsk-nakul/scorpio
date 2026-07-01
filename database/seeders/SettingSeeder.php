<?php
namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $adminName  = filled(env('ADMIN_NAME'))  ? env('ADMIN_NAME')  : 'Admin';
        $adminEmail = filled(env('ADMIN_EMAIL')) ? env('ADMIN_EMAIL') : 'admin@example.com';

        $defaults = [
            ['key' => 'site_name',         'value' => $adminName,  'group' => 'general'],
            ['key' => 'site_tagline',      'value' => '',          'group' => 'general'],
            ['key' => 'meta_description',  'value' => '',          'group' => 'seo'],
            ['key' => 'og_image',          'value' => '',          'group' => 'seo'],
            ['key' => 'media_max_size_mb', 'value' => '50',        'group' => 'general'],

            // Social — left blank; fill in from Profile or Settings
            ['key' => 'social_github',   'value' => '', 'group' => 'social'],
            ['key' => 'social_linkedin', 'value' => '', 'group' => 'social'],
            ['key' => 'social_twitter',  'value' => '', 'group' => 'social'],

            // Mail — seeded from env so mail works out of the box
            ['key' => 'mail_from_name',    'value' => $adminName,  'group' => 'mail'],
            ['key' => 'mail_from_address', 'value' => $adminEmail, 'group' => 'mail'],
            ['key' => 'mail_reply_to',     'value' => $adminEmail, 'group' => 'mail'],
        ];

        foreach ($defaults as $row) {
            Setting::firstOrCreate(['key' => $row['key']], $row);
        }
    }
}
