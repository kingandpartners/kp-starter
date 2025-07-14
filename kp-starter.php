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

use ACFComposer\ACFComposer;

class Site {
  public static $config = array(
    'features' => [
      'shared' => [
        'functions.php' => [],
        'fields.json'   => [],
        'fields.php'    => [],
      ],
      'theme'  => [
        'functions.php' => [],
        'fields.json'   => [],
        'fields.php'    => [],
      ],
    ],
    'components' => [
      'shared' => [
        'functions.php' => [],
        'fields.json'   => [],
        'fields.php'    => [],
      ],
      'theme'  => [
        'functions.php' => [],
        'fields.json'   => [],
        'fields.php'    => [],
      ],
    ],
    'templates' => [
      'shared' => [
        'functions.php' => [],
        'fields.json'   => [],
        'fields.php'    => [],
      ],
      'theme'  => [
        'functions.php' => [],
        'fields.json'   => [],
        'fields.php'    => [],
      ],
    ],
    'custom_post_types' => [
      'shared' => [
        'config.json'   => [],
        'functions.php' => [],
        'fields.json'   => [],
        'fields.php'    => [],
      ],
      'theme' => [
        'config.json'   => [],
        'functions.php' => [],
        'fields.json'   => [],
        'fields.php'    => [],
      ],
    ],
    'taxonomies' => [
      'shared' => [
        'taxonomies.php' => [],
      ],
      'theme' => [
        'taxonomies.php' => [],
      ],
    ],
  );

  public static function register() {
    $project_root  = getenv('PROJECT_ROOT');
    $theme_dir     = basename(get_template_directory());
    $child_theme   = get_option('options_globalOptionsFrontendSite_site');
    if ($child_theme) $theme_dir = "$theme_dir,$child_theme";
    $project_files = glob("$project_root/{cms,src/themes}/{" . $theme_dir . ",shared}/{*,*/*,*/*/*,*/*/*/*}/{functions,fields,taxonomies,config}.{php,json}", GLOB_BRACE);
    $features_dir  = __DIR__ . '/features';
    $plugin_files  = glob($features_dir . '/**/{functions,fields,taxonomies,config}.{php,json}', GLOB_BRACE);
    $files         = array_merge($project_files, $plugin_files);
    foreach($files as $file) {
      // which kind of file is it? functions, fields, taxonomies, config (json or php)
      $filename = basename($file);
      $ext      = pathinfo($file, PATHINFO_EXTENSION);

      // which type of file is it? features, components, custom_post_type, taxonomies
      preg_match('/(\/features\/)|(\/components\/)|(\/custom_post_types\/)|(\/templates\/)/', $file, $matches);
      $type = ('taxonomies.php' === $filename) ? 'taxonomies' : str_replace('/', '', $matches[0]);

      // is it shared or theme?
      preg_match('/(\/shared\/)/', $file, $matches);
      $priority = (preg_match('/(\/shared\/)/', $file, $matches)) ? 'shared' : 'theme';

      // is it located in cms or frontend?
      $location = (preg_match('/(\/cms\/)/', $file, $matches)) ? 'cms' : 'frontend';

      $result = array(
        'file'     => $file,
        'location' => $location,
      );
      if ('json' === $ext) {
        $result['config'] = json_decode(file_get_contents($file), true);
      }
      array_push(self::$config[$type][$priority][$filename], $result);
    };
  }
}

add_action('after_setup_theme', __NAMESPACE__ . '\init');
add_action('acf/init', __NAMESPACE__ . '\load');
add_action('init', __NAMESPACE__ . '\wp_init');

function wp_init() {
  register_taxonomies();
}

function init() {
  Site::register();
  load_features();
}

function load() {
  register_custom_post_types();
  register_acf_fields();
  do_action('kp_starter_after_load');
}

/**
 * Load shared and theme features
 */
function load_features() {
  $files = array_merge(
    Site::$config['features']['shared']['functions.php'],
    Site::$config['features']['theme']['functions.php'],
    Site::$config['components']['shared']['functions.php'],
    Site::$config['components']['theme']['functions.php'],
    Site::$config['templates']['shared']['functions.php'],
    Site::$config['templates']['theme']['functions.php']
  );
  foreach ($files as $file) require_once($file['file']);
}

/**
 * Register Custom Post Types
 */
function register_custom_post_types() {
  $files = array_merge(
    Site::$config['custom_post_types']['shared']['config.json'],
    Site::$config['custom_post_types']['theme']['config.json']
  );
  foreach ($files as $file) {
    $config = $file['config'];
    $name = $config['name'];
    unset( $config['name'] );
    if (post_type_exists($name)) continue;
    register_post_type($name, $config);
    add_post_type_support($name, 'thumbnail');
  }
}

/**
 * Register Taxonomies
 */
function register_taxonomies() {
  $files = array_merge(
    Site::$config['taxonomies']['shared']['taxonomies.php'],
    Site::$config['taxonomies']['theme']['taxonomies.php']
  );
  foreach ($files as $file) {
    foreach ($files as $file) require_once($file['file']);
  }
}

/**
 * Register ACF Custom Fields
 */
function register_acf_fields() {
  // load php fields
  $files = array_merge(
    Site::$config['custom_post_types']['shared']['fields.php'],
    Site::$config['custom_post_types']['theme']['fields.php'],
    Site::$config['features']['shared']['fields.php'],
    Site::$config['features']['theme']['fields.php'],
    Site::$config['components']['shared']['fields.php'],
    Site::$config['components']['theme']['fields.php'],
    Site::$config['templates']['shared']['fields.php'],
    Site::$config['templates']['theme']['fields.php']
  );
  foreach ($files as $file) require_once($file['file']);

  // add layout field filters for components
  $files = array_merge(
    Site::$config['components']['shared']['fields.json'],
    Site::$config['components']['theme']['fields.json']
  );
  foreach ($files as $file) {
    $dir  = dirname($file['file']);
    $name = basename($dir);
    add_filter("layout_$name", function() use ($dir, $name) {
      $fields = json_decode(file_get_contents("$dir/fields.json"), true);
      return $fields['fields'];
    });
  }

  $files = array_merge(
    Site::$config['custom_post_types']['shared']['fields.json'],
    Site::$config['custom_post_types']['theme']['fields.json'],
    Site::$config['features']['shared']['fields.json'],
    Site::$config['features']['theme']['fields.json'],
    Site::$config['components']['shared']['fields.json'],
    Site::$config['components']['theme']['fields.json'],
    Site::$config['templates']['shared']['fields.json'],
    Site::$config['templates']['theme']['fields.json']
  );

  foreach ($files as $file) {
    $config = $file['config'];
    if (isset($config['block'])) {
      // TODO
    }

    if (isset($config['layout'])) {
      register_layout($config);
    }

    if (isset($config['group'])) {
      register_group($config);
    }

    if ( isset( $config['globalOptions'] ) ) {
      $dir  = dirname($file['file']);
      $name = basename($dir);
      register_options_page(
        'Global Options',
        $file['location'],
        $name,
        $config['globalOptions']
      );
    }
  }
}

/**
 * Register Layout
 */
function register_layout($config) {
  $layout_name = $config['layout']['name'];
  foreach($config['groups'] as $group) {
    $group_name = str_replace('layout_', '', $group);
    $group_title = ucwords(str_replace('_', ' ', $group_name));
    $group_array = array(
      'group' => array(
        'name' => $layout_name . '_' . $group_name,
        'title' => $group_title,
      ),
      'fields' => array($group),
    );

    if (isset($config['location'])) {
      $group_array['location'] = $config['location'];
    }

    register_group($group_array);
  }
}

/**
 * Register Field Group
 */
function register_group($config) {
  if (isset($config['location'])) {
    $location = $config['location'];
  } else {
    $location = array();
  }

  $name = $config['group']['name'];
  $fields = apply_filters('register_acf_fields_' . $name, $config['fields']);

  $new_config = array(
    'name'     => $name,
    'title'    => $config['group']['title'],
    'fields'   => $fields,
    'location' => $location,
    'show_in_rest' => true,
  );

  if (isset( $config['position'])) {
    $new_config['position'] = $config['position'];
  }

  ACFComposer::registerFieldGroup($new_config);
}

/**
 * Helper function to register ACF Options page
 *
 * @param string $title The options page name.
 * @param string $type The type of options page name.
 * @param string $page_name The options sub-page name.
 * @param Array  $fields The array of ACF fields to register.
 */
function register_options_page( $title, $type, $page_name, $fields ) {
  $type            = ucfirst( $type );
  $camelized_title = str_replace( ' ', '', lcfirst( ucwords( $title ) ) );
  $page_title      = ucwords(str_replace('_', ' ', $page_name));
  $parent_slug     = ucfirst( $camelized_title );
  $page_name       = str_replace(' ', '', ucwords(str_replace('_', ' ', $page_name)));
  $menu_slug       = "$camelized_title$type$page_name";
  acf_add_options_page(
    array(
      'page_title' => $title,
      'menu_title' => $title,
      'menu_slug'  => $parent_slug,
    )
  );

  acf_add_options_sub_page(
    array(
      'page_title'   => $page_title,
      'menu_title'   => $page_title,
      'menu_slug'    => $menu_slug,
      'parent_slug'  => $parent_slug,
      'show_in_rest' => true,
    )
  );

  $field_group = ACFComposer::registerFieldGroup(
    array(
      'name'     => $menu_slug,
      'title'    => $page_title,
      'fields'   => prefix_fields( $fields, $menu_slug ),
      'show_in_rest' => true,
      'location' => array(
        array(
          array(
            'param'    => 'options_page',
            'operator' => '==',
            'value'    => $menu_slug,
          ),
        ),
      ),
    )
  );
}

/**
 * Helper function to prefix ACF options fields
 *
 * @param Array  $fields The fields to prefix.
 * @param string $prefix The prefix for the fields.
 */
function prefix_fields( $fields, $prefix ) {
  return array_map(
    function ( $field ) use ( $prefix ) {
      $field['name'] = $prefix . '_' . $field['name'];
      return $field;
    },
    $fields
  );
}
