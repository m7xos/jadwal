<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 20;

        $onlyUnread = filter_var($request->input('unread'), FILTER_VALIDATE_BOOLEAN);

        $query = $request->user()->notifications()->latest();
        if ($onlyUnread) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($perPage);

        return response()->json($notifications);
    }

    public function markRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        if ($notification->notifiable_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'OK']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->notifications()
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }
}
