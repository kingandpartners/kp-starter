<?php

/**
 * Plugin Name:     KP Starter
 * Plugin URI:      https://github.com/kingandpartners/kp-starter
 * Description:     Baseline WordPress functionality for King & Partners projects.
 * Author:          King & Partners
 * Author URI:      https://www.kingandpartners.com
 * Text Domain:     kp-starter
 * Version:         0.0.1
 *
 * @package         KP_Starter
 */


$features_dir = __DIR__ . '/features';
$feature_files = glob($features_dir . '/**/functions.php');
foreach ($feature_files as $file) {
  if (is_readable($file)) {
    require_once $file;
  } else {
    error_log("Unable to read feature file: $file");
  }
}