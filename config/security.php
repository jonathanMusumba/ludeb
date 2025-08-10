<?php
/**
 * Security Configuration File

 */

return [
    // Database Configuration
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? '',
        'database' => $_ENV['DB_NAME'] ?? 'ludeb',
        'charset' => 'utf8mb4',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'ssl' => $_ENV['DB_SSL'] ?? false,
    ],

    // Rate Limiting Configuration
    'rate_limiting' => [
        'max_attempts' => (int)($_ENV['RATE_LIMIT_MAX_ATTEMPTS'] ?? 5),
        'time_window' => (int)($_ENV['RATE_LIMIT_TIME_WINDOW'] ?? 900), // 15 minutes
        'progressive_delays' => [
            3 => 60,    // 1 minute after 3 attempts
            5 => 300,   // 5 minutes after 5 attempts
            7 => 900,   // 15 minutes after 7 attempts
            10 => 1800, // 30 minutes after 10 attempts
        ],
        'cleanup_interval' => 7 * 24 * 3600, // 7 days
    ],

    // Account Lockout Configuration
    'account_lockout' => [
        'max_attempts' => (int)($_ENV['LOCKOUT_MAX_ATTEMPTS'] ?? 5),
        'lockout_duration' => (int)($_ENV['LOCKOUT_DURATION'] ?? 1800), // 30 minutes
        'progressive_lockout' => true,
        'lockout_durations' => [
            5 => 1800,  // 30 minutes
            7 => 3600,  // 1 hour
            10 => 7200, // 2 hours
            15 => 86400, // 24 hours
        ],
        'auto_unlock' => true,
    ],

    // Session Security Configuration
    'session' => [
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 28800), // 8 hours
        'regenerate_interval' => (int)($_ENV['SESSION_REGENERATE'] ?? 300), // 5 minutes
        'max_concurrent_sessions' => (int)($_ENV['MAX_SESSIONS'] ?? 5),
        'cookie_secure' => $_ENV['SESSION_SECURE'] ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'fingerprint_check' => true,
        'ip_validation' => $_ENV['SESSION_IP_VALIDATION'] ?? true,
    ],

    // Password Security Configuration
    'password' => [
        'min_length' => (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 8),
        'require_uppercase' => $_ENV['PASSWORD_REQUIRE_UPPERCASE'] ?? true,
        'require_lowercase' => $_ENV['PASSWORD_REQUIRE_LOWERCASE'] ?? true,
        'require_numbers' => $_ENV['PASSWORD_REQUIRE_NUMBERS'] ?? true,
        'require_symbols' => $_ENV['PASSWORD_REQUIRE_SYMBOLS'] ?? true,
        'history_count' => (int)($_ENV['PASSWORD_HISTORY_COUNT'] ?? 12),
        'expiry_days' => (int)($_ENV['PASSWORD_EXPIRY_DAYS'] ?? 90),
        'hash_algorithm' => PASSWORD_DEFAULT,
        'hash_options' => [
            'cost' => (int)($_ENV['PASSWORD_HASH_COST'] ?? 12),
        ],
    ],

    // CSRF Protection Configuration
    'csrf' => [
        'token_lifetime' => (int)($_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600), // 1 hour
        'regenerate_on_use' => false,
        'field_name' => 'csrf_token',
        'header_name' => 'X-CSRF-Token',
    ],

    // Logging Configuration
    'logging' => [
        'enabled' => $_ENV['SECURITY_LOGGING'] ?? true,
        'log_file' => $_ENV['SECURITY_LOG_FILE'] ?? 'logs/security.log',
        'log_level' => $_ENV['LOG_LEVEL'] ?? 'info',
        'max_file_size' => $_ENV['LOG_MAX_SIZE'] ?? '10MB',
        'rotate_files' => (int)($_ENV['LOG_ROTATE_FILES'] ?? 10),
        'log_successful_logins' => $_ENV['LOG_SUCCESS'] ?? true,
        'log_failed_logins' => $_ENV['LOG_FAILURES'] ?? true,
        'log_ip_changes' => $_ENV['LOG_IP_CHANGES'] ?? true,
    ],

    // IP Filtering Configuration
    'ip_filtering' => [
        'enabled' => $_ENV['IP_FILTERING_ENABLED'] ?? false,
        'whitelist_only' => $_ENV['IP_WHITELIST_ONLY'] ?? false,
        'whitelist' => array_filter(explode(',', $_ENV['IP_WHITELIST'] ?? '')),
        'blacklist' => array_filter(explode(',', $_ENV['IP_BLACKLIST'] ?? '')),
        'auto_blacklist' => [
            'enabled' => $_ENV['AUTO_BLACKLIST'] ?? true,
            'threshold' => (int)($_ENV['AUTO_BLACKLIST_THRESHOLD'] ?? 20),
            'duration' => (int)($_ENV['AUTO_BLACKLIST_DURATION'] ?? 86400), // 24 hours
        ],
        'geo_blocking' => [
            'enabled' => $_ENV['GEO_BLOCKING'] ?? false,
            'allowed_countries' => array_filter(explode(',', $_ENV['ALLOWED_COUNTRIES'] ?? '')),
            'blocked_countries' => array_filter(explode(',', $_ENV['BLOCKED_COUNTRIES'] ?? '')),
        ],
    ],

    // Device Trust Configuration
    'device_trust' => [
        'enabled' => $_ENV['DEVICE_TRUST'] ?? false,
        'trust_duration' => (int)($_ENV['DEVICE_TRUST_DURATION'] ?? 2592000), // 30 days
        'max_trusted_devices' => (int)($_ENV['MAX_TRUSTED_DEVICES'] ?? 10),
        'fingerprinting' => [
            'user_agent' => true,
            'screen_resolution' => true,
            'timezone' => true,
            'language' => true,
            'plugins' => true,
        ],
    ],

    // Two-Factor Authentication Configuration
    'two_factor' => [
        'enabled' => $_ENV['2FA_ENABLED'] ?? false,
        'required_roles' => array_filter(explode(',', $_ENV['2FA_REQUIRED_ROLES'] ?? 'System Admin')),
        'issuer_name' => $_ENV['2FA_ISSUER'] ?? 'Results Management System',
        'code_length' => (int)($_ENV['2FA_CODE_LENGTH'] ?? 6),
        'time_window' => (int)($_ENV['2FA_TIME_WINDOW'] ?? 30),
        'backup_codes' => [
            'count' => (int)($_ENV['2FA_BACKUP_CODES'] ?? 10),
            'length' => (int)($_ENV['2FA_BACKUP_LENGTH'] ?? 8),
        ],
    ],

    // Security Headers Configuration
    'headers' => [
        'enabled' => $_ENV['SECURITY_HEADERS'] ?? true,
        'csp' => $_ENV['CSP_POLICY'] ?? "default-src 'self'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com;",
        'hsts' => $_ENV['HSTS_POLICY'] ?? 'max-age=31536000; includeSubDomains; preload',
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
    ],

    // Monitoring and Alerting Configuration
    'monitoring' => [
        'enabled' => $_ENV['MONITORING_ENABLED'] ?? true,
        'alert_email' => $_ENV['ALERT_EMAIL'] ?? 'security@yourdomain.com',
        'alert_thresholds' => [
            'failed_logins_per_minute' => (int)($_ENV['ALERT_FAILED_LOGINS'] ?? 10),
            'new_ips_per_hour' => (int)($_ENV['ALERT_NEW_IPS'] ?? 50),
            'account_lockouts_per_hour' => (int)($_ENV['ALERT_LOCKOUTS'] ?? 5),
            'suspicious_activities' => (int)($_ENV['ALERT_SUSPICIOUS'] ?? 3),
        ],
        'real_time_alerts' => $_ENV['REALTIME_ALERTS'] ?? false,
        'daily_reports' => $_ENV['DAILY_REPORTS'] ?? true,
    ],

    // Maintenance Configuration
    'maintenance' => [
        'cleanup_enabled' => $_ENV['CLEANUP_ENABLED'] ?? true,
        'cleanup_schedule' => $_ENV['CLEANUP_SCHEDULE'] ?? 'daily',
        'retain_logs_days' => (int)($_ENV['RETAIN_LOGS_DAYS'] ?? 90),
        'retain_attempts_days' => (int)($_ENV['RETAIN_ATTEMPTS_DAYS'] ?? 7),
        'retain_sessions_days' => (int)($_ENV['RETAIN_SESSIONS_DAYS'] ?? 30),
        'vacuum_database' => $_ENV['VACUUM_DATABASE'] ?? true,
    ],

    // API Security Configuration
    'api' => [
        'rate_limit' => (int)($_ENV['API_RATE_LIMIT'] ?? 100), // requests per hour
        'require_authentication' => $_ENV['API_AUTH_REQUIRED'] ?? true,
        'allowed_methods' => array_filter(explode(',', $_ENV['API_ALLOWED_METHODS'] ?? 'GET,POST')),
        'cors' => [
            'enabled' => $_ENV['API_CORS_ENABLED'] ?? false,
            'allowed_origins' => array_filter(explode(',', $_ENV['API_CORS_ORIGINS'] ?? '')),
            'allowed_methods' => array_filter(explode(',', $_ENV['API_CORS_METHODS'] ?? 'GET,POST')),
            'allowed_headers' => array_filter(explode(',', $_ENV['API_CORS_HEADERS'] ?? 'Content-Type,Authorization')),
        ],
    ],

    // Environment Configuration
    'environment' => [
        'debug_mode' => $_ENV['DEBUG_MODE'] ?? false,
        'development_ips' => array_filter(explode(',', $_ENV['DEVELOPMENT_IPS'] ?? '127.0.0.1')),
        'ssl_required' => $_ENV['SSL_REQUIRED'] ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'timezone' => $_ENV['TIMEZONE'] ?? 'UTC',
    ],

    // Advanced Security Features
    'advanced' => [
        'honeypot_enabled' => $_ENV['HONEYPOT_ENABLED'] ?? true,
        'bot_detection' => $_ENV['BOT_DETECTION'] ?? true,
        'captcha' => [
            'enabled' => $_ENV['CAPTCHA_ENABLED'] ?? false,
            'provider' => $_ENV['CAPTCHA_PROVIDER'] ?? 'recaptcha',
            'site_key' => $_ENV['CAPTCHA_SITE_KEY'] ?? '',
            'secret_key' => $_ENV['CAPTCHA_SECRET_KEY'] ?? '',
            'threshold' => (int)($_ENV['CAPTCHA_THRESHOLD'] ?? 3), // Show after X failed attempts
        ],
        'machine_learning' => [
            'enabled' => $_ENV['ML_DETECTION'] ?? false,
            'anomaly_detection' => $_ENV['ML_ANOMALY'] ?? false,
            'behavioral_analysis' => $_ENV['ML_BEHAVIORAL'] ?? false,
        ],
    ],
];