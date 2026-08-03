<?php
namespace App\Notifications;

use App\Notifications\Contracts\NotificationSender;
use Illuminate\Support\Facades\Log;

/**
 * Default binding until the separate Notification System spec ships a real
 * implementation. Logs at debug level and returns — callers (User::creditWallet(),
 * User::debitWallet()) work identically whether this or a real sender is bound.
 */
class NullNotificationSender implements NotificationSender
{
    public function send(object $notification): void
    {
        Log::debug('NullNotificationSender: notification not sent (no real sender bound yet)', [
            'notification' => get_class($notification),
        ]);
    }
}
