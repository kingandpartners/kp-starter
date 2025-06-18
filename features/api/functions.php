<?php

add_action('ep_after_load', function() {
  // Remove ElasticPress save hooks since we are not using ElasticSearch
  remove_action('wp_insert_post', 'ElasticPress\WpSaveHooks\wp_insert_post');
  remove_action('acf/save_post', 'ElasticPress\WpSaveHooks\acf_save_post');
  remove_action('edited_term', 'ElasticPress\WpSaveHooks\edited_term');
  remove_action('wp_update_nav_menu', 'ElasticPress\WpSaveHooks\wp_update_nav_menu');
});