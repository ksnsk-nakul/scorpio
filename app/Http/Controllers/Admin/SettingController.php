<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group')->map(
            fn ($g) => $g->keyBy('key')->map(fn ($s) => $s->value)
        );

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'groups'   => ['general', 'seo', 'social', 'mail'],
        ]);
    }

    public function update(Request $request)
    {
        $allowed = Setting::pluck('key')->toArray();

        $numericKeys = ['media_max_size_mb'];
        $rules = [];
        foreach ($numericKeys as $key) {
            if ($request->has($key)) {
                $rules[$key] = 'integer|min:1|max:2048';
            }
        }
        if ($rules) {
            $request->validate($rules);
        }

        foreach ($request->only($allowed) as $key => $value) {
            Setting::where('key', $key)->update(['value' => $value]);
        }

        return back()->with('success', 'Settings saved.');
    }
}
