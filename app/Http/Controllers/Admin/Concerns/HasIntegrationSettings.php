<?php

namespace App\Http\Controllers\Admin\Concerns;

/** Shared by SettingController (renders the Integrations tab inside /admin/settings)
 *  and IntegrationsController (owns the actual save/test actions) so the two never
 *  drift into two different definitions of what "configured" means per provider. */
trait HasIntegrationSettings
{
    protected function integrationSettingsProps(): array
    {
        $razorpayKeyId = env('RAZORPAY_KEY_ID', '');

        return [
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
        ];
    }
}
