<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Process request
        $response = $next($request);
        
        // Calculate duration
        $duration = microtime(true) - $startTime;
        
        // Log only API requests
        if ($request->is('api/*')) {
            $logData = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => auth()->id() ?? null,
                'user_agent' => $request->userAgent(),
                'duration_ms' => round($duration * 1000, 2),
                'status' => $response->getStatusCode(),
                'memory_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
            ];

            // Add request body for non-GET requests (exclude sensitive data)
            if (!$request->isMethod('GET')) {
                $logData['request_body'] = $this->sanitizeData($request->except([
                    'password',
                    'password_confirmation',
                    'current_password',
                    'token',
                ]));
            }

            // Add query parameters for GET requests
            if ($request->isMethod('GET') && $request->query()) {
                $logData['query_params'] = $request->query();
            }

            // Log based on status code
            $level = $response->getStatusCode() >= 500 ? 'error' : 
                     ($response->getStatusCode() >= 400 ? 'warning' : 'info');

            Log::channel('api')->{$level}('API Request', $logData);
        }

        return $response;
    }

    /**
     * Sanitize sensitive data
     */
    private function sanitizeData($data)
    {
        if (is_array($data)) {
            return array_map(function ($value) {
                if (is_string($value) && strlen($value) > 1000) {
                    return substr($value, 0, 1000) . '... (truncated)';
                }
                return $value;
            }, $data);
        }
        return $data;
    }
}
