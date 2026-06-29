<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Pages/Index', [
            'pages'     => auth()->user()->pages()->with('serviceCards')->latest('updated_at')->get(['id','name','slug','status','template','updated_at','is_home','blocks','user_id']),
            'templates' => ['blank', 'hero_cards', 'text_image', 'project_grid'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'template' => 'required|in:blank,hero_cards,text_image,project_grid',
        ]);

        $blocks = match ($data['template']) {
            'hero_cards'   => [
                ['type' => 'hero',         'order' => 0, 'data' => ['heading' => '', 'subheading' => '']],
                ['type' => 'service_cards','order' => 1, 'data' => []],
            ],
            'text_image'   => [['type' => 'text_image',   'order' => 0, 'data' => ['text' => '', 'image' => '']]],
            'project_grid' => [['type' => 'project_grid', 'order' => 0, 'data' => []]],
            default        => [],
        };

        $page = Page::create(array_merge($data, ['blocks' => $blocks, 'user_id' => auth()->id()]));

        return redirect("/admin/pages/{$page->id}/edit");
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Pages/Edit', [
            'page'       => $page,
            'blockTypes' => ['hero','text','text_image','service_cards','project_grid','contact_form'],
        ]);
    }

    public function update(Request $request, Page $page)
    {
        abort_if($page->user_id !== auth()->id(), 403);

        $data = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'blocks' => 'sometimes|array',
            'status' => 'sometimes|in:draft,published',
        ]);

        if (isset($data['status']) && $data['status'] === 'published' && $page->status !== 'published') {
            $data['published_at'] = now();
        }

        $page->update($data);
        return back()->with('success', 'Page saved.');
    }

    public function publish(Page $page)
    {
        abort_if($page->user_id !== auth()->id(), 403);

        $page->update(['status' => 'published', 'published_at' => now()]);
        return back()->with('success', 'Page published.');
    }

    public function destroy(Page $page)
    {
        abort_if($page->user_id !== auth()->id(), 403);
        abort_if($page->is_home, 403, 'Home page cannot be deleted.');

        $page->delete();
        return redirect('/admin/pages')->with('success', 'Page deleted.');
    }

    public function preview(Page $page): \Illuminate\Contracts\View\View
    {
        return view('page-preview', compact('page'));
    }
}
