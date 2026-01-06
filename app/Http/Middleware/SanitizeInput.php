<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize all input data except for password fields and files
        $input = $request->except(['password', 'password_confirmation']);
        
        $sanitized = $this->sanitizeArray($input);
        
        // Replace the request input with sanitized data
        $request->merge($sanitized);
        
        return $next($request);
    }

    /**
     * Recursively sanitize array data
     *
     * @param array $data
     * @return array
     */
    protected function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                // Strip tags to prevent XSS
                $sanitized[$key] = strip_tags($value);
                
                // Trim whitespace
                $sanitized[$key] = trim($sanitized[$key]);
                
                // Remove null bytes
                $sanitized[$key] = str_replace("\0", '', $sanitized[$key]);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
}
