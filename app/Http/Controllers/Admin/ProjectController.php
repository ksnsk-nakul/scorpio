<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function index()
    {
        return Inertia::render('Admin/Products/Index', [
            'workspaces' => Workspace::with('projects:id,workspace_id,name,slug,status,github_repo,cover_image')
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'workspace_id' => 'required|exists:workspaces,id',
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'status'       => 'in:active,archived',
        ]);
        $project = Project::create($data);
        return redirect("/admin/products/{$project->id}")->with('success', 'Project created.');
    }

    public function show(Project $project)
    {
        return Inertia::render('Admin/Products/Show', [
            'project' => $project->load('workspace:id,name'),
            'tasks'   => $project->rootTasks()->with('assignee:id,name,avatar')->limit(20)->get(),
            'media'   => $project->media()->latest()->get()->map(fn ($m) => [
                'id'       => $m->id,
                'filename' => $m->filename,
                'url'      => $m->url,
                'is_image' => $m->isImage(),
                'is_video' => $m->isVideo(),
            ]),
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'github_repo'       => 'nullable|string|max:255',
            'github_project_id' => 'nullable|string|max:255',
            'status'            => 'in:active,archived',
            'media_ids'         => 'nullable|array',
        ]);

        $mediaIds = $data['media_ids'] ?? [];
        unset($data['media_ids']);
        $project->update($data);

        if ($mediaIds) {
            $this->media->attach($mediaIds, $project);
        }

        return redirect("/admin/products/{$project->id}")->with('success', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return redirect('/admin/products')->with('success', 'Project deleted.');
    }
}
