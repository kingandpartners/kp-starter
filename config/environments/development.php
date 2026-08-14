<?php

/**
 * Configuration overrides for WP_ENV === 'development'
 */

use Roots\WPConfig\Config;

use function Env\env;

Config::define('SAVEQUERIES', true);
Config::define('WP_DEBUG', true);
// Errors go to the log, not the page. A displayed error corrupts the JSON of
// `nuxtpress` REST responses, and it makes WordPress add the `php-error` admin
// body class, which reserves 2em of blank space above #adminmenuback.
Config::define('WP_DEBUG_DISPLAY', env('WP_DEBUG_DISPLAY') ?? false);
// Logs live in a `logs/` directory, matching beta. The whole repo is bind-mounted
// into the container, so these are tailable from the host.
$log_dir = Config::get('WP_CONTENT_DIR') . '/logs';
Config::define('WP_DEBUG_LOG', env('WP_DEBUG_LOG') ?? $log_dir . '/debug.log');
// Deprecations are routed away from the main log by features/runtime/route-error-logs.php.
Config::define('WP_DEPRECATION_LOG', env('WP_DEPRECATION_LOG') ?? $log_dir . '/deprecation.log');
unset($log_dir);
Config::define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
Config::define('SCRIPT_DEBUG', true);
Config::define('DISALLOW_INDEXING', true);

ini_set('display_errors', Config::get('WP_DEBUG_DISPLAY') ? '1' : '0');

// Enable plugin and theme updates and installation from the admin
Config::define('DISALLOW_FILE_MODS', false);
