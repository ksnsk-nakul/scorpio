<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

beforeEach(function () {
    $ws = Workspace::create(['name' => 'WS', 'slug' => 'ws']);
    $this->project = Project::create([
        'workspace_id'           => $ws->id,
        'name'                   => 'Proj',
        'slug'                   => 'proj',
        'github_repo'            => 'octocat/hello-world',
        'github_webhook_secret'  => 'test-secret',
    ]);
});

function signedPayload(string $secret, array $payload): array
{
    $body = json_encode($payload);
    return [
        'body'      => $body,
        'signature' => 'sha256=' . hash_hmac('sha256', $body, $secret),
    ];
}

it('rejects a webhook with an invalid signature', function () {
    $payload = ['issue' => ['number' => 1, 'title' => 'Bug', 'state' => 'open', 'html_url' => 'https://x']];

    $this->postJson("/webhooks/github/{$this->project->id}", $payload, [
        'X-GitHub-Event'         => 'issues',
        'X-Hub-Signature-256'    => 'sha256=wrong',
    ])->assertStatus(401);

    expect(Task::count())->toBe(0);
});

it('accepts a correctly signed webhook and creates a task from the issue', function () {
    $payload = ['issue' => ['number' => 42, 'title' => 'Fix login bug', 'state' => 'open', 'html_url' => 'https://github.com/x/issues/42']];
    $signed  = signedPayload('test-secret', $payload);

    $this->call('POST', "/webhooks/github/{$this->project->id}", [], [], [], [
        'HTTP_X-GitHub-Event'      => 'issues',
        'HTTP_X-Hub-Signature-256' => $signed['signature'],
        'CONTENT_TYPE'             => 'application/json',
    ], $signed['body'])->assertOk();

    expect(Task::where('github_issue_id', '42')->where('title', 'Fix login bug')->exists())->toBeTrue();
});

it('returns 404 when the project has no webhook secret configured', function () {
    $project = Project::create(['workspace_id' => $this->project->workspace_id, 'name' => 'NoSecret', 'slug' => 'no-secret']);

    $this->postJson("/webhooks/github/{$project->id}", ['issue' => []], [
        'X-GitHub-Event' => 'issues',
    ])->assertStatus(404);
});
