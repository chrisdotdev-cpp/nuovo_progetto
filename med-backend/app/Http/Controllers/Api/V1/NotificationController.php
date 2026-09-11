<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Notifiche in-app dell'utente autenticato. */
class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()->notifications()
            ->category($request->query('category'))
            ->when($request->boolean('unread_only'), fn ($q) => $q->unread())
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return NotificationResource::collection($notifications);
    }

    /** Badge della navbar: una sola chiamata leggera. */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->notifications()->unread()->count(),
        ]);
    }

    public function markAsRead(Request $request, AppNotification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403, 'Notifica non tua.');

        $notification->markAsRead();

        return response()->json(['message' => 'Notifica letta.', 'data' => new NotificationResource($notification)]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $request->user()->notifications()->unread()->update(['read_at' => now()]);

        return response()->json(['message' => 'Tutte le notifiche sono state lette.', 'updated' => $updated]);
    }

    public function destroy(Request $request, AppNotification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403, 'Notifica non tua.');

        $notification->delete();

        return response()->json(['message' => 'Notifica eliminata.']);
    }
}
