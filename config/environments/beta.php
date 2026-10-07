<?php

/**
 * Configuration overrides for WP_ENV === 'beta'
 */

use Roots\WPConfig\Config;

/**
 * You should try to keep beta as close to production as possible. However,
 * should you need to, you can always override production configuration values
 * with `Config::define`.
 *
 * Example: `Config::define('WP_DEBUG', true);`
 * Example: `Config::define('DISALLOW_FILE_MODS', false);`
 */

Config::define('SAVEQUERIES', true);
Config::define('WP_DEBUG', true);
Config::define('WP_DEBUG_DISPLAY', false);
Config::define('WP_DEBUG_LOG', '/var/app/current/wordpress/web/app/logs/debug.log');
// Deprecations are routed away from the main log by features/runtime/route-error-logs.php.
Config::define('WP_DEPRECATION_LOG', '/var/app/current/wordpress/web/app/logs/deprecation.log');
Config::define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
Config::define('SCRIPT_DEBUG', true);
Config::define('DISALLOW_INDEXING', true);

// Matches WP_DEBUG_DISPLAY above. wp_debug_mode() forces this to '0' regardless
// once the constant is false, so setting '1' here only ever read as intent.
ini_set('display_errors', '0');
