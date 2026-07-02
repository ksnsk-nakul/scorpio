<?php

namespace App\Http\Controllers;

use App\Models\AboutSection;
use App\Models\ExperienceItem;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Skill;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function index(): Response
    {
        $user = $this->adminUser();

        if (! $user) {
            return Inertia::render('Public/Home', [
                'pages'    => [],
                'settings' => Setting::whereIn('key', ['site_name', 'site_tagline', 'meta_description'])->pluck('value', 'key'),
            ]);
        }

        $page = $user->pages()
            ->where('is_home', true)
            ->where('status', 'published')
            ->with('serviceCards')
            ->first();

        if (! $page) {
            return Inertia::render('Public/ProfileStub', [
                'owner' => $user->only('id', 'name', 'username'),
            ]);
        }

        return Inertia::render('Public/Portfolio', [
            'page'       => $page,
            'owner'      => $user->only('id', 'name', 'username'),
            'workspaces' => $user->workspaces()->with('projects:id,workspace_id,name,slug,description,github_repo,demo_url,tags,status,sort_order')->orderBy('sort_order')->get(['id','name'])->keyBy('id'),
            'settings'   => $this->tenantSettings($user),
            'dbSkills'   => $this->userSkills($user),
            'dbAbout'    => $this->userAbout($user),
            'dbExperience' => $this->userExperience($user),
        ]);
    }

    public function adminPage(string $slug): Response
    {
        $user = $this->adminUser();
        abort_if(! $user, 404);

        $page = $user->pages()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with('serviceCards')
            ->firstOrFail();

        return Inertia::render('Public/Portfolio', [
            'page'         => $page,
            'owner'        => $user->only('id', 'name', 'username'),
            'workspaces'   => $user->workspaces()->with('projects:id,workspace_id,name,slug,description,github_repo,demo_url,tags,status,sort_order')->orderBy('sort_order')->get(['id','name'])->keyBy('id'),
            'settings'     => $this->tenantSettings($user),
            'dbSkills'     => $this->userSkills($user),
            'dbAbout'      => $this->userAbout($user),
            'dbExperience' => $this->userExperience($user),
        ]);
    }

    public function portfolio(string $username): Response
    {
        $user = User::where('username', $username)
            ->select(['id', 'name', 'username', 'site_name', 'og_image'])
            ->firstOrFail();

        $page = $user->pages()
            ->where('is_home', true)
            ->where('status', 'published')
            ->with('serviceCards')
            ->first();

        if (! $page) {
            return Inertia::render('Public/ProfileStub', [
                'owner' => $user->only('id', 'name', 'username'),
            ]);
        }

        return Inertia::render('Public/Portfolio', [
            'page'         => $page,
            'owner'        => $user->only('id', 'name', 'username'),
            'workspaces'   => $user->workspaces()->with('projects:id,workspace_id,name,slug,description,github_repo,demo_url,tags,status,sort_order')->orderBy('sort_order')->get(['id','name'])->keyBy('id'),
            'settings'     => $this->tenantSettings($user),
            'dbSkills'     => $this->userSkills($user),
            'dbAbout'      => $this->userAbout($user),
            'dbExperience' => $this->userExperience($user),
        ]);
    }

    public function portfolioPage(string $username, string $slug): Response
    {
        $user = User::where('username', $username)
            ->select(['id', 'name', 'username', 'site_name', 'og_image'])
            ->firstOrFail();

        $page = $user->pages()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with('serviceCards')
            ->firstOrFail();

        return Inertia::render('Public/Portfolio', [
            'page'         => $page,
            'owner'        => $user->only('id', 'name', 'username'),
            'workspaces'   => $user->workspaces()->with('projects:id,workspace_id,name,slug,description,github_repo,demo_url,tags,status,sort_order')->orderBy('sort_order')->get(['id','name'])->keyBy('id'),
            'settings'     => $this->tenantSettings($user),
            'dbSkills'     => $this->userSkills($user),
            'dbAbout'      => $this->userAbout($user),
            'dbExperience' => $this->userExperience($user),
        ]);
    }

    public function projectDetail(string $username, string $projectSlug): Response
    {
        $user = User::where('username', $username)
            ->select(['id', 'name', 'username', 'site_name', 'og_image'])
            ->firstOrFail();

        // Find project in any of this user's workspaces
        $workspaceIds = $user->workspaces()->pluck('id');
        $project = Project::whereIn('workspace_id', $workspaceIds)
            ->where('slug', $projectSlug)
            ->where('status', 'active')
            ->firstOrFail();

        $media = $project->media()->get()->map(fn ($m) => [
            'id'       => $m->id,
            'url'      => $m->url,
            'alt_text' => $m->alt_text,
            'filename' => $m->filename,
            'is_image' => $m->isImage(),
            'is_video' => $m->isVideo(),
        ]);

        return Inertia::render('Public/ProjectDetail', [
            'project'  => $project->only('id','name','slug','description','details','github_repo','demo_url','tags'),
            'media'    => $media,
            'owner'    => $user->only('id', 'name', 'username'),
            'settings' => $this->tenantSettings($user),
        ]);
    }

    private function adminUser(): ?User
    {
        return User::role('admin')
            ->select(['id', 'name', 'username', 'site_name', 'og_image'])
            ->orderBy('id')
            ->first();
    }

    private function tenantSettings(User $user): array
    {
        return [
            'site_name'          => $user->site_name ?? Setting::get('site_name'),
            'og_image'           => $user->og_image,
            'show_donate_banner' => (bool) Setting::get('show_donate_banner', false),
        ];
    }

    private function userSkills(User $user): array
    {
        return Skill::where('user_id', $user->id)->orderBy('sort_order')->orderBy('name')->get(['name', 'icon'])->toArray();
    }

    private function userAbout(User $user): ?array
    {
        $a = AboutSection::where('user_id', $user->id)->first();
        return $a ? ['bio' => $a->bio, 'overview' => $a->overview ?? []] : null;
    }

    private function userExperience(User $user): array
    {
        return ExperienceItem::where('user_id', $user->id)->orderBy('sort_order')->get(['period', 'title', 'company', 'description'])->toArray();
    }
}
