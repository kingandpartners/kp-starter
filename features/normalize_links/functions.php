<?php
// Turns a link field's URL into a relative path when it points at the
// front end in FRONTEND_URL, so the frontend router handles it.
//
// On multisite, FRONTEND_URL is one value for the whole network (the main
// site's front end), so on every other site this would also make links to
// the main site relative: https://<main site>/ would load as /. There, the
// project's per-site normalize_links filter (e.g. cms/shared/features/
// normalize_links) normalises each site's links against its own front end
// instead, so this steps aside.
function normalize_links_for_elasticpress( $value, $post_id, $field ) {
  if ($value === "") {
    return null;
  }

  if (is_multisite()) {
    return $value;
  }

  $frontend_url = getenv('FRONTEND_URL');
  if (!isset($value['url']) || empty($frontend_url)) {
    return $value;
  }

  $parsed_frontend_url = parse_url($frontend_url);
  $parsed_url = parse_url($value['url']);

  if (
    isset($parsed_frontend_url['scheme'], $parsed_frontend_url['host']) &&
    isset($parsed_url['scheme'], $parsed_url['host']) &&
    $parsed_frontend_url['scheme'] === $parsed_url['scheme'] &&
    $parsed_frontend_url['host'] === $parsed_url['host']
  ) {
    $path = $parsed_url['path'] ?? '/';

    if (isset($parsed_url['query'])) {
      $path .= '?' . $parsed_url['query'];
    }

    if (isset($parsed_url['fragment'])) {
      $path .= '#' . $parsed_url['fragment'];
    }

    $value['url'] = $path;
  }

  return $value;
}

add_filter('acf/load_value/type=link', 'normalize_links_for_elasticpress', 10, 3);
