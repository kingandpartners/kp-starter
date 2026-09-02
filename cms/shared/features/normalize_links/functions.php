<?php

function kp_nuxt_normalize_links($value, $post_id, $field) {
  if ($value === '') return null;
  $home_url = get_home_url(get_current_blog_id());
  $frontend_url = UrlModification\modify_url($home_url);
  if (!isset($value['url']) || empty($frontend_url)) return $value;

  $parsed_frontend_url = parse_url($frontend_url);
  $parsed_url = parse_url($value['url']);
  if (
    isset($parsed_frontend_url['scheme'], $parsed_frontend_url['host']) &&
    isset($parsed_url['scheme'], $parsed_url['host']) &&
    $parsed_frontend_url['scheme'] === $parsed_url['scheme'] &&
    $parsed_frontend_url['host'] === $parsed_url['host']
  ) {
    $path = $parsed_url['path'] ?? '/';
    if (isset($parsed_url['query'])) $path .= '?' . $parsed_url['query'];
    if (isset($parsed_url['fragment'])) $path .= '#' . $parsed_url['fragment'];
    $value['url'] = $path;
  }

  return $value;
}

add_filter('acf/load_value/type=link', 'kp_nuxt_normalize_links', 10, 3);
