<?php

namespace KPMultisiteGenerator;

require_once __DIR__ . '/Generator.php';

add_action('cli_init', function () {
  if (!class_exists('\\WP_CLI') || !class_exists(__NAMESPACE__ . '\\Generator')) return;

  \WP_CLI::add_command('kp multisite generate', [__NAMESPACE__ . '\\Generator', 'generate_command']);
  \WP_CLI::add_command('kp multisite manifest', [__NAMESPACE__ . '\\Generator', 'manifest_command']);
});
