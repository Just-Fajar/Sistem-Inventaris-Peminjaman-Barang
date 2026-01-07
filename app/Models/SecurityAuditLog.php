<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAuditLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event_type',
        'user_id',
        'ip_address',
        'user_agent',
        'description',
        'metadata',
        'severity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Event types constants
     */
    const EVENT_LOGIN_SUCCESS = 'login_success';
    const EVENT_LOGIN_FAILED = 'login_failed';
    const EVENT_LOGOUT = 'logout';
    const EVENT_PASSWORD_CHANGE = 'password_change';
    const EVENT_PERMISSION_CHANGE = 'permission_change';
    const EVENT_ROLE_CHANGE = 'role_change';
    const EVENT_ACCOUNT_LOCKED = 'account_locked';
    const EVENT_ACCOUNT_UNLOCKED = 'account_unlocked';
    const EVENT_SENSITIVE_DATA_ACCESS = 'sensitive_data_access';
    const EVENT_SUSPICIOUS_ACTIVITY = 'suspicious_activity';

    /**
     * Severity levels
     */
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Get the user that owns the audit log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a security event
     */
    public static function logEvent(
        string $eventType,
        ?int $userId = null,
        ?string $description = null,
        array $metadata = [],
        string $severity = self::SEVERITY_INFO
    ): self {
        return self::create([
            'event_type' => $eventType,
            'user_id' => $userId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => $description,
            'metadata' => $metadata,
            'severity' => $severity,
        ]);
    }

    /**
     * Log failed login attempt
     */
    public static function logFailedLogin(?string $email = null): self
    {
        return self::logEvent(
            self::EVENT_LOGIN_FAILED,
            null,
            'Failed login attempt' . ($email ? " for email: {$email}" : ''),
            ['email' => $email],
            self::SEVERITY_WARNING
        );
    }

    /**
     * Log successful login
     */
    public static function logSuccessfulLogin(int $userId): self
    {
        return self::logEvent(
            self::EVENT_LOGIN_SUCCESS,
            $userId,
            'User logged in successfully',
            [],
            self::SEVERITY_INFO
        );
    }

    /**
     * Log logout
     */
    public static function logLogout(int $userId): self
    {
        return self::logEvent(
            self::EVENT_LOGOUT,
            $userId,
            'User logged out',
            [],
            self::SEVERITY_INFO
        );
    }

    /**
     * Cleanup old logs
     */
    public static function cleanup(int $days = null): int
    {
        $days = $days ?? config('security.audit_log_retention_days', 90);
        
        return self::where('created_at', '<', now()->subDays($days))->delete();
    }
}

