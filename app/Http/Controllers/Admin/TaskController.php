<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Services\GitHubService;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function __construct(
        private MediaService $media,
        private GitHubService $github,
    ) {}

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
            ->withCount(['subtasks', 'subtasks as done_subtasks_count' => fn ($q) => $q->where('status', 'done')])
            ->with('project:id,name', 'assignee:id,name,avatar')
            ->latest();

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('priority')) $query->where('priority', $request->priority);
        if ($request->filled('project'))  $query->where('project_id', $request->project);

        $view = $request->input('view', 'list');

        return Inertia::render('Admin/Tasks/Index', [
            'tasks'    => $view === 'board'
                ? $query->get()
                : $query->paginate(20)->withQueryString(),
            'projects' => Project::whereIn('id', $ownedProjectIds)->orderBy('name')->get(['id','name']),
            'filters'  => $request->only('status','priority','project'),
            'view'     => $view,
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
                'activities.user:id,name,avatar',
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

        $this->applyUpdate($task, $data);

        if ($mediaIds) {
            $this->media->attach($mediaIds, $task, auth()->id());
        }

        return back()->with('success', 'Task updated.');
    }

    /** Lightweight status-only update used by the kanban board's drag-and-drop. */
    public function updateStatus(Request $request, Task $task)
    {
        abort_unless($this->ownedProjectIds()->contains($task->project_id), 403);

        $data = $request->validate([
            'status' => 'required|in:open,in_progress,done,closed',
        ]);

        $this->applyUpdate($task, $data);

        return back()->with('success', 'Task status updated.');
    }

    private function applyUpdate(Task $task, array $data): void
    {
        $tracked = ['status', 'priority', 'assignee_id'];
        $before  = $task->only($tracked);

        $task->update($data);

        foreach ($tracked as $field) {
            if (array_key_exists($field, $data) && $before[$field] != $task->{$field}) {
                TaskActivity::create([
                    'task_id' => $task->id,
                    'user_id' => auth()->id(),
                    'field'   => $field,
                    'from'    => $before[$field] !== null ? (string) $before[$field] : null,
                    'to'      => $task->{$field} !== null ? (string) $task->{$field} : null,
                ]);
            }
        }

        $this->pushStatusToGitHub($task, $before['status']);
    }

    /** Push a local status change back to the linked GitHub issue, if any. */
    private function pushStatusToGitHub(Task $task, string $previousStatus): void
    {
        if ($task->status === $previousStatus || ! $task->github_issue_id) {
            return;
        }

        $task->loadMissing('project.workspace.user');
        $owner = $task->project?->workspace?->user;
        $repo  = $task->project?->github_repo;

        if (! $owner?->github_token || ! $repo) {
            return;
        }

        $githubState = in_array($task->status, ['done', 'closed']) ? 'closed' : 'open';
        $this->github->withToken($owner->github_token)->updateIssueState($repo, $task->github_issue_id, $githubState);
    }

    public function destroy(Task $task)
    {
        abort_unless($this->ownedProjectIds()->contains($task->project_id), 403);

        $redirect = $task->parent_id ? "/admin/tasks/{$task->parent_id}" : '/admin/tasks';
        $task->delete();
        return redirect($redirect)->with('success', 'Task deleted.');
    }
}
