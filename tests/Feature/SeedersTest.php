<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use App\Models\Setting;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_seeds_the_three_roles(): void
    {
        $roles = Role::pluck('name')->toArray();

        $this->assertContains('admin', $roles);
        $this->assertContains('editor', $roles);
        $this->assertContains('viewer', $roles);
    }

    public function test_seeds_default_settings(): void
    {
        $this->assertTrue(Setting::where('key', 'site_name')->exists());
    }
}
