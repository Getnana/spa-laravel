<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TherapistScheduleModel;
use App\Models\TherapistModel;

class TherapistScheduleController extends Controller
{

    public function index()
    {
        $model = new TherapistScheduleModel();

        $therapistId = request()->query('therapist_id');
        $dayOfWeek = request()->query('day_of_week');
        $isAvailable = request()->query('is_available');

        $builder = $model->select('therapist_schedules.*, therapists.name as therapist_name')
            ->join('therapists', 'therapists.id = therapist_schedules.therapist_id', 'left');

        if ($therapistId !== null && $therapistId !== '') {
            $builder = $builder->where('therapist_schedules.therapist_id', $therapistId);
        }

        if ($dayOfWeek !== null && $dayOfWeek !== '') {
            $builder = $builder->where('therapist_schedules.day_of_week', $dayOfWeek);
        }

        if ($isAvailable !== null && $isAvailable !== '') {
            $builder = $builder->where('therapist_schedules.is_available', $isAvailable);
        }

        $data = $builder->orderBy('therapist_schedules.therapist_id', 'ASC')
            ->orderBy('therapist_schedules.day_of_week', 'ASC')
            ->orderBy('therapist_schedules.start_time', 'ASC')
            ->findAll();

        $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        $data = array_map(function ($schedule) use ($dayNames) {
            $schedule['day_name'] = $dayNames[$schedule['day_of_week']] ?? '';
            return $schedule;
        }, $data);

        return response()->json([
            'status'  => true,
            'message' => 'Therapist schedules retrieved successfully.',
            'data'    => $data,
        ]);
    }

    public function create()
    {
        $rules = [
            'therapist_id' => 'required|is_natural_no_zero',
            'day_of_week'  => 'required|is_natural|less_than[7]',
            'start_time'   => 'required|valid_time',
            'end_time'     => 'required|valid_time',
            'is_available' => 'permit_empty|in_list[0,1]',
        ];

        if (!$this->validate($rules)) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $this->validator->errors()->toArray(),
            ])->setStatusCode(422);
        }

        $json = $this->getJsonInput();
        $therapistId = (int) $json['therapist_id'];
        $dayOfWeek = (int) $json['day_of_week'];

        $therapistModel = new TherapistModel();
        $therapist = $therapistModel->find($therapistId);

        if (!$therapist) {
            return response()->json([
                'status'  => false,
                'message' => 'Therapist not found.',
            ])->setStatusCode(404);
        }

        if ($json['start_time'] >= $json['end_time']) {
            return response()->json([
                'status'  => false,
                'message' => 'End time must be after start time.',
            ])->setStatusCode(422);
        }

        $model = new TherapistScheduleModel();
        $existing = $model->where('therapist_id', $therapistId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if ($existing) {
            return response()->json([
                'status'  => false,
                'message' => 'Therapist already has a schedule for this day. Please update the existing schedule instead.',
            ])->setStatusCode(409);
        }

        $data = [
            'therapist_id' => $therapistId,
            'day_of_week'  => $dayOfWeek,
            'start_time'   => $json['start_time'],
            'end_time'     => $json['end_time'],
            'is_available' => $json['is_available'] ?? 1,
        ];

        $id = $model->insert($data);

        if (!$id) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to create therapist schedule.',
            ])->setStatusCode(500);
        }

        log_audit('create', 'therapist_schedules', $id, null, $data);

        $schedule = $model->find($id);
        $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $schedule['day_name'] = $dayNames[$schedule['day_of_week']] ?? '';

        return response()->json([
            'status'  => true,
            'message' => 'Therapist schedule created successfully.',
            'data'    => $schedule,
        ])->setStatusCode(201);
    }

    public function update($id = null)
    {
        $model = new TherapistScheduleModel();
        $schedule = $model->find($id);

        if (!$schedule) {
            return response()->json([
                'status'  => false,
                'message' => 'Therapist schedule not found.',
            ])->setStatusCode(404);
        }

        $json = $this->getJsonInput() ?? [];

        $rules = [
            'therapist_id' => 'permit_empty|is_natural_no_zero',
            'day_of_week'  => 'permit_empty|is_natural|less_than[7]',
            'start_time'   => 'permit_empty|valid_time',
            'end_time'     => 'permit_empty|valid_time',
            'is_available' => 'permit_empty|in_list[0,1]',
        ];

        if (!$this->validate($rules)) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $this->validator->errors()->toArray(),
            ])->setStatusCode(422);
        }

        $startTime = $json['start_time'] ?? $schedule['start_time'];
        $endTime = $json['end_time'] ?? $schedule['end_time'];

        if ($startTime >= $endTime) {
            return response()->json([
                'status'  => false,
                'message' => 'End time must be after start time.',
            ])->setStatusCode(422);
        }

        if (isset($json['day_of_week']) && $json['day_of_week'] != $schedule['day_of_week']) {
            $duplicateCheck = $model->where('therapist_id', $schedule['therapist_id'])
                ->where('day_of_week', $json['day_of_week'])
                ->first();

            if ($duplicateCheck) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Therapist already has a schedule for this day.',
                ])->setStatusCode(409);
            }
        }

        if (isset($json['therapist_id'])) {
            $therapistModel = new TherapistModel();
            $therapist = $therapistModel->find($json['therapist_id']);
            if (!$therapist) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Therapist not found.',
                ])->setStatusCode(404);
            }

            if ($json['therapist_id'] != $schedule['therapist_id']) {
                $duplicateCheck = $model->where('therapist_id', $json['therapist_id'])
                    ->where('day_of_week', $json['day_of_week'] ?? $schedule['day_of_week'])
                    ->first();

                if ($duplicateCheck) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Therapist already has a schedule for this day.',
                    ])->setStatusCode(409);
                }
            }
        }

        $oldValues = $schedule;

        $updateData = array_filter([
            'therapist_id' => $json['therapist_id'] ?? null,
            'day_of_week'  => $json['day_of_week'] ?? null,
            'start_time'   => $json['start_time'] ?? null,
            'end_time'     => $json['end_time'] ?? null,
            'is_available' => $json['is_available'] ?? null,
        ], fn($v) => $v !== null);

        if (empty($updateData)) {
            return response()->json([
                'status'  => false,
                'message' => 'No data to update.',
            ])->setStatusCode(400);
        }

        $model->update($id, $updateData);
        log_audit('update', 'therapist_schedules', $id, $oldValues, $updateData);

        $updated = $model->find($id);
        $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $updated['day_name'] = $dayNames[$updated['day_of_week']] ?? '';

        return response()->json([
            'status'  => true,
            'message' => 'Therapist schedule updated successfully.',
            'data'    => $updated,
        ]);
    }

    public function delete($id = null)
    {
        $model = new TherapistScheduleModel();
        $schedule = $model->find($id);

        if (!$schedule) {
            return response()->json([
                'status'  => false,
                'message' => 'Therapist schedule not found.',
            ])->setStatusCode(404);
        }

        log_audit('delete', 'therapist_schedules', $id, $schedule, null);
        $model->delete($id);

        return response()->json([
            'status'  => true,
            'message' => 'Therapist schedule deleted successfully.',
        ]);
    }
}
