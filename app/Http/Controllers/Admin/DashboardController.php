<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Models\Task;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $ownedProjectIds = $user->workspaces()->with('projects:id,workspace_id')->get()
            ->flatMap(fn ($ws) => $ws->projects->pluck('id'));

        $contacts = ContactSubmission::where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get(['id', 'name', 'email', 'message', 'read', 'created_at']);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'pages'        => $user->pages()->count(),
                'serviceCards' => $user->serviceCards()->count(),
                'openTasks'    => Task::whereNull('parent_id')->where('status', 'open')
                    ->whereIn('project_id', $ownedProjectIds)->count(),
                'overdueTasks' => Task::whereNull('parent_id')
                    ->whereIn('project_id', $ownedProjectIds)
                    ->whereNotIn('status', ['done', 'closed'])
                    ->whereDate('due_date', '<', now())
                    ->count(),
                'users'        => $user->hasRole('admin') ? User::count() : null,
                'unreadMessages' => $contacts->where('read', false)->count(),
            ],
            'recentTasks' => Task::whereNull('parent_id')
                ->whereIn('project_id', $ownedProjectIds)
                ->with('project:id,name', 'assignee:id,name,avatar')
                ->latest()
                ->limit(5)
                ->get(['id','title','status','priority','project_id','assignee_id','due_date']),
            'contacts' => $contacts,
        ]);
    }

    public function markContactRead(ContactSubmission $submission)
    {
        abort_if($submission->user_id !== auth()->id(), 403);
        $submission->update(['read' => true]);
        return response()->noContent();
    }
}
