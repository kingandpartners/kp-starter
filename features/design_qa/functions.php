<?php

/**
 * Design QA overlay settings.
 *
 * The overlay itself lives in @kingandpartners/nuxt-platform. What lives here is
 * the pair of settings an editor or developer wants to change without a deploy:
 * whether the overlay is on at all, and which issue its reports are posted to.
 *
 * Registered only in development. In every other environment the options page
 * does not exist, the REST route is not registered, and the overlay is not
 * built into the bundle -- so there is nothing to switch on by accident.
 */

namespace KpStarter\DesignQa;

// The page title and field prefix are both derived from this by
// `register_options_page`, and `ucwords` would render 'design_qa' as
// 'Design Qa'.
const PAGE_NAME = 'design_QA';
const MENU_SLUG = 'globalOptionsFeatureDesignQA';

function is_development() {
  return defined('WP_ENV') ? WP_ENV === 'development' : getenv('WP_ENV') === 'development';
}

/**
 * ACF field names are prefixed with the options page slug when registered, so
 * reads have to use the same prefix.
 */
function option($name, $default = null) {
  if (!function_exists('get_field')) return $default;

  $value = get_field(MENU_SLUG . '_' . $name, 'option');

  return ($value === null || $value === '') ? $default : $value;
}

/**
 * The settings the frontend and its dev-only report endpoint both read.
 */
function settings() {
  return array(
    // Absent means on: a project that has never opened the page still gets the
    // overlay, which is the useful default for a tool you opt out of.
    'enabled' => (bool) option('enabled', true),
    'issue'   => (int) option('github_issue', 0) ?: null,
  );
}

add_action('acf/init', function () {
  if (!is_development()) return;
  if (!function_exists('acf_add_options_page')) return;

  register_options_page(
    'Global Options',
    'feature',
    PAGE_NAME,
    array(
      array(
        'label'         => 'Enable the overlay',
        'name'          => 'enabled',
        'type'          => 'true_false',
        'ui'            => 1,
        'default_value' => 1,
        'instructions'  => 'Turns the Design QA inspector on for this site in development. Reload the frontend after changing it.',
      ),
      array(
        'label'        => 'GitHub issue',
        'name'         => 'github_issue',
        'type'         => 'number',
        'min'          => 1,
        'instructions' => 'The issue number reports are posted to. Leave empty to fall back to the issue configured in nuxt.config.js.',
      ),
    )
  );
}, 11);

add_action('rest_api_init', function () {
  if (!is_development()) return;

  register_rest_route('nuxtpress/v1', '/design-qa', array(
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'callback'            => function () {
      return rest_ensure_response(settings());
    },
  ));
});
