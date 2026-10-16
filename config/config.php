<?php
declare(strict_types=1);

/**
 * PRAMAN — application configuration.
 * Never commit real production secrets in this file; override per server.
 */
return [
    'app' => [
        'name'        => 'PRAMAN',
        'full_name'   => 'Property Records & Auction-readiness Multi-section Assessment Network',
        'org'         => 'Kota Development Authority',
        'org_short'   => 'KDA',
        'env'         => 'local',            // local | production
        'debug'       => false,              // never true in production; errors are always logged, never shown
        'timezone'    => 'Asia/Kolkata',
        // Leave null to auto-detect (works for http://localhost/PARMAM/ and for a vhost pointing to /public).
        'base_path'   => null,
        'force_https' => false,              // set true in production (also enable the HTTPS rule in .htaccess)
    ],

    'session' => [
        'name'             => 'PRAMAN_SID',
        'idle_timeout'     => 1800,          // seconds of inactivity before auto-logout (30 min)
        'absolute_timeout' => 28800,         // max session life (8 hours)
        'save_path'        => dirname(__DIR__) . '/storage/sessions',
    ],

    'security' => [
        'login_max_attempts'   => 5,         // per username within the window
        'login_window_minutes' => 15,
        'lockout_minutes'      => 15,
        'ip_max_attempts'      => 20,        // per IP within the window (any username)
        'password_min_length'  => 10,
        // false = users go straight to their dashboard after login (no forced change on first login).
        // Set true to force users with a default / reset password to change it before using the system.
        'force_password_change' => false,
    ],

    // Panels that can log in.
    'enabled_panels' => ['SUPER_ADMIN', 'DATA_ENTRY', 'OIC', 'ROLE_USER'],

    'pagination' => [
        'default_per_page' => 10,
        'allowed_per_page' => [10, 25, 50],
    ],

    'verification' => [
        'validity_days' => 180,              // round validity (re-verification phase)
        // TEMPORARY: lets OIC simulate role-user approve/reject until the Role User panel is built.
        // Set to false (or delete the route) when the Role User response screen goes live.
        'simulate_enabled' => false,
    ],

    'log' => [
        'path' => dirname(__DIR__) . '/storage/logs',
    ],
];
