<?php

namespace NuxtSsr;

use function NuxtSsr\Redis\redis_get;
use function NuxtSsr\Redis\redis_unscoped_get;
use function NuxtSsr\Redis\redis_unscoped_store;
use function NuxtSsr\Redis\redis_store;

class SimpleCache {

  protected $object;
  protected $callback;
  protected $disable;
  protected $options;

  public function __construct($key, $options, $callback) {
    $this->callback = $callback;
    $this->options  = $options;
    // return if no cache
    $this->disable = filter_var(getenv('SIMPLE_CACHE_DISABLE'), FILTER_VALIDATE_BOOLEAN);
    if ($this->disable) return;
    $this->object = $this->get($key);
    $now          = time();
    $timestamp    = (!$this->object) ? $now : $this->object['timestamp'];
    $expired      = $timestamp < $now;

    if (!$this->object || $expired) {
      $this->store($key, array(
        'timestamp' => $now + $options['expires_in'],
        'content'   => $this->invoke_callback()
      ));
      $this->object = $this->get($key);
    }
  }

  public function get($key) {
    if (isset($this->options['unscoped'])) {
      return redis_unscoped_get($key);
    } else {
      return redis_get($key);
    }
  }

  public function store($key, $value) {
    if (isset($this->options['unscoped'])) {
      redis_unscoped_store($key, $value);
    } else {
      redis_store($key, $value);
    }
  }

  public function invoke_callback() {
    if (is_array($this->callback) && count($this->callback) > 2) {
      $arg = array_pop($this->callback);
      $content = call_user_func($this->callback, $arg);
    } else {
      $content = call_user_func($this->callback);
    }
    return $content;
  }

  public function content() {
    if ($this->disable) {
      return $this->invoke_callback();
    }
    return $this->object['content'];
  }

}

function longterm_cache_fetch($key, $callback, $options = array()) {
  $keys = [
    $key,
    redis_unscoped_get('longterm_cache_key') ?? '1',
  ];
  return simple_cache_fetch(
    implode('-', $keys),
    $callback,
    array(
      'unscoped'   => true,
      'expires_in' => 60 * 60 * 24 * 365 // 1 year
    )
  );
}

function simple_cache_fetch($key, $callback, $options = array()) {
  $one_day = 60 * 60 * 24;
  $expires_in = $one_day;
  if (!isset($options['unscoped'])) {
    $key = simple_cache_key($key);
  }
  if (isset($options['expires_in'])) {
    $expires_in = $options['expires_in'];
  }
  $new_options = array(
    'expires_in' => $expires_in,
  );
  $new_options = array_merge($new_options, $options);
  $cache = new SimpleCache(
    $key,
    $new_options,
    $callback,
  );
  return $cache->content();
}

function simple_cache_key($key) {
  // key could be string or array
  $keys = (array) $key;
  $keys = array_merge($keys, [
    redis_unscoped_get('deploy_cache_key'),
    redis_get('global_cache_key'),
  ]);
  // remove empty values from array
  $keys = array_filter($keys);
  $key = implode('-', $keys);
  return \Utilities\encodeURIComponent($key);
}

add_action('acf/options_page/save', function($post_id, $menu_slug) {
  if ('globalOptionsFeatureSimpleCache' !== $menu_slug) return;
  $key = get_field('globalOptionsFeatureSimpleCache_longterm_cache', 'options') ?? '1';
  $key = (int) $key;
  redis_unscoped_store('longterm_cache_key', $key);
  // store env info
  redis_unscoped_store('env_info', array(
    'project_name' => getenv('PROJECT_NAME'),
    'environment'  => WP_ENV,
    'multisite'    => is_multisite() ? 'true' : 'false',
    'subdomain'    => is_multisite() && is_subdomain_install() ? 'true' : 'false',
  ));
}, 10, 2);
