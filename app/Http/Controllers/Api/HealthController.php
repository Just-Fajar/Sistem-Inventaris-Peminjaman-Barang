<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * Check application health status
     */
    public function check()
    {
        $checks = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'services' => []
        ];

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['services']['database'] = [
                'status' => 'connected',
                'driver' => config('database.default'),
            ];
        } catch (\Exception $e) {
            $checks['status'] = 'error';
            $checks['services']['database'] = [
                'status' => 'disconnected',
                'error' => $e->getMessage(),
            ];
        }

        // Cache check
        try {
            Cache::put('health_check', true, 1);
            $cacheWorks = Cache::get('health_check');
            Cache::forget('health_check');
            
            $checks['services']['cache'] = [
                'status' => $cacheWorks ? 'working' : 'not working',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            $checks['services']['cache'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // Queue check
        $checks['services']['queue'] = [
            'status' => 'configured',
            'driver' => config('queue.default'),
        ];

        // Mail check
        $checks['services']['mail'] = [
            'status' => 'configured',
            'driver' => config('mail.default'),
        ];

        // Storage check
        $checks['services']['storage'] = [
            'status' => 'configured',
            'driver' => config('filesystems.default'),
        ];

        $httpStatus = $checks['status'] === 'ok' ? 200 : 503;

        return response()->json($checks, $httpStatus);
    }
}
