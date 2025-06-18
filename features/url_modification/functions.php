<?php
/**
 * KPNuxt
 *
 * @package KPNuxt
 */

namespace UrlModification;

function modify_url( $url ) {
  if ( ! defined( 'SITE_URLS' ) ) {
    return $url;
  }

  // keep url admin url for main sitemap index
  if (strpos($_SERVER['REQUEST_URI'], '/sitemap_index.xml') !== false) return $url;

  foreach ( SITE_URLS as $find => $replace ) {
    $url = str_replace( $find, $replace, $url );
  }
  return $url;
}

/**
 * Filters the home url so frontend URL shows as permalink when Admin URL is separate.
 */
add_filter(
  'home_url',
  function( $url, $path, $scheme, $blog_id ) {
    if (
      'wp-json' === $path ||
      (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        strpos( $_SERVER['REQUEST_URI'], 'page=wpcf' ) !== false
      )
    ) return $url;
    return modify_url($url);
  },
  10,
  4
);
