<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Http\Resources\V1\User\NotificationResource;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * LIST NOTIFICATIONS
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Notification::where('user_id', $user->id);

        if ($request->boolean('unread')) $query->where('is_read', false);
        if ($request->filled('type')) $query->where('type', $request->type);

        $notifications = $query->latest()->paginate($request->get('per_page', 10));

        // Cukup kirim collection-nya, Base Controller akan urus sisanya!
        return $this->success(
            NotificationResource::collection($notifications),
            'List notifikasi berhasil diambil'
        );
    }

    /**
     * MARK SINGLE AS READ
     */
    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', auth()->id())
            ->findOrFail($id);

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }

        return $this->success(new NotificationResource($notification), 'Notification berhasil ditandai sebagai dibaca');
    }

    /**
     * MARK ALL AS READ
     */
    public function markAllAsRead()
    {
        DB::transaction(function () {
            Notification::where('user_id', auth()->id())
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);
        });

        return $this->success(null, 'Semua notifikasi sudah dibaca');
    }

    /**
     * UNREAD COUNT
     */
    public function unreadCount()
    {
        $count = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return $this->success([
            'unread_count' => $count
        ], 'Jumlah notifikasi unread');
    }

    /**
     * DELETE NOTIFICATION
     */
    public function destroy($id)
    {
        $notification = Notification::where('user_id', auth()->id())
            ->findOrFail($id);

        $notification->delete();

        return $this->success(null, 'Notification berhasil dihapus');
    }
}
