<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use App\Models\TransactionModel;
use App\Models\BookingModel;
use App\Models\RatingModel;
use App\Models\TherapistModel;

class ReportController extends Controller
{

    public function revenue()
    {
        $startDate = request()->query('start_date') ?? date('Y-m-01');
        $endDate = request()->query('end_date') ?? date('Y-m-d');
        $period = request()->query('period') ?? 'daily';

        // Summary
        $summary = \App\Support\SpaQuery::forTable('transactions')
            ->select('
                COALESCE(SUM(total_amount), 0) as total_gross,
                COALESCE(SUM(net_revenue), 0) as total_net,
                COALESCE(SUM(commission_amount), 0) as total_commission,
                COALESCE(SUM(tax_amount), 0) as total_tax,
                COUNT(*) as transaction_count
            ')
            ->where('payment_status', 'paid')
            ->where('DATE(paid_at) >=', $startDate)
            ->where('DATE(paid_at) <=', $endDate)
            ->getRow();

        // Data points based on period
        $dataPoints = [];
        $current = strtotime($startDate);
        $end = strtotime($endDate);

        while ($current <= $end) {
            if ($period === 'daily') {
                $day = date('Y-m-d', $current);
                $label = date('d M Y', $current);
                $next = strtotime('+1 day', $current);

                $row = \App\Support\SpaQuery::forTable('transactions')
                    ->select('COALESCE(SUM(total_amount), 0) as gross, COALESCE(SUM(net_revenue), 0) as net, COALESCE(SUM(commission_amount), 0) as commission, COALESCE(SUM(tax_amount), 0) as tax, COUNT(*) as tx_count')
                    ->where('payment_status', 'paid')
                    ->where('DATE(paid_at)', $day)
                    ->getRow();

                $dataPoints[] = [
                    'label'             => $label,
                    'gross_revenue'     => (float) $row->gross,
                    'net_revenue'       => (float) $row->net,
                    'commission'        => (float) $row->commission,
                    'tax'               => (float) $row->tax,
                    'transaction_count' => (int) $row->tx_count,
                ];
                $current = $next;
            } elseif ($period === 'weekly') {
                $weekStart = date('Y-m-d', $current);
                $weekEnd = date('Y-m-d', min(strtotime('+6 days', $current), $end));
                $label = date('d M', $current) . ' - ' . date('d M', strtotime($weekEnd));
                $next = strtotime('+7 days', $current);

                $row = \App\Support\SpaQuery::forTable('transactions')
                    ->select('COALESCE(SUM(total_amount), 0) as gross, COALESCE(SUM(net_revenue), 0) as net, COALESCE(SUM(commission_amount), 0) as commission, COALESCE(SUM(tax_amount), 0) as tax, COUNT(*) as tx_count')
                    ->where('payment_status', 'paid')
                    ->where('DATE(paid_at) >=', $weekStart)
                    ->where('DATE(paid_at) <=', $weekEnd)
                    ->getRow();

                $dataPoints[] = [
                    'label'             => $label,
                    'gross_revenue'     => (float) $row->gross,
                    'net_revenue'       => (float) $row->net,
                    'commission'        => (float) $row->commission,
                    'tax'               => (float) $row->tax,
                    'transaction_count' => (int) $row->tx_count,
                ];
                $current = $next;
            } elseif ($period === 'monthly') {
                $month = date('Y-m', $current);
                $label = date('M Y', $current);
                $monthStartDay = date('Y-m-01', $current);
                $monthEndDay = date('Y-m-t', $current);
                $next = strtotime('+1 month', $current);

                $row = \App\Support\SpaQuery::forTable('transactions')
                    ->select('COALESCE(SUM(total_amount), 0) as gross, COALESCE(SUM(net_revenue), 0) as net, COALESCE(SUM(commission_amount), 0) as commission, COALESCE(SUM(tax_amount), 0) as tax, COUNT(*) as tx_count')
                    ->where('payment_status', 'paid')
                    ->where('DATE(paid_at) >=', $monthStartDay)
                    ->where('DATE(paid_at) <=', $monthEndDay)
                    ->getRow();

                $dataPoints[] = [
                    'label'             => $label,
                    'gross_revenue'     => (float) $row->gross,
                    'net_revenue'       => (float) $row->net,
                    'commission'        => (float) $row->commission,
                    'tax'               => (float) $row->tax,
                    'transaction_count' => (int) $row->tx_count,
                ];
                $current = $next;
            }
        }

        // Monthly comparison
        $comparison = null;
        if ($period === 'monthly') {
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            if ($startMonth !== $endMonth) {
                // Current period summary
                $currentRow = \App\Support\SpaQuery::forTable('transactions')
                    ->select('COALESCE(SUM(total_amount), 0) as total_gross, COALESCE(SUM(net_revenue), 0) as total_net')
                    ->where('payment_status', 'paid')
                    ->where('DATE(paid_at) >=', $startDate)
                    ->where('DATE(paid_at) <=', $endDate)
                    ->getRow();

                // Previous period (same duration shifted back)
                $daysDiff = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24) + 1;
                $prevEnd = date('Y-m-d', strtotime('-1 day', strtotime($startDate)));
                $prevStart = date('Y-m-d', strtotime("-{$daysDiff} days", strtotime($startDate)));

                $prevRow = \App\Support\SpaQuery::forTable('transactions')
                    ->select('COALESCE(SUM(total_amount), 0) as total_gross, COALESCE(SUM(net_revenue), 0) as total_net')
                    ->where('payment_status', 'paid')
                    ->where('DATE(paid_at) >=', $prevStart)
                    ->where('DATE(paid_at) <=', $prevEnd)
                    ->getRow();

                $comparison = [
                    'current_period' => [
                        'start_date' => $startDate,
                        'end_date'   => $endDate,
                        'gross'      => (float) $currentRow->total_gross,
                        'net'        => (float) $currentRow->total_net,
                    ],
                    'previous_period' => [
                        'start_date' => $prevStart,
                        'end_date'   => $prevEnd,
                        'gross'      => (float) $prevRow->total_gross,
                        'net'        => (float) $prevRow->total_net,
                    ],
                    'change' => [
                        'gross' => $prevRow->total_gross > 0
                            ? round(((float) $currentRow->total_gross - (float) $prevRow->total_gross) / (float) $prevRow->total_gross * 100, 2)
                            : null,
                        'net' => $prevRow->total_net > 0
                            ? round(((float) $currentRow->total_net - (float) $prevRow->total_net) / (float) $prevRow->total_net * 100, 2)
                            : null,
                    ],
                ];
            }
        }

        $result = [
            'summary'    => [
                'total_gross'      => (float) $summary->total_gross,
                'total_net'        => (float) $summary->total_net,
                'total_commission' => (float) $summary->total_commission,
                'total_tax'        => (float) $summary->total_tax,
                'transaction_count' => (int) $summary->transaction_count,
            ],
            'data'       => $dataPoints,
        ];

        if ($comparison !== null) {
            $result['comparison'] = $comparison;
        }

        return response()->json([
            'status'  => true,
            'message' => 'Revenue report retrieved.',
            'data'    => $result,
        ]);
    }

    public function transactions()
    {
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');
        $status = request()->query('status');
        $paymentMethod = request()->query('payment_method');
        $page = (int) (request()->query('page') ?? 1);
        $perPage = (int) (request()->query('per_page') ?? 15);

        $builder = \App\Support\SpaQuery::forTable('transactions')
            ->select('transactions.*, customers.name as customer_name, services.name as service_name,
                therapists.name as therapist_name, staff.full_name as staff_name')
            ->join('customers', 'customers.id = transactions.customer_id', 'left')
            ->join('services', 'services.id = transactions.service_id', 'left')
            ->join('therapists', 'therapists.id = transactions.therapist_id', 'left')
            ->join('users as staff', 'staff.id = transactions.staff_id', 'left')
            ->where('transactions.deleted_at', null);

        if ($startDate) {
            $builder->where('DATE(transactions.paid_at) >=', $startDate);
        }
        if ($endDate) {
            $builder->where('DATE(transactions.paid_at) <=', $endDate);
        }
        if ($status) {
            $builder->where('transactions.payment_status', $status);
        }
        if ($paymentMethod) {
            $builder->where('transactions.payment_method', $paymentMethod);
        }

        // Summary stats
        $summaryBuilder = clone $builder;
        $summaryRow = $summaryBuilder
            ->replaceSelect('COALESCE(SUM(transactions.total_amount), 0) as total,
                COALESCE(AVG(transactions.total_amount), 0) as average,
                COALESCE(MIN(transactions.total_amount), 0) as min_amount,
                COALESCE(MAX(transactions.total_amount), 0) as max_amount,
                COALESCE(SUM(transactions.tax_amount), 0) as total_tax,
                COUNT(*) as count')
            ->getRow();

        // Paginated data
        $offset = ($page - 1) * $perPage;
        $results = $builder->orderBy('transactions.paid_at', 'DESC')
            ->limit($perPage, $offset)
            ->getResultArray();

        $totalRecords = \App\Support\SpaQuery::forTable('transactions')
            ->where('deleted_at', null)
            ->countAllResults();

        return response()->json([
            'status'  => true,
            'message' => 'Transaction report retrieved.',
            'data'    => [
                'summary' => [
                    'total'     => (float) $summaryRow->total,
                    'average'   => (float) $summaryRow->average,
                    'min'       => (float) $summaryRow->min_amount,
                    'max'       => (float) $summaryRow->max_amount,
                    'total_tax' => (float) $summaryRow->total_tax,
                    'count'     => (int) $summaryRow->count,
                ],
                'transactions' => $results,
                'pagination'   => [
                    'page'       => $page,
                    'per_page'   => $perPage,
                    'total'      => (int) $summaryRow->count,
                    'total_pages' => (int) ceil((int) $summaryRow->count / $perPage),
                ],
            ],
        ]);
    }

    public function therapistPerformance()
    {
        $startDate = request()->query('start_date') ?? date('Y-m-01');
        $endDate = request()->query('end_date') ?? date('Y-m-d');
        $therapistId = request()->query('therapist_id');

        $builder = \App\Support\SpaQuery::forTable('therapists t')
            ->select('t.id, t.name, t.specialty, t.commission_rate')
            ->where('t.deleted_at', null);

        if ($therapistId) {
            $builder->where('t.id', $therapistId);
        }

        $therapists = $builder->getResultArray();

        $performance = [];
        foreach ($therapists as $therapist) {
            $tid = $therapist['id'];

            // Services handled (completed bookings)
            $servicesHandled = \App\Support\SpaQuery::forTable('bookings')
                ->where('therapist_id', $tid)
                ->where('booking_date >=', $startDate)
                ->where('booking_date <=', $endDate)
                ->where('status', 'completed')
                ->where('deleted_at', null)
                ->countAllResults();

            // Total bookings in range (for completion rate)
            $totalBookings = \App\Support\SpaQuery::forTable('bookings')
                ->where('therapist_id', $tid)
                ->where('booking_date >=', $startDate)
                ->where('booking_date <=', $endDate)
                ->whereNotIn('status', ['cancelled'])
                ->where('deleted_at', null)
                ->countAllResults();

            // Hours worked: sum of service durations for completed bookings
            $hoursRow = \App\Support\SpaQuery::forTable('bookings b')
                ->select('COALESCE(SUM(s.duration_minutes), 0) as total_minutes')
                ->join('services s', 's.id = b.service_id', 'left')
                ->where('b.therapist_id', $tid)
                ->where('b.booking_date >=', $startDate)
                ->where('b.booking_date <=', $endDate)
                ->where('b.status', 'completed')
                ->where('b.deleted_at', null)
                ->getRow();

            $hoursWorked = round((float) $hoursRow->total_minutes / 60, 2);

            // Revenue generated
            $revenueRow = \App\Support\SpaQuery::forTable('transactions')
                ->select('COALESCE(SUM(total_amount), 0) as revenue, COALESCE(SUM(commission_amount), 0) as commission, COALESCE(SUM(tax_amount), 0) as tax')
                ->where('therapist_id', $tid)
                ->where('payment_status', 'paid')
                ->where('DATE(paid_at) >=', $startDate)
                ->where('DATE(paid_at) <=', $endDate)
                ->where('deleted_at', null)
                ->getRow();

            // Average rating
            $ratingRow = \App\Support\SpaQuery::forTable('ratings')
                ->selectAvg('rating', 'avg_rating')
                ->where('therapist_id', $tid)
                ->where('rated_at >=', $startDate . ' 00:00:00')
                ->where('rated_at <=', $endDate . ' 23:59:59')
                ->getRow();

            $avgRating = $ratingRow ? round((float) $ratingRow->avg_rating, 1) : 0.0;

            // Completion rate
            $completionRate = $totalBookings > 0
                ? round(($servicesHandled / $totalBookings) * 100, 1)
                : 0.0;

            $performance[] = [
                'therapist_id'      => $tid,
                'name'              => $therapist['name'],
                'specialty'         => $therapist['specialty'],
                'commission_rate'   => (float) $therapist['commission_rate'],
                'services_handled'  => $servicesHandled,
                'hours_worked'      => $hoursWorked,
                'revenue_generated' => (float) $revenueRow->revenue,
                'commission_earned' => (float) $revenueRow->commission,
                'tax'               => (float) $revenueRow->tax,
                'avg_rating'        => $avgRating,
                'completion_rate'   => $completionRate,
            ];
        }

        // Sort by revenue desc
        usort($performance, fn($a, $b) => $b['revenue_generated'] <=> $a['revenue_generated']);

        return response()->json([
            'status'  => true,
            'message' => 'Therapist performance report retrieved.',
            'data'    => $performance,
        ]);
    }

    public function exportPdf($type = 'revenue')
    {
        if (!in_array($type, ['revenue', 'transactions', 'therapist', 'therapist-performance'])) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid report type.',
            ])->setStatusCode(400);
        }

        [$startDate, $endDate] = $this->exportDates();

        $typeKey = ($type === 'therapist-performance') ? 'therapist' : $type;

        if ($typeKey === 'revenue') {
            $data = $this->_getRevenueData($startDate, $endDate);
            $html = $this->_renderRevenuePdf($data, $startDate, $endDate);
        } elseif ($typeKey === 'transactions') {
            $data = $this->_getTransactionData($startDate, $endDate);
            $html = $this->_renderTransactionPdf($data, $startDate, $endDate);
        } else {
            $data = $this->_getTherapistData($startDate, $endDate);
            $html = $this->_renderTherapistPdf($data, $startDate, $endDate);
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = "{$type}_report_{$startDate}_to_{$endDate}.pdf";

        return response('')
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setContent($dompdf->output());
    }

    public function exportExcel($type = 'revenue')
    {
        if (!in_array($type, ['revenue', 'transactions', 'therapist', 'therapist-performance'])) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid report type.',
            ])->setStatusCode(400);
        }

        [$startDate, $endDate] = $this->exportDates();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $typeKey = ($type === 'therapist-performance') ? 'therapist' : $type;

        if ($typeKey === 'revenue') {
            $this->_buildRevenueSheet($sheet, $startDate, $endDate);
        } elseif ($typeKey === 'transactions') {
            $this->_buildTransactionSheet($sheet, $startDate, $endDate);
        } else {
            $this->_buildTherapistSheet($sheet, $startDate, $endDate);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = "{$type}_report_{$startDate}_to_{$endDate}.xlsx";

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    private function exportDates(): array
    {
        $data = \Illuminate\Support\Facades\Validator::make([
            'start_date' => request()->query('start_date', date('Y-m-01')),
            'end_date' => request()->query('end_date', date('Y-m-d')),
        ], [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ])->validate();

        return [$data['start_date'], $data['end_date']];
    }

    // --- Private helpers for PDF ---

    private function _getRevenueData($startDate, $endDate)
    {
        return \App\Support\SpaQuery::forTable('transactions')
            ->select("DATE(paid_at) as date, SUM(total_amount) as gross, SUM(net_revenue) as net, SUM(commission_amount) as commission, SUM(tax_amount) as tax, COUNT(*) as tx_count")
            ->where('payment_status', 'paid')
            ->where('DATE(paid_at) >=', $startDate)
            ->where('DATE(paid_at) <=', $endDate)
            ->groupBy('DATE(paid_at)')
            ->orderBy('date', 'ASC')
            ->getResultArray();
    }

    private function _getTransactionData($startDate, $endDate)
    {
        return \App\Support\SpaQuery::forTable('transactions')
            ->select('transactions.transaction_code, transactions.paid_at, customers.name as customer_name,
                services.name as service_name, therapists.name as therapist_name,
                transactions.total_amount, transactions.payment_method, transactions.payment_status,
                transactions.tax_amount, transactions.commission_amount, transactions.net_revenue')
            ->join('customers', 'customers.id = transactions.customer_id', 'left')
            ->join('services', 'services.id = transactions.service_id', 'left')
            ->join('therapists', 'therapists.id = transactions.therapist_id', 'left')
            ->where('transactions.deleted_at', null)
            ->where('DATE(transactions.paid_at) >=', $startDate)
            ->where('DATE(transactions.paid_at) <=', $endDate)
            ->orderBy('transactions.paid_at', 'DESC')
            ->getResultArray();
    }

    private function _getTherapistData($startDate, $endDate)
    {
        $therapists = \App\Support\SpaQuery::forTable('therapists')
            ->select('id, name, specialty, commission_rate')
            ->where('deleted_at', null)
            ->getResultArray();

        $result = [];
        foreach ($therapists as $t) {
            $completed = \App\Support\SpaQuery::forTable('bookings')
                ->where('therapist_id', $t['id'])
                ->where('booking_date >=', $startDate)
                ->where('booking_date <=', $endDate)
                ->where('status', 'completed')
                ->where('deleted_at', null)
                ->countAllResults();

            $hoursRow = \App\Support\SpaQuery::forTable('bookings b')
                ->select('COALESCE(SUM(s.duration_minutes), 0) as total_minutes')
                ->join('services s', 's.id = b.service_id', 'left')
                ->where('b.therapist_id', $t['id'])
                ->where('b.booking_date >=', $startDate)
                ->where('b.booking_date <=', $endDate)
                ->where('b.status', 'completed')
                ->where('b.deleted_at', null)
                ->getRow();

            $revRow = \App\Support\SpaQuery::forTable('transactions')
                ->select('COALESCE(SUM(total_amount), 0) as revenue, COALESCE(SUM(commission_amount), 0) as commission, COALESCE(SUM(tax_amount), 0) as tax')
                ->where('therapist_id', $t['id'])
                ->where('payment_status', 'paid')
                ->where('DATE(paid_at) >=', $startDate)
                ->where('DATE(paid_at) <=', $endDate)
                ->where('deleted_at', null)
                ->getRow();

            $result[] = [
                'name'             => $t['name'],
                'services_handled' => $completed,
                'hours_worked'     => round((float) $hoursRow->total_minutes / 60, 2),
                'revenue'          => (float) $revRow->revenue,
                'commission'       => (float) $revRow->commission,
                'tax'              => (float) $revRow->tax,
            ];
        }
        return $result;
    }

    private function _renderRevenuePdf($data, $startDate, $endDate)
    {
        $rows = '';
        $totalGross = 0;
        $totalNet = 0;
        $totalTax = 0;
        foreach ($data as $row) {
            $totalGross += $row['gross'];
            $totalNet += $row['net'];
            $totalTax += $row['tax'];
            $rows .= '<tr>
                <td>' . e($row['date']) . '</td>
                <td>' . number_format($row['tx_count']) . '</td>
                <td>Rp ' . number_format($row['gross'], 0, ',', '.') . '</td>
                <td>Rp ' . number_format($row['tax'], 0, ',', '.') . '</td>
                <td>Rp ' . number_format($row['net'], 0, ',', '.') . '</td>
                <td>Rp ' . number_format($row['commission'], 0, ',', '.') . '</td>
            </tr>';
        }
        $rows .= '<tr style="font-weight:bold; background:#f0f0f0;">
            <td>TOTAL</td>
            <td>-</td>
            <td>Rp ' . number_format($totalGross, 0, ',', '.') . '</td>
            <td>Rp ' . number_format($totalTax, 0, ',', '.') . '</td>
            <td>Rp ' . number_format($totalNet, 0, ',', '.') . '</td>
            <td>-</td>
        </tr>';

        return '<html><head><style>
            body{font-family:Arial,sans-serif;font-size:12px}
            h2{text-align:center;margin-bottom:5px}
            .subtitle{text-align:center;color:#666;margin-bottom:20px}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #ddd;padding:8px;text-align:left}
            th{background:#4CAF50;color:white}
        </style></head><body>
        <h2>Laporan Revenue</h2>
        <p class="subtitle">Periode: ' . e($startDate) . ' s/d ' . e($endDate) . '</p>
        <table>
            <tr><th>Tanggal</th><th>Transaksi</th><th>Gross Revenue</th><th>Pajak</th><th>Net Revenue</th><th>Komisi</th></tr>
            ' . $rows . '
        </table></body></html>';
    }

    private function _renderTransactionPdf($data, $startDate, $endDate)
    {
        $rows = '';
        foreach ($data as $row) {
            $rows .= '<tr>
                <td>' . e($row['transaction_code']) . '</td>
                <td>' . e($row['paid_at']) . '</td>
                <td>' . e($row['customer_name']) . '</td>
                <td>' . e($row['service_name']) . '</td>
                <td>' . e($row['therapist_name']) . '</td>
                <td>Rp ' . number_format($row['total_amount'], 0, ',', '.') . '</td>
                <td>Rp ' . number_format($row['tax_amount'], 0, ',', '.') . '</td>
                <td>' . e($row['payment_method']) . '</td>
                <td>' . e($row['payment_status']) . '</td>
            </tr>';
        }

        return '<html><head><style>
            body{font-family:Arial,sans-serif;font-size:11px}
            h2{text-align:center;margin-bottom:5px}
            .subtitle{text-align:center;color:#666;margin-bottom:20px}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #ddd;padding:6px;text-align:left}
            th{background:#2196F3;color:white}
        </style></head><body>
        <h2>Laporan Transaksi</h2>
        <p class="subtitle">Periode: ' . e($startDate) . ' s/d ' . e($endDate) . '</p>
        <table>
            <tr><th>Kode</th><th>Tanggal</th><th>Pelanggan</th><th>Layanan</th><th>Terapis</th><th>Total</th><th>Pajak</th><th>Metode</th><th>Status</th></tr>
            ' . $rows . '
        </table></body></html>';
    }

    private function _renderTherapistPdf($data, $startDate, $endDate)
    {
        $rows = '';
        foreach ($data as $row) {
            $rows .= '<tr>
                <td>' . e($row['name']) . '</td>
                <td>' . $row['services_handled'] . '</td>
                <td>' . $row['hours_worked'] . ' jam</td>
                <td>Rp ' . number_format($row['revenue'], 0, ',', '.') . '</td>
                <td>Rp ' . number_format($row['tax'], 0, ',', '.') . '</td>
                <td>Rp ' . number_format($row['commission'], 0, ',', '.') . '</td>
            </tr>';
        }

        return '<html><head><style>
            body{font-family:Arial,sans-serif;font-size:12px}
            h2{text-align:center;margin-bottom:5px}
            .subtitle{text-align:center;color:#666;margin-bottom:20px}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #ddd;padding:8px;text-align:left}
            th{background:#FF9800;color:white}
        </style></head><body>
        <h2>Laporan Kinerja Terapis</h2>
        <p class="subtitle">Periode: ' . e($startDate) . ' s/d ' . e($endDate) . '</p>
        <table>
            <tr><th>Nama Terapis</th><th>Layanan Ditangani</th><th>Jam Kerja</th><th>Revenue</th><th>Pajak</th><th>Komisi</th></tr>
            ' . $rows . '
        </table></body></html>';
    }

    // --- Private helpers for Excel ---

    private function _buildRevenueSheet($sheet, $startDate, $endDate)
    {
        $data = $this->_getRevenueData($startDate, $endDate);
        $sheet->setCellValue('A1', 'Laporan Revenue');
        $sheet->setCellValue('A2', 'Periode: ' . $startDate . ' s/d ' . $endDate);
        $sheet->setCellValue('A4', 'Tanggal');
        $sheet->setCellValue('B4', 'Jumlah Transaksi');
        $sheet->setCellValue('C4', 'Gross Revenue');
        $sheet->setCellValue('D4', 'Pajak');
        $sheet->setCellValue('E4', 'Net Revenue');
        $sheet->setCellValue('F4', 'Komisi');

        $row = 5;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item['date']);
            $sheet->setCellValue('B' . $row, $item['tx_count']);
            $sheet->setCellValue('C' . $row, $item['gross']);
            $sheet->setCellValue('D' . $row, $item['tax']);
            $sheet->setCellValue('E' . $row, $item['net']);
            $sheet->setCellValue('F' . $row, $item['commission']);
            $row++;
        }
    }

    private function _buildTransactionSheet($sheet, $startDate, $endDate)
    {
        $data = $this->_getTransactionData($startDate, $endDate);
        $sheet->setCellValue('A1', 'Laporan Transaksi');
        $sheet->setCellValue('A2', 'Periode: ' . $startDate . ' s/d ' . $endDate);
        $sheet->setCellValue('A4', 'Kode');
        $sheet->setCellValue('B4', 'Tanggal');
        $sheet->setCellValue('C4', 'Pelanggan');
        $sheet->setCellValue('D4', 'Layanan');
        $sheet->setCellValue('E4', 'Terapis');
        $sheet->setCellValue('F4', 'Total');
        $sheet->setCellValue('G4', 'Pajak');
        $sheet->setCellValue('H4', 'Metode Pembayaran');
        $sheet->setCellValue('I4', 'Status');

        $row = 5;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item['transaction_code']);
            $sheet->setCellValue('B' . $row, $item['paid_at']);
            $sheet->setCellValue('C' . $row, $item['customer_name']);
            $sheet->setCellValue('D' . $row, $item['service_name']);
            $sheet->setCellValue('E' . $row, $item['therapist_name']);
            $sheet->setCellValue('F' . $row, $item['total_amount']);
            $sheet->setCellValue('G' . $row, $item['tax_amount']);
            $sheet->setCellValue('H' . $row, $item['payment_method']);
            $sheet->setCellValue('I' . $row, $item['payment_status']);
            $row++;
        }
    }

    private function _buildTherapistSheet($sheet, $startDate, $endDate)
    {
        $data = $this->_getTherapistData($startDate, $endDate);
        $sheet->setCellValue('A1', 'Laporan Kinerja Terapis');
        $sheet->setCellValue('A2', 'Periode: ' . $startDate . ' s/d ' . $endDate);
        $sheet->setCellValue('A4', 'Nama Terapis');
        $sheet->setCellValue('B4', 'Layanan Ditangani');
        $sheet->setCellValue('C4', 'Jam Kerja');
        $sheet->setCellValue('D4', 'Revenue');
        $sheet->setCellValue('E4', 'Pajak');
        $sheet->setCellValue('F4', 'Komisi');

        $row = 5;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item['name']);
            $sheet->setCellValue('B' . $row, $item['services_handled']);
            $sheet->setCellValue('C' . $row, $item['hours_worked']);
            $sheet->setCellValue('D' . $row, $item['revenue']);
            $sheet->setCellValue('E' . $row, $item['tax']);
            $sheet->setCellValue('F' . $row, $item['commission']);
            $row++;
        }
    }
}
