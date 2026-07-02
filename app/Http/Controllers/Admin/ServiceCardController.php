<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\ServiceCard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        return Inertia::render('Admin/ServiceCards/Index', [
            'cards'            => $user->serviceCards()->with('page:id,name')->orderBy('sort_order')->get(),
            'pages'            => $user->pages()->where('status', 'published')->get(['id','name']),
            'serviceCardLimit' => $user->planLimit('service_cards'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ServiceCards/Form', [
            'pages' => auth()->user()->pages()->where('status', 'published')->get(['id','name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'icon'         => 'nullable|string|max:100',
            'image'        => 'nullable|string',
            'tags'         => 'nullable|array',
            'featured'     => 'boolean',
            'page_id'      => 'nullable|exists:pages,id',
            'external_url' => 'nullable|url',
        ]);

        $user  = auth()->user();
        $count = $user->serviceCards()->count();
        if (! $user->withinLimit('service_cards', $count)) {
            $limit = $user->planLimit('service_cards');
            return back()->withErrors(['title' => "Your plan allows up to {$limit} service cards. Upgrade to add more."]);
        }

        $data['sort_order'] = ($user->serviceCards()->max('sort_order') ?? 0) + 1;
        $data['user_id'] = $user->id;
        ServiceCard::create($data);

        return redirect('/admin/service-cards')->with('success', 'Card created.');
    }

    public function edit(ServiceCard $serviceCard): Response
    {
        $this->authorize('update', $serviceCard);

        return Inertia::render('Admin/ServiceCards/Form', [
            'card'  => $serviceCard,
            'pages' => auth()->user()->pages()->where('status', 'published')->get(['id','name']),
        ]);
    }

    public function update(Request $request, ServiceCard $serviceCard)
    {
        $this->authorize('update', $serviceCard);

        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'icon'         => 'nullable|string|max:100',
            'image'        => 'nullable|string',
            'tags'         => 'nullable|array',
            'featured'     => 'boolean',
            'page_id'      => 'nullable|exists:pages,id',
            'external_url' => 'nullable|url',
        ]);

        $serviceCard->update($data);
        return redirect('/admin/service-cards')->with('success', 'Card updated.');
    }

    public function destroy(ServiceCard $serviceCard)
    {
        $this->authorize('delete', $serviceCard);

        $serviceCard->delete();
        return redirect('/admin/service-cards')->with('success', 'Card deleted.');
    }

    public function reorder(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        $ownedIds = auth()->user()->serviceCards()->pluck('id')->toArray();
        foreach ($request->ids as $order => $id) {
            if (in_array($id, $ownedIds)) {
                ServiceCard::where('id', $id)->update(['sort_order' => $order]);
            }
        }
        return response()->json(['ok' => true]);
    }
}
