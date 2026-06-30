<?php
namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    private string $baseUrl = 'https://api.github.com';
    private ?string $overrideToken = null;

    /** Return a clone of the service using the given token. */
    public function withToken(?string $token): static
    {
        $clone = clone $this;
        $clone->overrideToken = $token;
        return $clone;
    }

    private function token(): ?string
    {
        return $this->overrideToken;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $token = $this->token();
        if (! $token) {
            throw new \RuntimeException('GitHub token not configured. Connect your account on the GitHub page.');
        }
        return Http::withToken($token)
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
            ->baseUrl($this->baseUrl);
    }

    public function getRepos(int $perPage = 30): array
    {
        try {
            return $this->http()
                ->get('/user/repos', ['per_page' => $perPage, 'sort' => 'updated'])
                ->json() ?? [];
        } catch (\Throwable $e) {
            Log::warning('GitHubService::getRepos failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getIssues(string $repo, string $state = 'open'): array
    {
        try {
            return $this->http()
                ->get("/repos/{$repo}/issues", ['state' => $state, 'per_page' => 100])
                ->json() ?? [];
        } catch (\Throwable $e) {
            Log::warning('GitHubService::getIssues failed', ['repo' => $repo, 'error' => $e->getMessage()]);
            return [];
        }
    }

    public function syncIssuesToProject(Project $project): int
    {
        if (! $project->github_repo) {
            return 0;
        }

        $issues = $this->getIssues($project->github_repo);
        $count  = 0;

        foreach ($issues as $issue) {
            if (isset($issue['pull_request'])) continue;

            Task::updateOrCreate(
                ['github_issue_id' => (string) $issue['number'], 'project_id' => $project->id],
                [
                    'title'            => $issue['title'],
                    'body'             => $issue['body'] ?? '',
                    'github_issue_url' => $issue['html_url'],
                    'status'           => $issue['state'] === 'open' ? 'open' : 'closed',
                    'priority'         => 'medium',
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Push a task's status back to its linked GitHub issue (open/closed).
     * Called when a task with github_issue_id changes status locally, so
     * the sync isn't purely one-directional (GitHub -> tasks).
     */
    public function updateIssueState(string $repo, string $issueNumber, string $state): bool
    {
        try {
            $response = $this->http()->patch("/repos/{$repo}/issues/{$issueNumber}", ['state' => $state]);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('GitHubService::updateIssueState failed', ['repo' => $repo, 'issue' => $issueNumber, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create a GitHub Projects v2 board via GraphQL. The classic REST
     * Projects API (/users/{owner}/projects) this used to call is
     * deprecated/removed; Projects v2 is GraphQL-only.
     */
    public function createProjectV2(string $ownerLogin, string $title): ?array
    {
        try {
            $ownerQuery = <<<'GQL'
            query($login: String!) {
              repositoryOwner(login: $login) { id }
            }
            GQL;
            $ownerId = $this->graphql($ownerQuery, ['login' => $ownerLogin])['data']['repositoryOwner']['id'] ?? null;

            if (! $ownerId) {
                return null;
            }

            $mutation = <<<'GQL'
            mutation($ownerId: ID!, $title: String!) {
              createProjectV2(input: { ownerId: $ownerId, title: $title }) {
                projectV2 { id number title url }
              }
            }
            GQL;
            return $this->graphql($mutation, ['ownerId' => $ownerId, 'title' => $title])['data']['createProjectV2']['projectV2'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('GitHubService::createProjectV2 failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function graphql(string $query, array $variables = []): array
    {
        return $this->http()
            ->post('https://api.github.com/graphql', ['query' => $query, 'variables' => $variables])
            ->json() ?? [];
    }
}
