<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\ServiceCard;
use App\Models\Task;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'pages'        => Page::count(),
                'serviceCards' => ServiceCard::count(),
                'openTasks'    => Task::whereNull('parent_id')->where('status', 'open')->count(),
                'users'        => User::count(),
            ],
            'recentTasks' => Task::whereNull('parent_id')
                ->with('project:id,name', 'assignee:id,name,avatar')
                ->latest()
                ->limit(5)
                ->get(['id','title','status','priority','project_id','assignee_id']),
        ]);
    }
}
