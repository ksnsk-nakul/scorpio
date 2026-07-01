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
            'workspaces' => auth()->user()->workspaces()->with('projects:id,workspace_id,name,slug,status,github_repo,cover_image')
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

        abort_unless(
            auth()->user()->workspaces()->where('id', $data['workspace_id'])->exists(),
            403
        );

        $project = Project::create($data);
        return redirect("/admin/products/{$project->id}")->with('success', 'Project created.');
    }

    public function show(Project $project)
    {
        abort_unless(
            auth()->user()->workspaces()->where('id', $project->workspace_id)->exists(),
            403
        );

        return Inertia::render('Admin/Products/Show', [
            'project' => $project->load('workspace:id,name'),
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
        abort_unless(
            auth()->user()->workspaces()->where('id', $project->workspace_id)->exists(),
            403
        );

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
            $this->media->attach($mediaIds, $project, auth()->id());
        }

        return redirect("/admin/products/{$project->id}")->with('success', 'Project updated.');
    }

    public function reorder(Request $request): \Illuminate\Http\JsonResponse
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $owned = auth()->user()->workspaces()->with('projects:id,workspace_id')->get()
            ->flatMap(fn ($ws) => $ws->projects->pluck('id'))->all();

        foreach ($ids as $order => $id) {
            if (in_array($id, $owned)) {
                \App\Models\Project::where('id', $id)->update(['sort_order' => $order]);
            }
        }
        return response()->json(['ok' => true]);
    }

    public function destroy(Project $project)
    {
        abort_unless(
            auth()->user()->workspaces()->where('id', $project->workspace_id)->exists(),
            403
        );

        $project->delete();
        return redirect('/admin/products')->with('success', 'Project deleted.');
    }
}
