<?php

use App\Models\Project;
use App\Models\Workspace;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);
beforeEach(fn () => (new \Database\Seeders\RoleSeeder)->run());

it('fetches repos using stored token', function () {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            ['id' => 1, 'name' => 'portfolio', 'full_name' => 'nakul/portfolio', 'description' => 'My portfolio'],
        ], 200),
    ]);

    $repos = app(GitHubService::class)->withToken('ghp_test')->getRepos();

    expect($repos)->toHaveCount(1)
        ->and($repos[0]['name'])->toBe('portfolio');
});

it('syncs issues to tasks', function () {
    $ws      = Workspace::create(['name' => 'WS', 'slug' => 'ws-gh']);
    $project = Project::create(['workspace_id' => $ws->id, 'name' => 'P', 'slug' => 'p', 'github_repo' => 'nakul/portfolio']);

    Http::fake([
        'api.github.com/repos/nakul/portfolio/issues*' => Http::response([
            ['number' => 42, 'title' => 'Fix header bug', 'body' => 'Details', 'state' => 'open',
             'html_url' => 'https://github.com/nakul/portfolio/issues/42'],
        ], 200),
    ]);

    app(GitHubService::class)->withToken('ghp_test')->syncIssuesToProject($project);

    expect(\App\Models\Task::where('github_issue_id', '42')->exists())->toBeTrue();
});
