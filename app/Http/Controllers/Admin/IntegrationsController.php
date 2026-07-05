<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsController extends Controller
{
    public function index(): Response
    {
        $razorpayKeyId = env('RAZORPAY_KEY_ID', '');

        return Inertia::render('Admin/Integrations/Index', [
            'payment' => [
                'RAZORPAY_KEY_ID'     => $razorpayKeyId,
                'RAZORPAY_KEY_SECRET' => env('RAZORPAY_KEY_SECRET', ''),
                'RAZORPAY_ME_HANDLE'  => env('RAZORPAY_ME_HANDLE', ''),
                'environment'         => str_starts_with($razorpayKeyId, 'rzp_live_') ? 'live' : 'test',
                'configured'          => filled($razorpayKeyId) && filled(env('RAZORPAY_KEY_SECRET', '')),
            ],
            'mail' => [
                'MAIL_MAILER'       => env('MAIL_MAILER', 'smtp'),
                'MAIL_HOST'         => env('MAIL_HOST', ''),
                'MAIL_PORT'         => env('MAIL_PORT', '587'),
                'MAIL_USERNAME'     => env('MAIL_USERNAME', ''),
                'MAIL_PASSWORD'     => env('MAIL_PASSWORD', ''),
                'MAIL_ENCRYPTION'   => env('MAIL_ENCRYPTION', 'tls'),
                'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS', ''),
                'MAIL_FROM_NAME'    => env('MAIL_FROM_NAME', ''),
                'configured'        => filled(env('MAIL_HOST', '')),
            ],
            'sms' => [
                'TWILIO_SID'        => env('TWILIO_SID', ''),
                'TWILIO_AUTH_TOKEN' => env('TWILIO_AUTH_TOKEN', ''),
                'TWILIO_FROM'       => env('TWILIO_FROM', ''),
                'configured'        => filled(env('TWILIO_SID', '')) && filled(env('TWILIO_AUTH_TOKEN', '')),
            ],
        ]);
    }

    public function savePayment(Request $request): RedirectResponse
    {
        abort_if(app()->environment('demo'), 403, 'Cannot modify settings in demo mode.');

        $data = $request->validate([
            'environment'         => 'required|in:test,live',
            'RAZORPAY_KEY_ID'     => 'required|string|max:100',
            'RAZORPAY_KEY_SECRET' => 'required|string|max:100',
            'RAZORPAY_ME_HANDLE'  => 'nullable|string|max:100',
        ]);

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

        return back()->with('success', 'Payment settings saved.');
    }

    public function saveMail(Request $request): RedirectResponse
    {
        abort_if(app()->environment('demo'), 403, 'Cannot modify settings in demo mode.');

        $data = $request->validate([
            'MAIL_MAILER'       => 'required|string|in:smtp,sendmail,mailgun,ses,postmark,log,array',
            'MAIL_HOST'         => 'required|string|max:255',
            'MAIL_PORT'         => 'required|integer|min:1|max:65535',
            'MAIL_USERNAME'     => 'nullable|string|max:255',
            'MAIL_PASSWORD'     => 'nullable|string|max:255',
            'MAIL_ENCRYPTION'   => 'nullable|string|in:tls,ssl,starttls,',
            'MAIL_FROM_ADDRESS' => 'required|email|max:255',
            'MAIL_FROM_NAME'    => 'required|string|max:100',
        ]);

        $this->writeEnv([
            'MAIL_MAILER'       => $data['MAIL_MAILER'],
            'MAIL_HOST'         => $data['MAIL_HOST'],
            'MAIL_PORT'         => (string) $data['MAIL_PORT'],
            'MAIL_USERNAME'     => $data['MAIL_USERNAME'] ?? '',
            'MAIL_PASSWORD'     => $data['MAIL_PASSWORD'] ?? '',
            'MAIL_ENCRYPTION'   => $data['MAIL_ENCRYPTION'] ?? 'tls',
            'MAIL_FROM_ADDRESS' => $data['MAIL_FROM_ADDRESS'],
            'MAIL_FROM_NAME'    => $data['MAIL_FROM_NAME'],
        ]);

        return back()->with('success', 'Mail settings saved.');
    }

    public function saveSms(Request $request): RedirectResponse
    {
        abort_if(app()->environment('demo'), 403, 'Cannot modify settings in demo mode.');

        $data = $request->validate([
            'TWILIO_SID'        => 'required|string|max:100',
            'TWILIO_AUTH_TOKEN' => 'required|string|max:100',
            'TWILIO_FROM'       => 'required|string|max:20',
        ]);

        $this->writeEnv([
            'TWILIO_SID'        => $data['TWILIO_SID'],
            'TWILIO_AUTH_TOKEN' => $data['TWILIO_AUTH_TOKEN'],
            'TWILIO_FROM'       => $data['TWILIO_FROM'],
        ]);

        return back()->with('success', 'SMS (Twilio) settings saved.');
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

        foreach ($pairs as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}
