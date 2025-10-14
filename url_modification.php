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
      strpos($_SERVER['REQUEST_URI'], '.xml') !== false ||
      empty($path) ||
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

// Modify the URL for the XSL stylesheet in the sitemaps
// since home_url is bypassed for .xml files in the filter above.
add_filter('clean_url', function($good_protocol_url, $original_url, $_context) {
  if (strpos($original_url, '.xsl') === false) {
    return $good_protocol_url;
  }
  return modify_url($good_protocol_url);
}, 10, 3);
