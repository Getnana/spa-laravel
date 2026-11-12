<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use App\Models\BookingModel;
use App\Models\TransactionModel;
use App\Models\TherapistModel;
use App\Models\CustomerModel;
use App\Models\ServiceModel;

class DashboardController extends Controller
{

    public function summary()
    {
        $today = date('Y-m-d');

        // Total revenue today from paid transactions
        $revenueRow = \App\Support\SpaQuery::forTable('transactions')
            ->select('COALESCE(SUM(total_amount), 0) as total_revenue')
            ->where('payment_status', 'paid')
            ->where('DATE(paid_at)', $today)
            ->where('deleted_at', null)
            ->getRow();

        // Total transactions today (paid)
        $txCount = \App\Support\SpaQuery::forTable('transactions')
            ->where('payment_status', 'paid')
            ->where('DATE(paid_at)', $today)
            ->where('deleted_at', null)
            ->countAllResults();

        // Active bookings today (confirmed + in_progress)
        $activeBookings = \App\Support\SpaQuery::forTable('bookings')
            ->where('booking_date', $today)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->where('deleted_at', null)
            ->countAllResults();

        // Pending bookings today
        $pendingBookings = \App\Support\SpaQuery::forTable('bookings')
            ->where('booking_date', $today)
            ->where('status', 'pending')
            ->where('deleted_at', null)
            ->countAllResults();

        // Total customers
        $customerModel = new CustomerModel();
        $totalCustomers = $customerModel->countAllResults();

        // Active therapists
        $therapistModel = new TherapistModel();
        $activeTherapists = $therapistModel->where('status', 'active')->countAllResults();

        return response()->json([
            'status'  => true,
            'message' => 'Dashboard summary retrieved.',
            'data'    => [
                'total_revenue'     => (float) $revenueRow->total_revenue,
                'total_transactions' => $txCount,
                'active_bookings'   => $activeBookings,
                'total_customers'   => $totalCustomers,
                'active_therapists' => $activeTherapists,
                'pending_bookings'  => $pendingBookings,
            ],
        ]);
    }

    public function revenueChart($period = 'daily')
    {
        $data = [];

        if ($period === 'daily') {
            // Last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $label = date('d M', strtotime($date));

                $row = \App\Support\SpaQuery::forTable('transactions')
                    ->select('COALESCE(SUM(total_amount), 0) as gross, COALESCE(SUM(net_revenue), 0) as net')
                    ->where('payment_status', 'paid')
                    ->where('DATE(paid_at)', $date)
                    ->where('deleted_at', null)
                    ->getRow();

                $data[] = [
                    'label'        => $label,
                    'gross_revenue' => (float) $row->gross,
                    'net_revenue'   => (float) $row->net,
                ];
            }
        } elseif ($period === 'weekly') {
            // Last 4 weeks
            for ($i = 3; $i >= 0; $i--) {
                $weekStart = date('Y-m-d', strtotime("monday this week -{$i} weeks"));
                $weekEnd = date('Y-m-d', strtotime("sunday this week -{$i} weeks"));
                $label = date('d M', strtotime($weekStart)) . ' - ' . date('d M', strtotime($weekEnd));

                $row = \App\Support\SpaQuery::forTable('transactions')
                    ->select('COALESCE(SUM(total_amount), 0) as gross, COALESCE(SUM(net_revenue), 0) as net')
                    ->where('payment_status', 'paid')
                    ->where('DATE(paid_at) >=', $weekStart)
                    ->where('DATE(paid_at) <=', $weekEnd)
                    ->where('deleted_at', null)
                    ->getRow();

                $data[] = [
                    'label'        => $label,
                    'gross_revenue' => (float) $row->gross,
                    'net_revenue'   => (float) $row->net,
                ];
            }
        } elseif ($period === 'monthly') {
            // Last 12 months
            for ($i = 11; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-{$i} months"));
                $label = date('M Y', strtotime($month));

                $row = \App\Support\SpaQuery::forTable('transactions')
                    ->select('COALESCE(SUM(total_amount), 0) as gross, COALESCE(SUM(net_revenue), 0) as net')
                    ->where('payment_status', 'paid')
                    ->like('paid_at', $month, 'after')
                    ->where('deleted_at', null)
                    ->getRow();

                $data[] = [
                    'label'        => $label,
                    'gross_revenue' => (float) $row->gross,
                    'net_revenue'   => (float) $row->net,
                ];
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'Revenue chart data retrieved.',
            'data'    => $data,
        ]);
    }

    public function bookingStats()
    {
        $startDate = request()->query('start_date') ?? date('Y-m-d');
        $endDate = request()->query('end_date') ?? date('Y-m-d');

        // Count by status
        $statuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show', 'awaiting_payment'];
        $statusCounts = [];
        foreach ($statuses as $status) {
            $count = \App\Support\SpaQuery::forTable('bookings')
                ->where('booking_date >=', $startDate)
                ->where('booking_date <=', $endDate)
                ->where('status', $status)
                ->where('deleted_at', null)
                ->countAllResults();
            $statusCounts[$status] = $count;
        }

        // Count by source
        $sources = ['walk_in', 'whatsapp', 'phone', 'online'];
        $sourceCounts = [];
        foreach ($sources as $source) {
            $count = \App\Support\SpaQuery::forTable('bookings')
                ->where('booking_date >=', $startDate)
                ->where('booking_date <=', $endDate)
                ->where('source', $source)
                ->where('deleted_at', null)
                ->countAllResults();
            $sourceCounts[$source] = $count;
        }

        return response()->json([
            'status'  => true,
            'message' => 'Booking stats retrieved.',
            'data'    => [
                'by_status' => $statusCounts,
                'by_source' => $sourceCounts,
            ],
        ]);
    }

    public function popularServices($limit = 5)
    {
        $limit = (int) $limit;

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $rows = \App\Support\SpaQuery::forTable('bookings')
            ->select('services.id, services.name, COUNT(bookings.id) as booking_count')
            ->join('services', 'services.id = bookings.service_id', 'left')
            ->where('bookings.booking_date >=', $monthStart)
            ->where('bookings.booking_date <=', $monthEnd)
            ->where('bookings.deleted_at', null)
            ->where('bookings.service_id IS NOT NULL')
            ->groupBy('services.id, services.name')
            ->orderBy('booking_count', 'DESC')
            ->limit($limit)
            ->getResultArray();

        return response()->json([
            'status'  => true,
            'message' => 'Popular services retrieved.',
            'data'    => $rows,
        ]);
    }
}
