<?php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request, Project $project): Response
    {
        if (! $project->github_webhook_secret) {
            return response('Webhook not configured', 404);
        }

        if (! $this->verifySignature($request, $project->github_webhook_secret)) {
            Log::warning('GitHub webhook signature mismatch', ['project_id' => $project->id]);
            return response('Invalid signature', 401);
        }

        $event = $request->header('X-GitHub-Event');

        if ($event === 'issues') {
            $this->handleIssueEvent($request, $project);
        }

        return response('ok', 200);
    }

    private function verifySignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    private function handleIssueEvent(Request $request, Project $project): void
    {
        $issue = $request->input('issue');

        if (! $issue || isset($issue['pull_request'])) {
            return;
        }

        $task = Task::updateOrCreate(
            ['github_issue_id' => (string) $issue['number'], 'project_id' => $project->id],
            [
                'title'            => $issue['title'],
                'body'             => $issue['body'] ?? '',
                'github_issue_url' => $issue['html_url'],
                'status'           => $issue['state'] === 'open' ? 'open' : 'closed',
                'priority'         => 'medium',
            ]
        );

        if ($task->wasChanged('status')) {
            TaskActivity::create([
                'task_id' => $task->id,
                'field'   => 'status',
                'from'    => null,
                'to'      => $task->status . ' (via GitHub webhook)',
            ]);
        }
    }
}
