<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasIntegrationSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class IntegrationsController extends Controller
{
    use HasIntegrationSettings;

    // Integrations now lives as a tab inside /admin/settings — this route stays
    // registered (bookmarks, old links) but just forwards there instead of
    // rendering its own page.
    public function index(): RedirectResponse
    {
        return redirect('/admin/settings?group=integrations');
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

    public function testMail(Request $request): JsonResponse
    {
        abort_if(app()->environment('demo'), 403, 'Cannot test mail in demo mode.');

        $data = $request->validate([
            'MAIL_HOST'         => 'required|string|max:255',
            'MAIL_PORT'         => 'required|integer|min:1|max:65535',
            'MAIL_USERNAME'     => 'nullable|string|max:255',
            'MAIL_PASSWORD'     => 'nullable|string|max:255',
            'MAIL_ENCRYPTION'   => 'nullable|string|in:tls,ssl,starttls,',
            'MAIL_FROM_ADDRESS' => 'required|email|max:255',
            'MAIL_FROM_NAME'    => 'required|string|max:100',
            'test_to'           => 'required|email|max:255',
        ]);

        // Override mail config at runtime only — does not write to .env
        config([
            'mail.default'                      => 'smtp',
            'mail.mailers.smtp.host'            => $data['MAIL_HOST'],
            'mail.mailers.smtp.port'            => (int) $data['MAIL_PORT'],
            'mail.mailers.smtp.username'        => $data['MAIL_USERNAME'] ?? null,
            'mail.mailers.smtp.password'        => $data['MAIL_PASSWORD'] ?? null,
            'mail.mailers.smtp.encryption'      => $data['MAIL_ENCRYPTION'] ?: null,
            'mail.mailers.smtp.timeout'         => 10,
            'mail.from.address'                 => $data['MAIL_FROM_ADDRESS'],
            'mail.from.name'                    => $data['MAIL_FROM_NAME'],
        ]);

        try {
            Mail::mailer('smtp')->raw(
                "This is a test email from your Portfolio CMS.\n\nIf you received this, your mail configuration is working correctly.",
                function ($message) use ($data) {
                    $message->to($data['test_to'])
                            ->subject('Test Email — Portfolio CMS');
                }
            );

            return response()->json(['success' => true, 'message' => "Test email sent to {$data['test_to']}."]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
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
