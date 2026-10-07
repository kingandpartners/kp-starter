<?php

function kp_nuxt_save_robots_txt($value, $post_id, $field) {
  $site_name = get_field('site', 'options_globalOptionsComponentSite');
  if (empty($value) || empty($site_name)) return $value;

  $robots_dir = '/var/app/current/wordpress/web/robots';
  $robots_file = $robots_dir . '/' . sanitize_file_name($site_name) . '.txt';
  if (!file_exists($robots_dir)) wp_mkdir_p($robots_dir);
  file_put_contents($robots_file, $value);
  return $value;
}

add_filter(
  'acf/update_value/name=globalOptionsCmsRobotsTxt_robots_txt',
  'kp_nuxt_save_robots_txt',
  10,
  3
);
