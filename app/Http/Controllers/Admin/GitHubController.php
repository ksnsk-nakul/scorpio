<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ThirdPartySetting;
use App\Services\GitHubService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GitHubController extends Controller
{
    public function __construct(private GitHubService $github) {}

    public function index()
    {
        $hasToken = ThirdPartySetting::where('group', 'github')
            ->where('key', 'token')->where('is_active', true)->exists();

        return Inertia::render('Admin/GitHub/Index', [
            'repos'    => $hasToken ? $this->github->getRepos() : [],
            'projects' => Project::whereNotNull('github_repo')
                ->with('workspace:id,name')
                ->get(['id','name','github_repo','github_project_id','workspace_id']),
            'hasToken' => $hasToken,
        ]);
    }

    public function createGitHubProject(Request $request, Project $project)
    {
        $data = $request->validate([
            'owner' => 'required|string',
            'name'  => 'required|string|max:255',
            'body'  => 'nullable|string',
        ]);

        $ghProject = $this->github->createProject($data['owner'], $data['name'], $data['body'] ?? '');

        if (! $ghProject) {
            return back()->withErrors(['github' => 'Failed to create GitHub project.']);
        }

        $project->update(['github_project_id' => (string) $ghProject['number']]);
        return back()->with('success', "GitHub project #{$ghProject['number']} created and linked.");
    }

    public function sync(Project $project)
    {
        $count = $this->github->syncIssuesToProject($project);
        return back()->with('success', "{$count} issues synced from GitHub.");
    }
}
