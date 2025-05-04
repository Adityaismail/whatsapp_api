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

    public static function requestOtp($device)
    {
        $deviceModel = Device::where('device', $device)->first();

        if (!$deviceModel || !$deviceModel->token) {
            return [
                'status' => false,
                'message' => 'Device not found or token is missing'
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => $deviceModel->token
        ])->post('https://api.fonnte.com/delete-device', [
            'otp' => ''
        ]);

        if (!$response->successful()) {
            return [
                'status' => false,
                'message' => 'Failed to request OTP',
                'error' => $response->json()
            ];
        }

        $data = $response->json();

        // Check if this is a cooldown response
        if (isset($data['reason']) && str_contains(strtolower($data['reason']), 'please wait')) {
            $seconds = (int) filter_var($data['reason'], FILTER_SANITIZE_NUMBER_INT);
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;

            $timeMessage = '';
            if ($minutes > 0) {
                $timeMessage .= "{$minutes} minute" . ($minutes > 1 ? 's' : '');
            }
            if ($remainingSeconds > 0) {
                if ($minutes > 0) {
                    $timeMessage .= ' and ';
                }
                $timeMessage .= "{$remainingSeconds} second" . ($remainingSeconds > 1 ? 's' : '');
            }

            return [
                'status' => false,
                'message' => "Please wait {$timeMessage} before requesting new OTP",
                'error' => $data,
                'cooldown' => true,
                'cooldown_seconds' => $seconds,
                'cooldown_minutes' => $minutes,
                'cooldown_remaining_seconds' => $remainingSeconds
            ];
        }

        if (isset($data['status']) && $data['status'] === true) {
            return [
                'status' => true,
                'message' => 'OTP has been sent',
                'data' => $data
            ];
        }

        return [
            'status' => false,
            'message' => $data['reason'] ?? 'Failed to request OTP',
            'error' => $data
        ];
    }

    public static function deleteDevice($device, $data)
    {
        $deviceModel = Device::where('device', $device)->first();

        if (!$deviceModel) {
            return [
                'status' => false,
                'message' => 'Device not found'
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $deviceModel->token // Sesuai contoh, tanpa Bearer
            ])->asForm() // Menggunakan format form-urlencoded
            ->post('https://api.fonnte.com/delete-device', [
                'otp' => $data['otp'] // Mengambil nilai OTP dari input form
            ]);

            $responseData = $response->json();

            // Debugging - bisa dihapus setelah testing
            \Log::debug('Fonnte API Response:', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            if ($response->successful() && ($responseData['status'] ?? false)) {
                return [
                    'status' => true,
                    'message' => $responseData['message'] ?? 'Device deleted successfully',
                    'data' => $responseData
                ];
            }

            return [
                'status' => false,
                'message' => $responseData['reason'] ?? ($responseData['message'] ?? 'Failed to delete device'),
                'error' => $responseData
            ];

        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'API request failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
}

