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
        return Inertia::render('Admin/ServiceCards/Index', [
            'cards' => auth()->user()->serviceCards()->with('page:id,name')->orderBy('sort_order')->get(),
            'pages' => auth()->user()->pages()->where('status', 'published')->get(['id','name']),
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

        $data['sort_order'] = ServiceCard::max('sort_order') + 1;
        $data['user_id'] = auth()->id();
        ServiceCard::create($data);

        return redirect('/admin/service-cards')->with('success', 'Card created.');
    }

    public function edit(ServiceCard $serviceCard): Response
    {
        return Inertia::render('Admin/ServiceCards/Form', [
            'card'  => $serviceCard,
            'pages' => auth()->user()->pages()->where('status', 'published')->get(['id','name']),
        ]);
    }

    public function update(Request $request, ServiceCard $serviceCard)
    {
        abort_if($serviceCard->user_id !== auth()->id(), 403);

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
        abort_if($serviceCard->user_id !== auth()->id(), 403);

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
