<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminNotificationController extends Controller
{
public function unread()
{
    $user = auth()->user();
    $notifications = $user->unreadNotifications->map(function ($n) {
        return [
            'id' => $n->id,
            'data' => $n->data,
            'created_at' => $n->created_at->toDateTimeString()
        ];
    });

    return response()->json([
        'count' => $notifications->count(),
        'notifications' => $notifications
    ]);
}
  public function markAsRead($id)
    {
        $notification = Auth::user()->unreadNotifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['status' => 'success']);
    }
}
