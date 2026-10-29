<?php
declare(strict_types=1);

/**
 * PRAMAAN — application configuration.
 * Server-specific values come from the .env file in the project root (see .env.example);
 * the second argument of env() is the safe default when a key is missing.
 * Branding, landing texts and the security / verification limits are edited by the
 * Super Admin (App Settings) and override the matching values below at runtime.
 */
return [
    'app' => [
        'name'        => env('APP_NAME', 'PRAMAAN'),
        'env'         => env('APP_ENV', 'production'),            // local | production
        'debug'       => (bool) env('APP_DEBUG', false),          // never true in production: errors are logged, never shown
        'timezone'    => env('APP_TIMEZONE', 'Asia/Kolkata'),
        'base_path'   => env('APP_BASE_PATH') ?: null,            // empty = auto-detect the folder
        'force_https' => (bool) env('APP_FORCE_HTTPS', false),    // true on the live server (+ HTTPS rules in .htaccess)
    ],

    'session' => [
        'name'             => env('SESSION_NAME', 'PRAMAAN_SID'),
        'idle_timeout'     => (int) env('SESSION_IDLE_MINUTES', 30) * 60,      // App Settings may change it
        'absolute_timeout' => (int) env('SESSION_LIFETIME_MINUTES', 480) * 60, // maximum session life
        'save_path'        => dirname(__DIR__) . '/storage/sessions',
    ],

    'security' => [
        'login_max_attempts'   => 5,          // App Settings
        'lockout_minutes'      => 15,         // App Settings
        'login_window_minutes' => (int) env('LOGIN_WINDOW_MINUTES', 15),
        'ip_max_attempts'      => 20,         // App Settings
        'password_min_length'  => 10,         // App Settings
    ],

    'verification' => [
        'validity_days'       => 180,         // App Settings
        'reverify_alert_days' => 15,          // App Settings
    ],

    // rows sent to the browser per sign-in (older rows stay in the database)
    'state' => [
        'audit_limit'        => (int) env('STATE_AUDIT_LIMIT', 3000),
        'notification_limit' => (int) env('STATE_NOTIFICATION_LIMIT', 300),
    ],

    'log' => [
        'path' => dirname(__DIR__) . '/storage/logs',
    ],
];
