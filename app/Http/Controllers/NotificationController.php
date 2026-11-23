<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', ['items' => $request->user()->notifications()->paginate(20)]);
    }

    /** Small JSON feed for the bell in the top bar (polled every 30s). */
    public function feed(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit(6)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'message' => $n->data['message'] ?? '',
                'url' => $n->data['url'] ?? null,
                'icon' => $n->data['icon'] ?? 'bell',
                'level' => $n->data['level'] ?? 'info',
                'read' => $n->read_at !== null,
                'time' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function read(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->first()?->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true, 'message' => 'All notifications marked as read.', 'reload' => true]);
    }
}
