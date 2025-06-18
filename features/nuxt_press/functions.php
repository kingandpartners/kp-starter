<?php

namespace NuxtPress;

require __DIR__ . '/ApiController.php';
require __DIR__ . '/Cache.php';

add_action('rest_api_init', function() {
  $controller = new ApiController;
  $controller->register_routes();
});

add_filter('ep_post_data', function($data, $obj) {
  if ('revision' === $obj->post_type) {
    $post_type = get_post_type($obj->post_parent);
    if ('page' === $post_type) {
      $data['page_template'] = get_post_meta($obj->post_parent, '_wp_page_template', true);
    } else {
      $data['page_template'] = "template-$post_type-single";
    }
  }
  return $data;
}, 10, 2);

function set_url_post_meta( $id, $obj, $update ) {
  // do not set url for revisions as they are not looked up by url and this
  // also causes the non-revision version of the page to be set incorrectly
  if ('revision' === $obj->post_type) return;
  $permalink = get_the_permalink($obj->ID);
  update_post_meta($obj->ID, '_url', $permalink);
}

add_action('wp_insert_post', 'NuxtPress\set_url_post_meta', 10, 3);

// remove Attributes for custom post types to remove confusion over selecting
// a Template
function hide_meta_box_attributes( $hidden, $screen) {
  if ('page' === $screen->post_type) return $hidden;
  $hidden[] = 'pageparentdiv';
  return $hidden;
}

function remove_meta_box_attributes() {
  ?>
  <style type="text/css">
    label[for="pageparentdiv-hide"] { display: none; }
  </style>
  <?php
}
add_filter('hidden_meta_boxes', __NAMESPACE__ . '\hide_meta_box_attributes', 1, 2);
add_action('acf/input/admin_head', __NAMESPACE__ . '\remove_meta_box_attributes');
