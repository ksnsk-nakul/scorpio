<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\GitHubService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GitHubController extends Controller
{
    public function __construct(private GitHubService $github) {}

    public function index()
    {
        $user     = auth()->user();
        $hasToken = filled($user->github_token);

        $ownedWorkspaceIds = $user->workspaces()->pluck('id');

        return Inertia::render('Admin/GitHub/Index', [
            'repos'    => $hasToken ? $this->github->withToken($user->github_token)->getRepos() : [],
            'projects' => Project::whereNotNull('github_repo')
                ->whereIn('workspace_id', $ownedWorkspaceIds)
                ->with('workspace:id,name')
                ->get(['id','name','github_repo','github_project_id','workspace_id']),
            'hasToken' => $hasToken,
        ]);
    }

    public function connectToken(Request $request)
    {
        $request->validate(['token' => 'required|string']);
        $user = auth()->user();
        $user->github_token = $request->token;
        $user->save();
        return redirect()->route('admin.github.index')->with('success', 'GitHub token connected.');
    }

    public function disconnectToken()
    {
        $user = auth()->user();
        $user->github_token = null;
        $user->save();
        return redirect()->route('admin.github.index')->with('success', 'GitHub token disconnected.');
    }

    public function createGitHubProject(Request $request, Project $project)
    {
        abort_if(! filled(auth()->user()->github_token), 403, 'No GitHub token configured.');
        abort_unless(auth()->user()->workspaces()->where('id', $project->workspace_id)->exists(), 403);

        $data = $request->validate([
            'owner' => 'required|string',
            'name'  => 'required|string|max:255',
        ]);

        $token = auth()->user()->github_token;
        $ghProject = $this->github->withToken($token)->createProjectV2($data['owner'], $data['name']);

        if (! $ghProject) {
            return back()->withErrors(['github' => 'Failed to create GitHub project. Check that your token has the "project" scope.']);
        }

        $project->update(['github_project_id' => (string) $ghProject['number']]);
        return back()->with('success', "GitHub project #{$ghProject['number']} created and linked.");
    }

    /**
     * Generate (or rotate) a webhook secret for a project and return the
     * URL + secret for the user to paste into the repo's GitHub webhook
     * settings — gives real-time sync instead of hourly/on-demand pulls.
     */
    public function webhookCredentials(Project $project)
    {
        abort_unless(auth()->user()->workspaces()->where('id', $project->workspace_id)->exists(), 403);
        abort_if(! $project->github_repo, 422, 'Link a GitHub repo to this product first.');

        $project->github_webhook_secret = bin2hex(random_bytes(20));
        $project->save();

        return back()->with([
            'webhook_project_id' => $project->id,
            'webhook_url'        => route('webhooks.github', $project),
            'webhook_secret'     => $project->github_webhook_secret,
        ]);
    }

    public function sync(Project $project)
    {
        abort_if(! filled(auth()->user()->github_token), 403, 'No GitHub token configured.');
        abort_unless(auth()->user()->workspaces()->where('id', $project->workspace_id)->exists(), 403);

        $token = auth()->user()->github_token;
        $count = $this->github->withToken($token)->syncIssuesToProject($project);
        return back()->with('success', "{$count} issues synced from GitHub.");
    }
}
