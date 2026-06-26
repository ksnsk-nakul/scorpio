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

    public function index(Request $request)
    {
        $query = Task::query()
            ->whereNull('parent_id')
            ->with('project:id,name', 'assignee:id,name,avatar')
            ->latest();

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('priority')) $query->where('priority', $request->priority);
        if ($request->filled('project'))  $query->where('project_id', $request->project);

        return Inertia::render('Admin/Tasks/Index', [
            'tasks'    => $query->paginate(20)->withQueryString(),
            'projects' => Project::orderBy('name')->get(['id','name']),
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

        $mediaIds = $data['media_ids'] ?? [];
        unset($data['media_ids']);
        $task = Task::create($data);

        if ($mediaIds) {
            $this->media->attach($mediaIds, $task);
        }

        $redirect = isset($data['parent_id']) && $data['parent_id']
            ? "/admin/tasks/{$data['parent_id']}"
            : "/admin/tasks/{$task->id}";

        return redirect($redirect)->with('success', 'Task created.');
    }

    public function show(Task $task)
    {
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
            $this->media->attach($mediaIds, $task);
        }

        return back()->with('success', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        $redirect = $task->parent_id ? "/admin/tasks/{$task->parent_id}" : '/admin/tasks';
        $task->delete();
        return redirect($redirect)->with('success', 'Task deleted.');
    }
}
