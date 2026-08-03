<?php
namespace App\Notifications\Contracts;

interface NotificationSender
{
    /**
     * Dispatch a notification through whichever channels are currently enabled.
     * Implementations decide channel selection/config; callers just hand over
     * the notification object.
     */
    public function send(object $notification): void;
}
