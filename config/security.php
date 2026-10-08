<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Monitoring
    |--------------------------------------------------------------------------
    | Application-layer threat detection: failed logins, lockouts, 4xx probes,
    | rate-limit hits, and suspicious scanner paths. Values here are defaults;
    | they can be overridden at runtime via Settings (group "security").
    */

    'enabled' => env('SECURITY_MONITOR', true),

    // Collapse bursts: at most one stored event per IP + type per N seconds.
    'dedupe_seconds' => 5,

    'autoban' => [
        'enabled' => true,
        'threshold' => 10,
        'window_minutes' => 5,
        'duration_minutes' => 60,
        // Never auto-ban these addresses.
        'allowlist' => ['127.0.0.1', '::1'],
    ],

    'retention_days' => 90,

    'geoip' => [
        'enabled' => env('SECURITY_GEOIP_ENABLED', true),
        'endpoint' => env('SECURITY_GEOIP_ENDPOINT', 'http://ip-api.com/json'),
        'timeout' => 2,
        'cache_days' => 7,
    ],

    /*
    | Path fragments (regex) that signal vulnerability scanners / probes.
    */
    'scanner_patterns' => [
        '\.env', '\.git', '\.aws', '\.ssh', '\.htaccess', '\.htpasswd',
        'wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'xmlrpc\.php',
        'phpmyadmin', 'pma', 'adminer', 'db\.php', 'mysql',
        'phpinfo', 'eval\(', 'shell', 'c99', 'r57', 'boaform', 'gponform', 'setup\.cgi',
        '\.\./', 'etc/passwd', 'proc/self', 'cgi-bin',
        'config\.php', 'actuator', 'telescope',
        '\.(sql|bak|old|swp|swo|zip|tar|gz|rar|env)$',
        '/vendor/', 'php\.ini', 'docker-compose', '\.DS_Store',
    ],

    /*
    | Path prefixes that are never recorded (assets, framework internals).
    */
    'skip_paths' => ['build/', 'storage/', 'fonts/', 'docs/', '_scramble', 'up', 'favicon.ico', 'vendor/'],
];
