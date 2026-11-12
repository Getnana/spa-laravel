<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use App\Models\AuditLogModel;

class AuditLogController extends Controller
{

    public function index()
    {
        $model = new AuditLogModel();

        $userId = request()->query('user_id');
        $action = request()->query('action');
        $tableName = request()->query('table_name');
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');
        $page = (int) (request()->query('page') ?? 1);
        $perPage = (int) (request()->query('per_page') ?? 20);

        $builder = \App\Support\SpaQuery::forTable('audit_logs')
            ->select('audit_logs.id, audit_logs.user_name, audit_logs.action, audit_logs.table_name,
                audit_logs.record_id, audit_logs.ip_address, audit_logs.created_at');

        if ($userId) {
            $builder->where('audit_logs.user_id', $userId);
        }

        if ($action) {
            $builder->where('audit_logs.action', $action);
        }

        if ($tableName) {
            $builder->where('audit_logs.table_name', $tableName);
        }

        if ($startDate) {
            $builder->where('DATE(audit_logs.created_at) >=', $startDate);
        }

        if ($endDate) {
            $builder->where('DATE(audit_logs.created_at) <=', $endDate);
        }

        // Get total count
        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();

        // Get paginated data
        $offset = ($page - 1) * $perPage;
        $data = $builder->orderBy('audit_logs.created_at', 'DESC')
            ->limit($perPage, $offset)
            ->getResultArray();

        return response()->json([
            'status'  => true,
            'message' => 'Audit logs retrieved successfully.',
            'data'    => $data,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function show($id = null)
    {
        $model = new AuditLogModel();
        $log = $model->find($id);

        if (!$log) {
            return response()->json([
                'status'  => false,
                'message' => 'Audit log not found.',
            ])->setStatusCode(404);
        }

        // Decode JSON values
        $log['old_values'] = $log['old_values'] ? json_decode($log['old_values'], true) : null;
        $log['new_values'] = $log['new_values'] ? json_decode($log['new_values'], true) : null;

        return response()->json([
            'status'  => true,
            'message' => 'Audit log retrieved successfully.',
            'data'    => $log,
        ]);
    }
}
