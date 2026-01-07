<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IP Blacklist
    |--------------------------------------------------------------------------
    |
    | IPs in this list will be blocked from accessing the application.
    | Supports exact IPs, wildcards (*), and CIDR notation.
    |
    | Examples:
    | - '192.168.1.100' (exact IP)
    | - '192.168.1.*' (wildcard)
    | - '192.168.1.0/24' (CIDR notation)
    |
    */
    'ip_blacklist' => env('IP_BLACKLIST') 
        ? explode(',', env('IP_BLACKLIST')) 
        : [],

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist
    |--------------------------------------------------------------------------
    |
    | If this list is not empty, ONLY IPs in this list can access the application.
    | Leave empty to allow all IPs (except blacklisted).
    | Supports exact IPs, wildcards (*), and CIDR notation.
    |
    */
    'ip_whitelist' => env('IP_WHITELIST') 
        ? explode(',', env('IP_WHITELIST')) 
        : [],

    /*
    |--------------------------------------------------------------------------
    | API Token Expiration
    |--------------------------------------------------------------------------
    |
    | Define how long API tokens should be valid (in minutes).
    | Set to null for tokens that never expire.
    |
    */
    'token_expiration' => env('API_TOKEN_EXPIRATION', 60 * 24 * 30), // 30 days

    /*
    |--------------------------------------------------------------------------
    | Security Audit Log Retention
    |--------------------------------------------------------------------------
    |
    | How many days to keep security audit logs before cleanup.
    |
    */
    'audit_log_retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Max Failed Login Attempts
    |--------------------------------------------------------------------------
    |
    | Maximum number of failed login attempts before temporary ban.
    |
    */
    'max_failed_login_attempts' => env('MAX_FAILED_LOGIN_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Failed Login Ban Duration
    |--------------------------------------------------------------------------
    |
    | How long to ban IP after max failed attempts (in minutes).
    |
    */
    'failed_login_ban_duration' => env('FAILED_LOGIN_BAN_DURATION', 15),
];
