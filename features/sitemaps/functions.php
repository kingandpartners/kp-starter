<?php

use function UrlModification\modify_url;

// Since the frontend URL is different from the backend URL, we need to modify
// the sitemap URLs. The way Yoast handles the sitemap entries is a bit odd and
// there are two separate filters for the sitemap entries. The filter for the
// first links is separate from the rest of the links so we have to use two
// filters to modify the URLs.
add_filter('wpseo_sitemap_entry', function($url) {
  if (!is_array($url)) return $url;
  $url['loc'] = modify_url($url['loc']);
  return $url;
}, 10, 1);

// See comment for `wpseo_sitemap_entry` filter above.
add_filter('wpseo_sitemap_post_type_first_links', function($links) {
  $links = array_map(function($link) {
    $link['loc'] = modify_url($link['loc']);
    return $link;
  }, $links);
  return $links;
}, 10, 1);
