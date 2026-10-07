<?php

/**
 * Plugin Name: Bedrock Autoloader
 * Description: Loads standard plugins registered by the Bedrock autoloader.
 */

namespace Roots\Bedrock;

if (is_blog_installed() && class_exists(Autoloader::class)) {
    new Autoloader();
}
