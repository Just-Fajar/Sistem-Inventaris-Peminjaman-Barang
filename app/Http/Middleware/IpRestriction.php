<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpRestriction
{
    /**
     * IP addresses that are blocked (blacklist)
     */
    protected array $blacklist = [];

    /**
     * IP addresses that are allowed (whitelist)
     * If empty, all IPs are allowed (except blacklisted)
     */
    protected array $whitelist = [];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Load from config
        $this->blacklist = config('security.ip_blacklist', []);
        $this->whitelist = config('security.ip_whitelist', []);

        // Check blacklist
        if ($this->isBlacklisted($ip)) {
            abort(403, 'Access denied from this IP address.');
        }

        // Check whitelist (if configured)
        if (!empty($this->whitelist) && !$this->isWhitelisted($ip)) {
            abort(403, 'Access denied. IP not whitelisted.');
        }

        return $next($request);
    }

    /**
     * Check if IP is blacklisted
     */
    protected function isBlacklisted(string $ip): bool
    {
        foreach ($this->blacklist as $blocked) {
            if ($this->ipMatches($ip, $blocked)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if IP is whitelisted
     */
    protected function isWhitelisted(string $ip): bool
    {
        foreach ($this->whitelist as $allowed) {
            if ($this->ipMatches($ip, $allowed)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if IP matches pattern (supports CIDR notation)
     */
    protected function ipMatches(string $ip, string $pattern): bool
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation (e.g., 192.168.1.0/24)
        if (str_contains($pattern, '/')) {
            return $this->cidrMatch($ip, $pattern);
        }

        // Wildcard pattern (e.g., 192.168.*.*)
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace(['\*', '.'], ['.*', '\\.'], preg_quote($pattern, '/')) . '$/';
            return (bool) preg_match($regex, $ip);
        }

        return false;
    }

    /**
     * Check if IP matches CIDR range
     */
    protected function cidrMatch(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int) $mask);
        
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
