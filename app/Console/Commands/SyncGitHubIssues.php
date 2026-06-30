<?php
namespace App\Console\Commands;

use App\Models\Project;
use App\Services\GitHubService;
use Illuminate\Console\Command;

class SyncGitHubIssues extends Command
{
    protected $signature   = 'github:sync {--project= : Sync a specific project by ID}';
    protected $description = 'Sync open GitHub issues as tasks for all (or one) linked projects';

    public function handle(GitHubService $github): int
    {
        $query = Project::whereNotNull('github_repo')->with('workspace.user');

        if ($this->option('project')) {
            $query->where('id', $this->option('project'));
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            $this->warn('No projects with a linked GitHub repo found.');
            return self::SUCCESS;
        }

        $total = 0;
        foreach ($projects as $project) {
            $token = $project->workspace?->user?->github_token;

            if (! $token) {
                $this->warn("  {$project->name} ({$project->github_repo}): skipped — owner has no GitHub token connected.");
                continue;
            }

            $count = $github->withToken($token)->syncIssuesToProject($project);
            $this->line("  {$project->name} ({$project->github_repo}): {$count} issues synced.");
            $total += $count;
        }

        $this->info("Done. {$total} issues synced across {$projects->count()} project(s).");
        return self::SUCCESS;
    }
}
