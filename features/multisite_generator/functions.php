<?php

namespace KPMultisiteGenerator;

require_once __DIR__ . '/Generator.php';

add_action('cli_init', function () {
  if (!class_exists('\\WP_CLI') || !class_exists(__NAMESPACE__ . '\\Generator')) return;

  \WP_CLI::add_command('kp multisite generate', [__NAMESPACE__ . '\\Generator', 'generate_command']);
  \WP_CLI::add_command('kp multisite manifest', [__NAMESPACE__ . '\\Generator', 'manifest_command']);
});

// Regenerate multisite configs whenever a new site is added to the network.
// Priority 200 ensures this runs after wp_initialize_site priority 10 (which
// saves the site's domain and name options that the generator depends on).
add_action('wp_initialize_site', function (\WP_Site $new_site, array $args) {
  if ('true' !== strtolower((string) getenv('MULTISITE'))) {
    return;
  }

  Generator::generate();
}, 200, 2);

