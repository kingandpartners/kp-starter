<?php

namespace KPMultisiteGenerator;

require_once __DIR__ . '/Generator.php';

// Composer-loaded WordPress plugins initialize after WP-CLI's cli_init hook,
// so commands must be registered while this feature is loaded.
if (defined('WP_CLI') && WP_CLI && class_exists('\\WP_CLI')) {
  \WP_CLI::add_command('kp multisite generate', [__NAMESPACE__ . '\\Generator', 'generate_command']);
  \WP_CLI::add_command('kp multisite manifest', [__NAMESPACE__ . '\\Generator', 'manifest_command']);
}

// Regenerate multisite configs whenever a new site is added to the network.
// Priority 200 ensures this runs after wp_initialize_site priority 10 (which
// saves the site's domain and name options that the generator depends on).
add_action('wp_initialize_site', function (\WP_Site $new_site, array $args) {
  if ('true' !== strtolower((string) getenv('MULTISITE'))) {
    return;
  }

  Generator::generate();
}, 200, 2);
