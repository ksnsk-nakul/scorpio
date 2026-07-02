<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    private array $keys = [
        'RAZORPAY_KEY_ID',
        'RAZORPAY_KEY_SECRET',
        'RAZORPAY_ME_HANDLE',
    ];

    public function index()
    {
        $values = [];
        foreach ($this->keys as $key) {
            $values[$key] = env($key, '');
        }

        $keyId = $values['RAZORPAY_KEY_ID'] ?? '';
        $environment = str_starts_with($keyId, 'rzp_live_') ? 'live' : 'test';

        return Inertia::render('Admin/Payment/Index', [
            'values'      => $values,
            'environment' => $environment,
            'configured'  => filled($keyId) && filled($values['RAZORPAY_KEY_SECRET']),
        ]);
    }

    public function update(Request $request)
    {
        abort_if(app()->environment('demo'), 403, 'Cannot modify payment settings in demo mode.');

        $data = $request->validate([
            'environment'           => 'required|in:test,live',
            'RAZORPAY_KEY_ID'       => 'required|string|max:100',
            'RAZORPAY_KEY_SECRET'   => 'required|string|max:100',
            'RAZORPAY_ME_HANDLE'    => 'nullable|string|max:100',
        ]);

        // Validate key matches declared environment
        $prefix = "rzp_{$data['environment']}_";
        if (! str_starts_with($data['RAZORPAY_KEY_ID'], $prefix)) {
            return back()->withErrors([
                'RAZORPAY_KEY_ID' => "Key ID must start with \"{$prefix}\" for {$data['environment']} mode.",
            ]);
        }

        $this->writeEnv([
            'RAZORPAY_KEY_ID'     => $data['RAZORPAY_KEY_ID'],
            'RAZORPAY_KEY_SECRET' => $data['RAZORPAY_KEY_SECRET'],
            'RAZORPAY_ME_HANDLE'  => $data['RAZORPAY_ME_HANDLE'] ?? '',
        ]);

        return back()->with('success', 'Payment gateway settings saved. Restart your server if changes don\'t apply immediately.');
    }

    private function writeEnv(array $pairs): void
    {
        $path    = base_path('.env');
        $content = file_get_contents($path);

        foreach ($pairs as $key => $value) {
            $escaped = preg_match('/\s/', $value) ? "\"{$value}\"" : $value;
            $pattern = "/^{$key}=.*/m";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$key}={$escaped}", $content);
            } else {
                $content .= "\n{$key}={$escaped}";
            }
        }

        file_put_contents($path, $content);

        // Re-read so current request reflects new values
        foreach ($pairs as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}
