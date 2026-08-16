<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Marquer une notification comme lue.
     */
    public function markAsRead(Request $request, string $notification)
    {
        $notif = $request->user()->notifications()->findOrFail($notification);
        $notif->markAsRead();

        return back();
    }
}
