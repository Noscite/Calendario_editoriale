<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Notification\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Notifiche utente — corrisponde a notifications.py (Python).
 */
final class NotificationController extends Controller
{
    // GET /api/notifications
    public function index(Request $request): JsonResponse
    {
        $limit     = min(100, (int) $request->query('limit', 20));
        $unreadOnly = $request->boolean('unread_only', false);

        $query = Notification::where('user_id', $request->user()->id);

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'message'    => $n->message,
                'post_id'    => $n->data['post_id'] ?? null,
                'project_id' => $n->data['project_id'] ?? null,
                'brand_id'   => $n->data['brand_id'] ?? null,
                'is_read'    => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json($notifications);
    }

    // GET /api/notifications/unread-count
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    // POST /api/notifications/{id}/read
    public function markRead(int $id, Request $request): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->read_at = now();
        $notification->save();

        return response()->json(['success' => true]);
    }

    // POST /api/notifications/read-all
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    // DELETE /api/notifications/{id}
    public function destroy(int $id, Request $request): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->delete();

        return response()->json(['success' => true]);
    }

    // DELETE /api/notifications
    public function destroyAll(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true]);
    }
}
