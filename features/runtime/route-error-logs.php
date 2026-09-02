<?php

/**
 * Error routing for every environment that logs.
 *
 *  - Errors are never rendered into the page. Besides being noise in wp-admin,
 *    a displayed error corrupts the JSON of `nuxtpress` REST responses, and it
 *    makes WordPress add the `php-error` admin body class (see
 *    wp-admin/admin-header.php), which reserves 2em of blank space above
 *    #adminmenuback.
 *  - Everything is logged, deprecations included.
 *  - Deprecations are routed to their own file, so a wall of third-party
 *    deprecation noise cannot bury a real warning or fatal in the main log.
 *
 * Set WP_DEPRECATION_LOG to a path to enable the split, or `true` to use
 * WP_CONTENT_DIR/deprecation.log. When it is undefined or false, deprecations
 * are masked out of error_reporting entirely, which is how this file behaved
 * before deprecation routing existed.
 *
 * WordPress resets the error level in wp_debug_mode() after the Bedrock config
 * is loaded, so this must run as a MU feature.
 */

/**
 * Keyed off WP_DEBUG rather than the environment name. wp_debug_mode() only
 * configures error logging when WP_DEBUG is on, and otherwise installs a
 * deliberately restricted error_reporting level — there is nothing to route in
 * that case, and widening it would be a regression.
 */
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    return;
}

ini_set('display_errors', '0');

$deprecation_log = defined('WP_DEPRECATION_LOG') ? WP_DEPRECATION_LOG : false;

if ($deprecation_log === false) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    return;
}

error_reporting(E_ALL);

$deprecation_log = $deprecation_log === true
    ? WP_CONTENT_DIR . '/deprecation.log'
    : $deprecation_log;

/**
 * Note: this handler is never called for fatals (E_ERROR, E_PARSE, E_CORE_*,
 * E_COMPILE_*). PHP reports those itself, so they keep going to WP_DEBUG_LOG.
 */
$previous_handler = set_error_handler(
    function ($errno, $errstr, $errfile, $errline) use (&$previous_handler, $deprecation_log) {
        // Honour @-suppression and any masked-off levels.
        if (!(error_reporting() & $errno)) {
            return false;
        }

        if ($errno !== E_DEPRECATED && $errno !== E_USER_DEPRECATED) {
            // Hand back to whoever was handling errors before us — an Airbrake
            // notifier registered in phpbrake-init.php, say — or to PHP itself,
            // which logs to WP_DEBUG_LOG.
            return $previous_handler
                ? call_user_func($previous_handler, $errno, $errstr, $errfile, $errline)
                : false;
        }

        // error_log() in file mode prepends nothing, so timestamp it here to
        // match the format PHP uses for the main log.
        error_log(
            sprintf(
                "[%s UTC] PHP Deprecated:  %s in %s on line %d\n",
                gmdate('d-M-Y H:i:s'),
                $errstr,
                $errfile,
                $errline
            ),
            3,
            $deprecation_log
        );

        return true;
    }
);
