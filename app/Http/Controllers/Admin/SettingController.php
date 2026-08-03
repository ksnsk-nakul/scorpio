<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasIntegrationSettings;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class SettingController extends Controller
{
    use HasIntegrationSettings;

    public function index()
    {
        $settings = Setting::all()->groupBy('group')->map(
            fn ($g) => $g->keyBy('key')->map(fn ($s) => $s->value)
        );

        return Inertia::render('Admin/Settings/Index', array_merge([
            'settings' => $settings,
            // "integrations" is a distinct tab from the plain "mail" group above —
            // that one is simple from-name/reply-to display preferences, this one is
            // real provider credentials (Razorpay/SMTP/Twilio), rendered via
            // IntegrationsPanel.vue instead of the generic key-value form.
            'groups'   => ['general', 'seo', 'social', 'mail', 'appearance', 'integrations'],
        ], $this->integrationSettingsProps()));
    }

    public function update(Request $request)
    {
        abort_if(app()->environment('demo'), 403, 'Settings cannot be changed in demo mode.');

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

            if ($key === 'layout_template_public') {
                Cache::forget('settings.layout_template_public');
            }
        }

        return back()->with('success', 'Settings saved.');
    }
}
