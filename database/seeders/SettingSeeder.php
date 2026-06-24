<?php
namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'site_name',        'value' => 'Portfolio',         'group' => 'general'],
            ['key' => 'site_tagline',      'value' => 'Full-Stack Dev',    'group' => 'general'],
            ['key' => 'meta_description',  'value' => '',                  'group' => 'seo'],
            ['key' => 'og_image',          'value' => '',                  'group' => 'seo'],
            ['key' => 'media_max_size_mb', 'value' => '50',               'group' => 'general'],
        ];

        foreach ($defaults as $row) {
            Setting::firstOrCreate(['key' => $row['key']], $row);
        }
    }
}
