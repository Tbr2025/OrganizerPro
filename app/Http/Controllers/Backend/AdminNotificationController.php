<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminNotificationController extends Controller
{

    public function index(Request $request)
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate(10);

        if ($request->ajax()) {
            return view('backend.pages.notifications.partials.list', compact('notifications'))->render();
        }

        $breadcrumbs = [
            'title' => __('Notifications'),
        ];

        return view('backend.pages.notifications.index', compact('notifications', 'breadcrumbs'));
    }
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
    public function markAsUnread($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->update(['read_at' => null]);

        return response()->json(['status' => 'success']);
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['status' => 'success']);
    }
}
