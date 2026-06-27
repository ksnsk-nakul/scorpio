<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThirdPartySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ThirdPartySettingController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Integrations/Index', [
            'integrations' => ThirdPartySetting::orderBy('group')->orderBy('provider')->get(),
            'groups'       => ['github', 'google', 'storage', 'analytics', 'other'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provider'  => 'required|string|max:100',
            'key'       => 'required|string|max:255',
            'value'     => 'nullable|string',
            'group'     => 'required|in:github,google,storage,analytics,other',
            'is_active' => 'boolean',
        ]);

        ThirdPartySetting::updateOrCreate(
            ['provider' => $data['provider'], 'key' => $data['key']],
            $data
        );

        return back()->with('success', 'Integration saved.');
    }

    public function update(Request $request, ThirdPartySetting $integration)
    {
        $integration->update($request->validate([
            'provider'  => 'required|string|max:100',
            'key'       => 'required|string|max:255',
            'value'     => 'nullable|string',
            'group'     => 'required|in:github,google,storage,analytics,other',
            'is_active' => 'boolean',
        ]));

        return back()->with('success', 'Integration updated.');
    }

    public function destroy(ThirdPartySetting $integration)
    {
        $integration->delete();
        return back()->with('success', 'Integration removed.');
    }
}
