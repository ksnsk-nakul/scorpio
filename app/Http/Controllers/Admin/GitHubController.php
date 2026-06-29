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

        return Inertia::render('Admin/GitHub/Index', [
            'repos'    => $hasToken ? $this->github->withToken($user->github_token)->getRepos() : [],
            'projects' => Project::whereNotNull('github_repo')
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
        $data = $request->validate([
            'owner' => 'required|string',
            'name'  => 'required|string|max:255',
            'body'  => 'nullable|string',
        ]);

        $token = auth()->user()->github_token;
        $ghProject = $this->github->withToken($token)->createProject($data['owner'], $data['name'], $data['body'] ?? '');

        if (! $ghProject) {
            return back()->withErrors(['github' => 'Failed to create GitHub project.']);
        }

        $project->update(['github_project_id' => (string) $ghProject['number']]);
        return back()->with('success', "GitHub project #{$ghProject['number']} created and linked.");
    }

    public function sync(Project $project)
    {
        $token = auth()->user()->github_token;
        $count = $this->github->withToken($token)->syncIssuesToProject($project);
        return back()->with('success', "{$count} issues synced from GitHub.");
    }
}
