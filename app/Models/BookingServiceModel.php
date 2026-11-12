<?php

namespace App\Models;

class BookingServiceModel extends BaseModel
{
    protected $table            = 'booking_services';
    protected $primaryKey       = 'id';
    protected $useTimestamps    = false;
    protected $allowedFields    = ['booking_id', 'service_id', 'created_at'];
    protected $validationRules  = [
        'booking_id' => 'required|is_natural_no_zero',
        'service_id' => 'required|is_natural_no_zero',
    ];

    public function syncServices(int $bookingId, array $serviceIds): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($bookingId, $serviceIds): void {
            $this->builder()->where('booking_id', $bookingId)->delete();

            foreach ($serviceIds as $serviceId) {
                $inserted = $this->insert([
                    'booking_id' => $bookingId,
                    'service_id' => (int) $serviceId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                if ($inserted === false) {
                    throw new \RuntimeException('Layanan booking tidak valid.');
                }
            }
        });
    }

    public function getServicesByBooking(int $bookingId): array
    {
        return $this->select('booking_services.*, services.name as service_name, services.duration_minutes, services.price')
            ->join('services', 'services.id = booking_services.service_id', 'left')
            ->where('booking_id', $bookingId)
            ->findAll();
    }
}
