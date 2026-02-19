<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, $userId)
    {
        if (! $this->canAccessUser($request, (int) $userId)) {
            return response()->json([
                'message' => 'Forbidden. You can only access your own notifications.',
            ], 403);
        }

        $notifications = Notification::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($notifications);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'title' => 'nullable|string|max:255',
            'message' => 'nullable|string',
            'type' => 'nullable|string|in:payment,order,promotion,system',
            'is_read' => 'nullable|boolean',
        ]);

        $targetUserId = (int) ($validated['user_id'] ?? $request->user()->id);

        if (! $this->canAccessUser($request, $targetUserId)) {
            return response()->json([
                'message' => 'Forbidden. You can only create your own notifications.',
            ], 403);
        }

        $notification = Notification::createSafe([
            ...$validated,
            'user_id' => $targetUserId,
            'type' => $validated['type'] ?? 'system',
        ]);

        return response()->json($notification, 201);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        if (! $this->canAccessUser($request, (int) $notification->user_id)) {
            return response()->json([
                'message' => 'Forbidden. You can only update your own notifications.',
            ], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json($notification);
    }

    public function destroy(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        if (! $this->canAccessUser($request, (int) $notification->user_id)) {
            return response()->json([
                'message' => 'Forbidden. You can only delete your own notifications.',
            ], 403);
        }

        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }

    private function canAccessUser(Request $request, int $targetUserId): bool
    {
        $currentUser = $request->user();

        return $currentUser !== null && ($currentUser->is_admin || (int) $currentUser->id === $targetUserId);
    }
}
