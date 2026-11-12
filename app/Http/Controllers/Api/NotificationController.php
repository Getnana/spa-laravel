<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationModel;

class NotificationController extends Controller
{

    protected $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new NotificationModel();
    }

    /**
     * List notifications for current user, newest first, paginated
     */
    public function index()
    {
        $userId = session()->get('user_id');

        if (!$userId) {
            return response()->json([
                'status'  => false,
                'message' => 'User belum login.',
            ])->setStatusCode(401);
        }

        $query = $this->notificationModel->where('user_id', $userId);

        // Filter by type
        $type = request()->query('type');
        if ($type) {
            $query->where('type', $type);
        }

        // Filter by read status
        $isRead = request()->query('is_read');
        if ($isRead !== null && $isRead !== '') {
            $query->where('is_read', (int) $isRead);
        }

        $query->orderBy('id', 'DESC');

        $notifications = $query->paginate(20);
        $pager         = $this->notificationModel->pager;

        return response()->json([
            'status'  => true,
            'message' => 'Daftar notifikasi berhasil diambil.',
            'data'    => $notifications,
            'pagination' => [
                'current_page' => $pager->currentPage(),
                'total_pages'  => $pager->lastPage(),
                'per_page'     => $pager->perPage(),
                'total'        => $pager->total(),
            ],
        ]);
    }

    /**
     * Mark single notification as read
     */
    public function markRead($id = null)
    {
        $userId = session()->get('user_id');

        if (!$userId) {
            return response()->json([
                'status'  => false,
                'message' => 'User belum login.',
            ])->setStatusCode(401);
        }

        $notification = $this->notificationModel->find($id);

        if (!$notification) {
            return response()->json([
                'status'  => false,
                'message' => 'Notifikasi tidak ditemukan.',
            ])->setStatusCode(404);
        }

        // Verify notification belongs to current user
        if ($notification['user_id'] != $userId) {
            return response()->json([
                'status'  => false,
                'message' => 'Anda tidak memiliki akses ke notifikasi ini.',
            ])->setStatusCode(403);
        }

        if ($notification['is_read'] == 1) {
            return response()->json([
                'status'  => true,
                'message' => 'Notifikasi sudah ditandai sebagai dibaca.',
                'data'    => $notification,
            ]);
        }

        $this->notificationModel->update($id, ['is_read' => 1]);

        return response()->json([
            'status'  => true,
            'message' => 'Notifikasi ditandai sebagai dibaca.',
            'data'    => $this->notificationModel->find($id),
        ]);
    }

    /**
     * Mark all notifications as read for current user
     */
    public function markAllRead()
    {
        $userId = session()->get('user_id');

        if (!$userId) {
            return response()->json([
                'status'  => false,
                'message' => 'User belum login.',
            ])->setStatusCode(401);
        }

        $this->notificationModel->builder()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        return response()->json([
            'status'  => true,
            'message' => 'Semua notifikasi ditandai sebagai dibaca.',
        ]);
    }

    /**
     * Count of unread notifications for current user
     */
    public function unreadCount()
    {
        $userId = session()->get('user_id');

        if (!$userId) {
            return response()->json([
                'status'  => false,
                'message' => 'User belum login.',
            ])->setStatusCode(401);
        }

        $count = $this->notificationModel->builder()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->countAllResults();

        return response()->json([
            'status' => true,
            'data'   => [
                'unread_count' => $count,
            ],
        ]);
    }
}
