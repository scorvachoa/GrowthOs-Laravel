<?php

namespace App\Observers;

use Illuminate\Notifications\DatabaseNotification;

class NotificationObserver
{
    public function created(DatabaseNotification $notification): void
    {
        $user = $notification->notifiable;
        $count = $user->unreadNotifications()->count();

        if ($count > 5) {
            $user->unreadNotifications()->latest()->skip(5)->get()->each->delete();
        }
    }
}
