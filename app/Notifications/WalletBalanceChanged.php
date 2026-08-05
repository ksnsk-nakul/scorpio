<?php
namespace App\Notifications;

use App\Models\User;
use App\Models\WalletTransaction;

/**
 * Plain data object, not a Laravel Notification subclass yet — the separate
 * Notification System spec decides the real channel/delivery shape. This is
 * just what NotificationSender::send() receives today.
 */
class WalletBalanceChanged
{
    public function __construct(
        public User $user,
        public WalletTransaction $transaction,
    ) {}
}
