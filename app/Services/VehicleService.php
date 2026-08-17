<?php

namespace App\Services;

class VehicleService
{
    private SupabaseService $supabase;
    public function __construct(SupabaseService $s) { $this->supabase = $s; }

    public function updateOdometer(int $userId, ?string $username, int $km): array
    {
        $this->supabase->upsert('users', ['user_id' => $userId, 'username' => $username, 'pref_language' => 'id']);
        $ex = $this->supabase->selectSingle('vehicle_service', ['select' => 'service_interval', 'user_id' => "eq.{$userId}"]);
        $interval = $ex['service_interval'] ?? 2000;
        $next = $km + $interval;
        $this->supabase->upsert('vehicle_service', ['user_id' => $userId, 'last_km' => $km, 'service_interval' => $interval, 'next_service_km' => $next, 'updated_at' => now()->toIso8601String()]);
        return ['lastKm' => $km, 'nextServiceKm' => $next, 'interval' => $interval];
    }

    public function getServiceStatus(int $userId): ?array
    {
        $d = $this->supabase->selectSingle('vehicle_service', ['select' => 'last_km,next_service_km', 'user_id' => "eq.{$userId}"]);
        if (!$d) return null;
        return ['lastKm' => $d['last_km'], 'nextServiceKm' => $d['next_service_km'], 'remainingKm' => $d['next_service_km'] - $d['last_km']];
    }
}
