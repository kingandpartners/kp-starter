<?php

/**
 * Custom toolbars for ACF WYSIWYG fields
 * @param $toolbars
 * @return mixed
 */
function custom_toolbars( $toolbars ) {
  $toolbars['Custom'] = array();
  $toolbars['Custom'][1] = array('formatselect', 'styleselect', 'bold', 'italic', 'link', 'bullist', 'numlist', 'spellchecker', 'fullscreen');

  $toolbars['Simple'] = array();
  $toolbars['Simple'][1] = array('link');

  return $toolbars;
}

/**
 * Custom settings for WYSIWYG fields
 * @param $settings
 * @return mixed
 */
function custom_editor_settings($settings) {
  // Block Formats
  $settings['block_formats'] = 'Heading=h2; Sub-Heading=h3;';
  return $settings;
}

add_filter('tiny_mce_before_init', 'custom_editor_settings');
add_filter('acf/fields/wysiwyg/toolbars' , 'custom_toolbars');
