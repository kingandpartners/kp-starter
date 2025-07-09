<?php
/**
 * KP_Starter
 *
 * @package KP_Starter
 */

/**
 * Adds excerpts to 'post' post_type.
 */
add_post_type_support( 'post', 'excerpt' );
remove_post_type_support( 'page', 'editor' );

/**
 * Removes default templates and adds custom templates.
 */
add_filter(
  'theme_templates',
  function ( $post_templates ) {
    $project_root = getenv('PROJECT_ROOT');
    $theme_dir     = basename(get_template_directory());
    $child_theme   = get_option('options_globalOptionsFrontendSite_site');
    if ($child_theme) $theme_dir = "$theme_dir,$child_theme";
    $templates = glob("$project_root/src/themes/{" . $theme_dir . ",shared}/templates/Template*", GLOB_BRACE);
    foreach ( $templates as $template) {
      $template = str_replace('Template', '', $template);
      preg_match_all('/((?:^|[A-Z])[a-z]+)/', $template, $words);
      $value = implode(' ', $words[0]);
      $name  = str_replace(' ', '-', strtolower($value));
      $key   = "template/template-$name.php";
      $post_templates[$key] = $value;
    }

    return $post_templates;
  }
);


/**
 * Templates without editor
 *
 * @param string $id The template identifier.
 */
function ea_disable_editor( $id = false ) {
	$excluded_templates = array(
    'template/template-default.php',
    'template/template-home.php',
    'template/template-detail.php',
    'template/template-detail-light.php',
    'template/template-detail-dark.php'
	);

	if ( empty( $id ) ) {
		return false;
	}

	$id       = intval( $id );
	$template = get_page_template_slug( $id );

	return in_array( $template, $excluded_templates, true );
}

/**
 * Disable Gutenberg by template
 *
 * @param boolean $can_edit Starting value for a post.
 * @param string  $post_type The type of post.
 */
function ea_disable_gutenberg( $can_edit, $post_type ) {
	$post_id = null;

	// Annoying that phpcs requires nonce verification anytime $_GET or $_POST is used:
	// https://github.com/WordPress/WordPress-Coding-Standards/issues/1878
	// For now we will just disable when we see it.
	// phpcs:disable
	if ( isset( $_GET['post'] ) ) {
		$post_id = intval( $_GET['post'] );
	}
	// phpcs:enable

	if ( ! ( is_admin() && ! empty( $post_id ) ) ) {
		return $can_edit;
	}

	if ( ea_disable_editor( $post_id ) ) {
		$can_edit = false;
	}

	return $can_edit;
}

/**
 * Disable Classic Editor by template
 */
function ea_disable_classic_editor() {
	$screen = get_current_screen();
	// phpcs:disable
	if ( 'page' !== $screen->id || ! isset( $_GET['post'] ) ) {
		return;
	}

	if ( ea_disable_editor( $_GET['post'] ) ) {
		remove_post_type_support( 'page', 'editor' );
	}
	// phpcs:enable
}

// add_filter( 'gutenberg_can_edit_post_type', 'ea_disable_gutenberg', 10, 2 );
// add_filter( 'use_block_editor_for_post_type', 'ea_disable_gutenberg', 10, 2 );
add_action( 'admin_head', 'ea_disable_classic_editor' );

add_filter( 'use_block_editor_for_post', '__return_false', 10 );
add_filter( 'use_block_editor_for_post_type', '__return_false', 10 );