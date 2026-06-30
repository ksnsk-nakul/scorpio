<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $ws = Workspace::create(['name' => 'WS', 'slug' => 'ws', 'user_id' => $this->admin->id]);
    $this->project = Project::create(['workspace_id' => $ws->id, 'name' => 'Proj', 'slug' => 'proj']);
});

it('creates a task', function () {
    $this->actingAs($this->admin)
        ->post('/admin/tasks', [
            'project_id' => $this->project->id,
            'title'      => 'Fix bug',
            'status'     => 'open',
            'priority'   => 'high',
        ])
        ->assertRedirect();
    expect(Task::where('title', 'Fix bug')->exists())->toBeTrue();
});

it('creates a subtask under a parent', function () {
    $parent = Task::create(['project_id' => $this->project->id, 'title' => 'Parent', 'status' => 'open', 'priority' => 'medium']);
    $this->actingAs($this->admin)
        ->post('/admin/tasks', [
            'project_id' => $this->project->id,
            'parent_id'  => $parent->id,
            'title'      => 'Subtask',
            'status'     => 'open',
            'priority'   => 'low',
        ])
        ->assertRedirect();
    expect($parent->subtasks()->count())->toBe(1);
});

it('adds a comment to a task', function () {
    $task = Task::create(['project_id' => $this->project->id, 'title' => 'Task', 'status' => 'open', 'priority' => 'low']);
    $this->actingAs($this->admin)
        ->post("/admin/tasks/{$task->id}/comments", ['body' => 'Good progress'])
        ->assertRedirect();
    expect($task->comments()->count())->toBe(1);
});

it('updates task status via the kanban endpoint and logs activity', function () {
    $task = Task::create(['project_id' => $this->project->id, 'title' => 'Task', 'status' => 'open', 'priority' => 'low']);

    $this->actingAs($this->admin)
        ->patch("/admin/tasks/{$task->id}/status", ['status' => 'done'])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('done');
    expect($task->activities()->where('field', 'status')->where('to', 'done')->exists())->toBeTrue();
});

it('rejects status updates for tasks belonging to another tenant', function () {
    $other  = User::factory()->create();
    $other->assignRole('editor');
    $otherWs = Workspace::create(['name' => 'Other', 'slug' => 'other', 'user_id' => $other->id]);
    $otherProject = Project::create(['workspace_id' => $otherWs->id, 'name' => 'OtherProj', 'slug' => 'other-proj']);
    $task = Task::create(['project_id' => $otherProject->id, 'title' => 'Not yours', 'status' => 'open', 'priority' => 'low']);

    $this->actingAs($this->admin)
        ->patch("/admin/tasks/{$task->id}/status", ['status' => 'done'])
        ->assertForbidden();
});
