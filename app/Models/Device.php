<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Http;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'autoread',
        'device',
        'expired',
        'name',
        'package',
        'quota',
        'status',
        'token'
    ];

    public static function syncFromApi()
    {
        $token = env('FONNTE_TOKEN');

        $response = Http::withHeaders([
            'Authorization' => $token
        ])->post('https://api.fonnte.com/get-devices');

        if (!$response->successful()) {
            return [
                'status' => false,
                'message' => 'Failed to fetch devices from API',
                'error' => $response->json()
            ];
        }

        $data = $response->json();

        if (!isset($data['status']) || !$data['status']) {
            return [
                'status' => false,
                'message' => 'API returned error',
                'error' => $data
            ];
        }

        $devices = $data['data'] ?? [];
        $updatedCount = 0;
        $deletedCount = 0;

        // Get all device IDs from API response
        $apiDeviceIds = collect($devices)->pluck('device')->toArray();

        // Delete devices that no longer exist in API
        $deletedCount = Device::whereNotIn('device', $apiDeviceIds)->delete();

        // Update or create devices from API
        foreach ($devices as $device) {
            $updated = Device::updateOrCreate(
                ['device' => $device['device']],
                [
                    'name' => $device['name'] ?? null,
                    'package' => $device['package'] ?? null,
                    'quota' => $device['quota'] ?? 0,
                    'status' => $device['status'] ?? 'inactive',
                    'token' => $device['token'] ?? null,
                    'autoread' => $device['autoread'] ?? false,
                    'expired' => $device['expired'] ?? null,
                ]
            );

            if ($updated->wasRecentlyCreated || $updated->wasChanged()) {
                $updatedCount++;
            }
        }

        return [
            'status' => true,
            'message' => 'Devices synchronized successfully',
            'total_devices' => count($devices),
            'updated_count' => $updatedCount,
            'deleted_count' => $deletedCount
        ];
    }

    public static function addDevice($name, $device, $autoread, $personal, $group)
    {
        $token = env('FONNTE_TOKEN');

        $response = Http::withHeaders([
            'Authorization' => $token
        ])->post('https://api.fonnte.com/add-device', [
            'name' => $name,
            'device' => $device,
            'autoread' => $autoread,
            'personal' => $personal,
            'group' => $group
        ]);

        if (!$response->successful()) {
            return [
                'status' => false,
                'message' => 'Failed to add device',
                'error' => $response->json()
            ];
        }

        $data = $response->json();

        if (!isset($data['status']) || !$data['status']) {
            return [
                'status' => false,
                'message' => 'API returned error',
                'error' => $data
            ];
        }

        // Create or update the device in local database
        Device::updateOrCreate(
            ['device' => $device],
            [
                'name' => $data['name'],
                'device' => $data['device'],
                'autoread' => $data['autoread'],
                'personal' => $data['personal'],
                'package' => $data['package'] ?? 'free',
                'quota' => $data['quota'] ?? 1000,
                'group' => $data['group'],
                'status' => $data['status'] == 0 ? 'connected' : 'disconnect',
                'expired' => $data['expired'] ?? now()->addDays(30),
                'token' => $data['token']
            ]
        );

        return [
            'status' => true,
            'message' => 'Device added successfully',
            'data' => $data
        ];
    }
}
