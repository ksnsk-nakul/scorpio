<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Workspace;
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

        $repos = [];
        if ($hasToken) {
            $repos = $this->github->withToken($user->github_token)->getRepos(100);

            // Auto-unlink projects whose GitHub repo was deleted/renamed
            $existingFullNames = collect($repos)->pluck('full_name')->map(fn($n) => strtolower($n));
            Project::whereNotNull('github_repo')
                ->whereIn('workspace_id', $ownedWorkspaceIds)
                ->get(['id', 'github_repo'])
                ->each(function (Project $project) use ($existingFullNames) {
                    if (! $existingFullNames->contains(strtolower($project->github_repo))) {
                        $project->update(['github_repo' => null, 'github_webhook_secret' => null]);
                    }
                });
        }

        return Inertia::render('Admin/GitHub/Index', [
            'repos'          => $repos,
            'projects'       => Project::whereNotNull('github_repo')
                ->whereIn('workspace_id', $ownedWorkspaceIds)
                ->with('workspace:id,name')
                ->get(['id','name','github_repo','github_project_id','workspace_id']),
            'hasToken'       => $hasToken,
            'hasOAuthClient' => filled(config('services.github.client_id')),
        ]);
    }

    public function linkRepoAsProduct(Request $request)
    {
        $data = $request->validate([
            'full_name'   => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        // Already linked to one of this user's projects?
        $ownedWorkspaceIds = $user->workspaces()->pluck('id');
        $exists = Project::whereIn('workspace_id', $ownedWorkspaceIds)
            ->where('github_repo', $data['full_name'])
            ->exists();

        if ($exists) {
            return back()->with('info', 'This repository is already linked to a product.');
        }

        // Use the user's first workspace, or create one
        $workspace = $user->workspaces()->first()
            ?? Workspace::create(['user_id' => $user->id, 'name' => 'My Projects']);

        $repoName = last(explode('/', $data['full_name']));

        Project::create([
            'workspace_id' => $workspace->id,
            'name'         => str($repoName)->replace(['-', '_'], ' ')->title()->toString(),
            'slug'         => \Illuminate\Support\Str::slug($repoName),
            'description'  => $data['description'] ?? '',
            'github_repo'  => $data['full_name'],
            'status'       => 'active',
        ]);

        return back()->with('success', ""{$repoName}" added as a product.");
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
        $this->authorize('update', $project);

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
        $this->authorize('update', $project);
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
        $this->authorize('update', $project);

        $token = auth()->user()->github_token;
        $count = $this->github->withToken($token)->syncIssuesToProject($project);
        return back()->with('success', "{$count} issues synced from GitHub.");
    }
}
