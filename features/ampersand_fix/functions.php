<?php

add_action('admin_init', function() {
  // since wordpress is only used for admin, we can safely assume anybody
  // with access to the admin will not be adding malicious code, so we
  // can remove these filters which allows for unescaped ampersands
  kses_remove_filters();
  $role = get_role('administrator');
  $role->add_cap('read');
  $role->add_cap('unfiltered_html');
}, 999);

// remove the html filter on all ACF fields
add_filter('acf/allow_unfiltered_html', function() {
  return true;
});

// ACF started sanitizing link values on the frontend, so by manually
// decoding the link value keys we are able to unsanitize the values
add_filter('acf/load_value/type=link', function( $value, $post_id, $field ) {
  if (empty($value)) return $value;
  foreach($value as $key => $val) {
    $value[$key] = htmlspecialchars_decode($val);
  }
  return $value;
}, 10, 3);