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
            'announcements' => \App\Models\Announcement::active()->get(['id','title','body','type','display','cta_label','cta_url']),
            'planLimits' => [
                'pages'        => $user->planLimit('pages'),
                'workspaces'   => $user->planLimit('workspaces'),
                'projects'     => $user->planLimit('projects'),
                'skills'       => $user->planLimit('skills'),
                'service_cards'=> $user->planLimit('service_cards'),
            ],
            'planFeatures' => [
                'github_sync'          => $user->planFeature('github_sync'),
                'analytics'            => $user->planFeature('analytics'),
                'seo_control'          => $user->planFeature('seo_control'),
                'password_pages'       => $user->planFeature('password_pages'),
                'scheduled_publish'    => $user->planFeature('scheduled_publish'),
                'contact_attachments'  => $user->planFeature('contact_attachments'),
                'white_label'          => $user->planFeature('white_label'),
                'audit_logs'           => $user->planFeature('audit_logs'),
                'api_access'           => $user->planFeature('api_access'),
                'priority_support'     => $user->planFeature('priority_support'),
            ],
            'currentPlan' => $user->currentPlan(),
        ]);
    }

    public function markContactRead(ContactSubmission $submission)
    {
        $this->authorize('update', $submission);
        $submission->update(['read' => true]);
        return response()->noContent();
    }
}
