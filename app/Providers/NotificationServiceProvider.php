<?php
namespace App\Providers;

use App\Notifications\Contracts\NotificationSender;
use App\Notifications\NullNotificationSender;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationSender::class, NullNotificationSender::class);
    }
}
