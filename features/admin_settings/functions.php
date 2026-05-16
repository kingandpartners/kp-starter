<?php

add_action(
  'admin_menu',
  function() {
    remove_menu_page('edit.php');
    remove_menu_page('edit-comments.php');
    remove_submenu_page('options-general.php','options-permalink.php');
    remove_menu_page('themes.php');
    remove_menu_page('plugins.php');

    add_menu_page(
      'Nav Menus',
      'Nav Menus',
      'manage_options',
      'nav-menus.php',
      '',
      'dashicons-menu',
      5
    );
  }
);

// remove the ACF admin menu item
add_filter('acf/settings/show_admin', '__return_false');

// Ensure admin page titles are always strings so WP core strip_tags() never receives null.
add_action(
  'current_screen',
  function( $screen ) {
    global $title;

    if ( null !== $title && '' !== $title ) {
      return;
    }

    $fallback = '';
    if ( is_object( $screen ) && ! empty( $screen->title ) ) {
      $fallback = (string) $screen->title;
    }

    if ( '' === $fallback ) {
      $fallback = (string) get_bloginfo( 'name' );
    }

    if ( '' === $fallback ) {
      $fallback = 'WordPress';
    }

    $title = $fallback;
  },
  0
);

add_action(
  'admin_menu',
  function() {
    remove_post_type_support('post', 'editor');
    remove_post_type_support('post', 'comments');
    remove_post_type_support('post', 'trackbacks');
    remove_post_type_support('page', 'editor');
    remove_post_type_support('page', 'comments');
    remove_post_type_support('page', 'trackbacks');
  }
);

add_action(
  'login_enqueue_scripts',
  function() {
  ?>
    <style type="text/css">
      #login h1 a,
      .login h1 a {
        background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/login.svg);
        background-size: contain;
        background-position: center;
        background-repeat: no-repeat;
      }
    </style>
  <?php
  }
);

add_action(
  'login_headerurl',
  function() {
    return get_bloginfo('url');
  }
);

// normalize behavior between multisite and non-multisite builds
if (!function_exists('get_sites')) {
  function get_sites() {
    return array(
      (object) array(
        "blog_id" => "1",
        "domain"  => getenv('WP_DOMAIN'),
        "path"    => "/",
        "site_id" => "1",
        "home"    => get_home_url(1),
      )
    );
  }
}
