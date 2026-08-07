<?php

/**
 * Suppress deprecated notices outside production. WordPress resets the error
 * level after the Bedrock config is loaded, so this must run as a MU feature.
 */
if (defined('WP_ENV') && WP_ENV !== 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}
