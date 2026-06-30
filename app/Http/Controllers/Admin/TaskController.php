<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function __construct(private MediaService $media) {}

    private function ownedProjectIds(): \Illuminate\Support\Collection
    {
        return auth()->user()->workspaces()->with('projects:id,workspace_id')->get()
            ->flatMap(fn ($ws) => $ws->projects->pluck('id'));
    }

    public function index(Request $request)
    {
        $ownedProjectIds = $this->ownedProjectIds();

        $query = Task::query()
            ->whereNull('parent_id')
            ->whereIn('project_id', $ownedProjectIds)
            ->with('project:id,name', 'assignee:id,name,avatar')
            ->latest();

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('priority')) $query->where('priority', $request->priority);
        if ($request->filled('project'))  $query->where('project_id', $request->project);

        return Inertia::render('Admin/Tasks/Index', [
            'tasks'    => $query->paginate(20)->withQueryString(),
            'projects' => Project::whereIn('id', $ownedProjectIds)->orderBy('name')->get(['id','name']),
            'filters'  => $request->only('status','priority','project'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id'  => 'required|exists:projects,id',
            'parent_id'   => 'nullable|exists:tasks,id',
            'title'       => 'required|string|max:255',
            'body'        => 'nullable|string',
            'status'      => 'required|in:open,in_progress,done,closed',
            'priority'    => 'required|in:low,medium,high',
            'assignee_id' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
            'media_ids'   => 'nullable|array',
        ]);

        abort_unless($this->ownedProjectIds()->contains($data['project_id']), 403);

        $mediaIds = $data['media_ids'] ?? [];
        unset($data['media_ids']);
        $task = Task::create($data);

        if ($mediaIds) {
            $this->media->attach($mediaIds, $task, auth()->id());
        }

        $redirect = isset($data['parent_id']) && $data['parent_id']
            ? "/admin/tasks/{$data['parent_id']}"
            : "/admin/tasks/{$task->id}";

        return redirect($redirect)->with('success', 'Task created.');
    }

    public function show(Task $task)
    {
        abort_unless($this->ownedProjectIds()->contains($task->project_id), 403);

        return Inertia::render('Admin/Tasks/Show', [
            'task'     => $task->load([
                'project:id,name',
                'assignee:id,name,avatar',
                'parent:id,title',
                'subtasks.assignee:id,name,avatar',
                'comments.user:id,name,avatar',
                'comments.media',
                'media',
            ]),
            'users'    => User::orderBy('name')->get(['id','name','avatar']),
        ]);
    }

    public function update(Request $request, Task $task)
    {
        abort_unless($this->ownedProjectIds()->contains($task->project_id), 403);

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'body'        => 'nullable|string',
            'status'      => 'required|in:open,in_progress,done,closed',
            'priority'    => 'required|in:low,medium,high',
            'assignee_id' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
            'media_ids'   => 'nullable|array',
        ]);

        $mediaIds = $data['media_ids'] ?? [];
        unset($data['media_ids']);
        $task->update($data);

        if ($mediaIds) {
            $this->media->attach($mediaIds, $task, auth()->id());
        }

        return back()->with('success', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        abort_unless($this->ownedProjectIds()->contains($task->project_id), 403);

        $redirect = $task->parent_id ? "/admin/tasks/{$task->parent_id}" : '/admin/tasks';
        $task->delete();
        return redirect($redirect)->with('success', 'Task deleted.');
    }
}
