<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserModel;
use App\Models\TherapistModel;
use App\Models\BookingModel;
use App\Models\TransactionModel;

class UserController extends Controller
{

    public function index()
    {
        $model = new UserModel();

        $role = request()->query('role');
        $search = request()->query('search');
        $page = (int) (request()->query('page') ?? 1);
        $perPage = (int) (request()->query('per_page') ?? 15);

        $builder = $model;

        if ($role) {
            $builder = $builder->where('role', $role);
        }

        if ($search !== null && $search !== '') {
            $builder = $builder->groupStart()
                ->like('username', $search)
                ->orLike('full_name', $search)
                ->orLike('email', $search)
                ->groupEnd();
        }

        $data = $builder->orderBy('id', 'ASC')
            ->paginate($perPage, $page);

        return response()->json([
            'status'  => true,
            'message' => 'Users retrieved successfully.',
            'data'    => $data,
            'pagination' => [
                'current_page' => $model->pager->currentPage(),
                'total_pages'  => $model->pager->lastPage(),
                'per_page'     => $model->pager->perPage(),
                'total'        => $model->pager->total(),
            ],
        ]);
    }

    public function create()
    {
        $rules = [
            'username'  => 'required|min_length[3]|max_length[50]|regex_match[/^[a-zA-Z0-9_]+$/]|is_unique[users.username]',
            'email'     => 'required|valid_email|max_length[100]|is_unique[users.email]',
            'password'  => 'required|min_length[8]',
            'full_name' => 'required|min_length[2]|max_length[100]',
            'role'      => 'required|in_list[owner,staff,therapist]',
        ];

        if (!$this->validate($rules)) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $this->validator->errors()->toArray(),
            ])->setStatusCode(422);
        }

        $model = new UserModel();
        $json = $this->getJsonInput();

        $data = [
            'username'  => $json['username'],
            'email'     => $json['email'],
            'password'  => $json['password'],
            'full_name' => $json['full_name'],
            'role'      => $json['role'],
            'is_active' => $json['is_active'] ?? 1,
        ];

        if (isset($json['therapist_id'])) {
            $therapistModel = new TherapistModel();
            if (!$therapistModel->find($json['therapist_id'])) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Therapist tidak ditemukan.',
                ])->setStatusCode(422);
            }
            $data['therapist_id'] = $json['therapist_id'];
        }

        $id = $model->insert($data);

        if (!$id) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to create user.',
                'errors'  => $model->errors(),
            ])->setStatusCode(500);
        }

        log_audit('create', 'users', $id, null, ['username' => $data['username'], 'full_name' => $data['full_name'], 'role' => $data['role']]);

        $user = $model->find($id);
        unset($user['password']);

        return response()->json([
            'status'  => true,
            'message' => 'User created successfully.',
            'data'    => $user,
        ])->setStatusCode(201);
    }

    public function show($id = null)
    {
        $model = new UserModel();
        $user = $model->find($id);

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'User not found.',
            ])->setStatusCode(404);
        }

        unset($user['password']);

        return response()->json([
            'status'  => true,
            'message' => 'User retrieved successfully.',
            'data'    => $user,
        ]);
    }

    public function update($id = null)
    {
        $model = new UserModel();
        $user = $model->find($id);

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'User not found.',
            ])->setStatusCode(404);
        }

        $json = $this->getJsonInput() ?? [];

        $rules = [
            'username'  => 'permit_empty|min_length[3]|max_length[50]|regex_match[/^[a-zA-Z0-9_]+$/]|is_unique[users.username,id,' . $id . ']',
            'email'     => 'permit_empty|valid_email|max_length[100]|is_unique[users.email,id,' . $id . ']',
            'password'  => 'permit_empty|min_length[8]',
            'full_name' => 'permit_empty|min_length[2]|max_length[100]',
            'role'      => 'permit_empty|in_list[owner,staff,therapist]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];

        if (!$this->validate($rules)) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $this->validator->errors()->toArray(),
            ])->setStatusCode(422);
        }

        $oldValues = [
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
            'is_active' => $user['is_active'],
        ];

        $updateData = array_filter([
            'username'   => $json['username'] ?? null,
            'email'      => $json['email'] ?? null,
            'password'   => $json['password'] ?? null,
            'full_name'  => $json['full_name'] ?? null,
            'role'       => $json['role'] ?? null,
            'is_active'  => $json['is_active'] ?? null,
        ], fn($v) => $v !== null);

        if (isset($json['therapist_id']) && $json['therapist_id'] !== null) {
            $therapistModel = new TherapistModel();
            if (!$therapistModel->find($json['therapist_id'])) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Therapist tidak ditemukan.',
                ])->setStatusCode(422);
            }
            $updateData['therapist_id'] = $json['therapist_id'];
        }

        if (empty($updateData)) {
            return response()->json([
                'status'  => false,
                'message' => 'No data to update.',
            ])->setStatusCode(400);
        }

        // Don't log password in audit
        $auditNewValues = $updateData;
        unset($auditNewValues['password']);

        $model->skipValidation(true)->update($id, $updateData);

        log_audit('update', 'users', $id, $oldValues, $auditNewValues);

        $updatedUser = $model->find($id);
        unset($updatedUser['password']);

        return response()->json([
            'status'  => true,
            'message' => 'User updated successfully.',
            'data'    => $updatedUser,
        ]);
    }

    public function delete($id = null)
    {
        $model = new UserModel();
        $user = $model->find($id);

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'User not found.',
            ])->setStatusCode(404);
        }

        // Prevent self-deletion
        $currentUserId = session()->get('user_id');
        if ((int) $id === (int) $currentUserId) {
            return response()->json([
                'status'  => false,
                'message' => 'Cannot delete your own account.',
            ])->setStatusCode(400);
        }

        $bookingCount = (new BookingModel())->where('created_by', $id)->countAllResults();
        $txStaffCount = (new TransactionModel())->where('staff_id', $id)->countAllResults();
        $txCreatedCount = (new TransactionModel())->where('created_by', $id)->countAllResults();

        $relations = [];
        if ($bookingCount > 0) $relations[] = "{$bookingCount} booking";
        if ($txStaffCount > 0) $relations[] = "{$txStaffCount} transaksi (staff)";
        if ($txCreatedCount > 0) $relations[] = "{$txCreatedCount} transaksi (dibuat)";

        if (!empty($relations)) {
            return response()->json([
                'status'  => false,
                'message' => 'User tidak dapat dihapus karena masih memiliki: ' . implode(', ', $relations) . '.',
            ])->setStatusCode(400);
        }

        log_audit('delete', 'users', $id, ['username' => $user['username'], 'full_name' => $user['full_name']], null);
        $model->delete($id);

        return response()->json([
            'status'  => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    public function resetPassword($id = null)
    {
        $model = new UserModel();
        $user = $model->find($id);

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'User not found.',
            ])->setStatusCode(404);
        }

        $newPassword = bin2hex(random_bytes(4));

        $model->skipValidation(true)->update($id, [
            'password' => $newPassword,
        ]);

        log_audit('update', 'users', $id, null, ['action' => 'reset_password', 'username' => $user['username']]);

        return response()->json([
            'status'  => true,
            'message' => "Password for user '{$user['username']}' has been reset.",
            'data'    => [
                'username'         => $user['username'],
                'default_password' => $newPassword,
            ],
        ]);
    }
}
