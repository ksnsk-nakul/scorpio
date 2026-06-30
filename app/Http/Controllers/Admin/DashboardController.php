<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'pages'        => $user->pages()->count(),
                'serviceCards' => $user->serviceCards()->count(),
                'openTasks'    => Task::whereNull('parent_id')->where('status', 'open')
                    ->whereIn('project_id', $ownedProjectIds)->count(),
                'users'        => $user->hasRole('admin') ? User::count() : null,
            ],
            'recentTasks' => Task::whereNull('parent_id')
                ->whereIn('project_id', $ownedProjectIds)
                ->with('project:id,name', 'assignee:id,name,avatar')
                ->latest()
                ->limit(5)
                ->get(['id','title','status','priority','project_id','assignee_id']),
        ]);
    }
}
