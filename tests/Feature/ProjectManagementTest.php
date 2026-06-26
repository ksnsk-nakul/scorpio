<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('creates a workspace', function () {
    $this->actingAs($this->admin)
        ->post('/admin/workspaces', ['name' => 'Open Source'])
        ->assertRedirect();
    expect(Workspace::where('name', 'Open Source')->exists())->toBeTrue();
});

it('creates a project inside a workspace', function () {
    $ws = Workspace::create(['name' => 'Personal', 'slug' => 'personal']);
    $this->actingAs($this->admin)
        ->post('/admin/projects', ['workspace_id' => $ws->id, 'name' => 'Portfolio'])
        ->assertRedirect();
    expect(Project::where('name', 'Portfolio')->exists())->toBeTrue();
});

it('links a github repo to a project', function () {
    $ws = Workspace::create(['name' => 'Test', 'slug' => 'test-ws']);
    $p  = Project::create(['workspace_id' => $ws->id, 'name' => 'TestProject', 'slug' => 'test-proj']);
    $this->actingAs($this->admin)
        ->patch("/admin/projects/{$p->id}", ['github_repo' => 'nakul/portfolio', 'name' => 'TestProject'])
        ->assertRedirect();
    expect($p->fresh()->github_repo)->toBe('nakul/portfolio');
});
