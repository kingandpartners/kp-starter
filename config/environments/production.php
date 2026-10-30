<?php

/**
 * Configuration overrides for WP_ENV === 'production'
 */

use Roots\WPConfig\Config;

use function Env\env;

/**
 * WordPress only configures error logging inside `if ( WP_DEBUG )` — see
 * wp_debug_mode() in wp-includes/load.php — so WP_DEBUG has to be on for
 * WP_DEBUG_LOG to have any effect at all.
 *
 * Errors are still never rendered. WP_DEBUG_DISPLAY is false, which makes
 * wp_debug_mode() set display_errors to 0; application.php and
 * features/runtime/route-error-logs.php each force it off as well; and
 * wp_debug_mode() additionally forces it off for REST, AJAX and JSON requests,
 * which is the path a headless frontend actually uses.
 */
Config::define('WP_DEBUG', env('WP_DEBUG') ?? true);
Config::define('WP_DEBUG_DISPLAY', env('WP_DEBUG_DISPLAY') ?? false);

$log_dir = Config::get('WP_CONTENT_DIR') . '/logs';
Config::define('WP_DEBUG_LOG', env('WP_DEBUG_LOG') ?? $log_dir . '/debug.log');
/**
 * Deprecations are routed away from the main log by
 * features/runtime/route-error-logs.php. Set WP_DEPRECATION_LOG=false to drop
 * them instead of logging them — worth doing where the volume from third-party
 * plugins outweighs their value.
 */
Config::define('WP_DEPRECATION_LOG', env('WP_DEPRECATION_LOG') ?? $log_dir . '/deprecation.log');
unset($log_dir);

ini_set('display_errors', Config::get('WP_DEBUG_DISPLAY') ? '1' : '0');
