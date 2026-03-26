<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    /**
     * Get all notifications (with filter optional)
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $query = Notification::where('user_id', $user->id);

            // 🔥 filter unread
            if ($request->has('unread')) {
                $query->where('is_read', false);
            }

            // 🔥 filter type (optional)
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            $notifications = $query->latest()->get();

            return response()->json([
                'message' => 'Success',
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil notifikasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead($id)
    {
        try {
            $notification = Notification::where('user_id', auth()->id())
                ->findOrFail($id);

            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            return response()->json([
                'message' => 'Notification marked as read'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Notification tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal update notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        try {
            Notification::where('user_id', auth()->id())
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'message' => 'Semua notifikasi ditandai sudah dibaca'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal update semua notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread count (buat badge frontend 🔥)
     */
    public function unreadCount()
    {
        try {
            $count = Notification::where('user_id', auth()->id())
                ->where('is_read', false)
                ->count();

            return response()->json([
                'message' => 'Success',
                'data' => [
                    'unread_count' => $count
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil jumlah notifikasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete notification (optional)
     */
    public function destroy($id)
    {
        try {
            $notification = Notification::where('user_id', auth()->id())
                ->findOrFail($id);

            $notification->delete();

            return response()->json([
                'message' => 'Notification berhasil dihapus'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Notification tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
