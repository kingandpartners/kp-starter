<?php

namespace NuxtPress\Cache;

use function NuxtSsr\Redis\redis_get;
use function NuxtSsr\Redis\redis_unscoped_get;
use function NuxtSsr\Redis\redis_store;
use function NuxtSsr\Redis\redis_unscoped_store;

add_action('wp_insert_post', __NAMESPACE__ . '\wp_insert_post', 10, 3);
add_action('before_delete_post', __NAMESPACE__ . '\before_delete_post', 10, 2);
add_action('acf/save_post', __NAMESPACE__ . '\acf_save_post', 10, 1);
add_action('edited_term', __NAMESPACE__ . '\bump_global_cache', 10, 1);
add_action('wp_update_nav_menu', __NAMESPACE__ . '\bump_global_cache', 10, 1);
add_action('wp_update_site', __NAMESPACE__ . '\bump_deploy_cache_version', 10, 0);

function wp_insert_post($id, $obj, $update) {
  // Skip if this update is to an ignored post type
  if (in_array($obj->post_type, ignored_post_types())) return;

  if (in_array($obj->post_type, ['revision', 'page'])) {
    bump_page_cache_version($id, $obj, $update);
  } else {
    bump_global_cache($id);
  }
}

/**
 * Permanent deletion does not fire `wp_insert_post`, so without this the cache
 * would keep serving a post that no longer exists until the key expired.
 *
 * Trashing already bumps the right key through `wp_insert_post`, and a post
 * deleted out of the trash carries a `__trashed` permalink whose key nothing
 * reads. Deleting a published post outright is the case this covers.
 *
 * Revisions are skipped: WordPress prunes them on its own schedule and a
 * deleted revision cannot change what the front end renders.
 */
function before_delete_post($id, $obj = null) {
  $obj = $obj ?? get_post($id);
  if (empty($obj)) return;
  if ('revision' === $obj->post_type) return;
  if (in_array($obj->post_type, ignored_post_types())) return;

  if ('page' === $obj->post_type) {
    bump_page_cache_version($id, $obj, true);
  } else {
    bump_global_cache($id);
  }
}

function acf_save_post($id = null) {
  if (is_numeric($id)) {
    $obj = get_post($id);
    if (empty($obj)) return;
    // pages are handled in the `wp_insert_post` hook, so we can skip them here
    if ('page' === $obj->post_type) return;
  }
  bump_global_cache($id);
}

function bump_page_cache_version($id, $object, $update) {
  $url       = get_the_permalink($id);
  $cache_key = redis_get($url) ?? 0;
  redis_store($url, ++$cache_key);
  do_action('bump_page_cache_version', $url, $id, $object, $update);
}

// These post types do not need to be cached therefore they are ignored
function ignored_post_types() {
  return [
    'flamingo_contact',
    'flamingo_inbound',
  ];
}

function bump_global_cache($id = null) {
  $global_cache_key = redis_get('global_cache_key') ?? 0;
  redis_store('global_cache_key', ++$global_cache_key);
  do_action('bump_global_cache_version', $id);
}

function bump_deploy_cache_version() {
  $cache_key = redis_unscoped_get('deploy_cache_key') ?? 0;
  redis_unscoped_store('deploy_cache_key', ++$cache_key);
  do_action('bump_deploy_cache_version');
}
