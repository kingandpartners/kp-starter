<?php

use function UrlModification\modify_url;

add_filter('wpseo_sitemap_index_links', function ($links) {
  return array_map(function ($link) {
    $link['loc'] = modify_url($link['loc']);
    return $link;
  }, $links);
}, 10, 1);
