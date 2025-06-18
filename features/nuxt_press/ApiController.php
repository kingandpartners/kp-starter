<?php

namespace NuxtPress;

use function NuxtSsr\Redis\redis_get;
use function NuxtSsr\Redis\redis_unscoped_get;
use function NuxtSsr\simple_cache_fetch;
use function NuxtSsr\longterm_cache_fetch;

class ApiController {

  public function register_routes() {
    $namespace = 'nuxtpress/v1';

    register_rest_route($namespace, '/blogs/', array(
      'methods'  => 'GET',
      'callback' => array($this, 'get_blogs'),
      'permission_callback' => '__return_true'
    ));

    register_rest_route($namespace, '/blogs/(?P<url>\S+)', array(
      'methods'  => 'GET',
      'callback' => array($this, 'get_blog'),
      'permission_callback' => '__return_true'
    ));

    register_rest_route($namespace, '/get-by-url/(?P<url>\S+)', array(
      'methods'  => 'GET',
      'callback' => array($this, 'get_by_url'),
      'permission_callback' => '__return_true'
    ));

    register_rest_route($namespace, '/navs/', array(
      'methods'  => 'GET',
      'callback' => array($this, 'get_navs'),
      'permission_callback' => '__return_true'
    ));

    register_rest_route($namespace, '/global-options', array(
      'methods'  => 'GET',
      'callback' => array($this, 'get_global_options'),
      'permission_callback' => '__return_true'
    ));

    register_rest_route($namespace, '/posts/(?P<locale>[a-zA-Z0-9-]+)/(?P<type>[a-zA-Z0-9-_]+)(?:/(?P<ids>\S+))?', array(
      'methods'  => 'GET',
      'callback' => array($this, 'get_posts_by_params'),
      'permission_callback' => '__return_true'
    ));

    register_rest_route($namespace, '/terms/(?P<locale>[a-zA-Z0-9-]+)/(?P<type>[a-zA-Z0-9-_]+)(?:/(?P<ids>\S+))?', array(
      'methods'  => 'GET',
      'callback' => array($this, 'get_terms_by_type'),
      'permission_callback' => '__return_true'
    ));

    register_rest_route($namespace, '/component-stats/(?P<name>\S+)', array(
      'methods'  => 'GET',
      'callback' => array($this, 'get_component_stats'),
      'permission_callback' => '__return_true'
    ));

    register_rest_route($namespace, '/pages-by-template/(?P<template>\S+)', array(
      'methods'  => 'GET',
      'callback' => array($this, 'get_pages_by_template'),
      'permission_callback' => '__return_true'
    ));
  }

  public function cache_key($key) {
    $keys = [
      $key,
      redis_unscoped_get('deploy_cache_key'),
      redis_get('global_cache_key'),
    ];
    return implode('-', $keys);
  }

  public function get_component_stats($request) {
    $name       = $request['name'];
    $post_types = get_post_types();

    $args = array(
      'posts_per_page' => -1,
      'post_status'    => 'publish',
      'post_type'      => $post_types,
      'meta_query' => array(
        array(
          'key'     => 'components',
          'value'   => $name,
          'compare' => 'LIKE',
        ),
      ),
    );

    $posts = get_posts($args);
    $posts = array_map(function($post) {
      return array(
        'post_type' => $post->post_type,
        'url'       => get_the_permalink($post)
      );
    }, $posts);

    $data = array(
      'name'  => $name,
      'posts' => $posts
    );
    return new \WP_REST_Response($data, 200);
  }

  public function get_blogs() {
    $response = simple_cache_fetch('blogs', array($this, 'retrieve_blogs'));
    return new \WP_REST_Response($response, 200);
  }

  public function get_blog($request) {
    $url = rtrim($request['url'], '/');
    $origin = parse_url($url, PHP_URL_HOST);
    $protocol = parse_url($url, PHP_URL_SCHEME);
    $domain = $protocol . '://' . $origin;
    if (!is_multisite() || !is_subdomain_install()) {
      $key = $domain;
    } else {
      $path = parse_url($url, PHP_URL_PATH) ?? '';
      $parts = explode('/', $path);
      $site_path = count($parts) < 2 ? '' : $parts[1];
      $key = "$domain/$site_path";
    }
    $response = longterm_cache_fetch("blog-$key", array($this, 'retrieve_blog', $key));
    return new \WP_REST_Response($response, 200);
  }

  public function retrieve_blog($url) {
    $blogs = simple_cache_fetch('blogs', array($this, 'retrieve_blogs'));
    $blog = $blogs[$url] ?? null;
    return new \WP_REST_Response($blog, 200);
  }

  public function get_global_options() {
    $response = simple_cache_fetch('global_options', array($this, 'retrieve_global_options'));
    return new \WP_REST_Response($response, 200);
  }

  public function get_post_types() {
    $response = simple_cache_fetch('post_types', array($this, 'retrieve_post_types'));
    return new \WP_REST_Response($response, 200);
  }

  public function get_by_url($request) {
    $url     = rtrim($request['url'], '/') . '/';
    $url_key = redis_get($url);
    $keys    = ["url-$url", $url_key];
    if (isset($_GET['page_id'])) array_push($keys, $_GET['page_id']);
    if (isset($_GET['p'])) array_push($keys, $_GET['p']);
    if (isset($_GET['preview'])) array_push($keys, $_GET['preview']);
    $response = simple_cache_fetch($keys, array($this, 'retrieve_by_url', $url));
    return new \WP_REST_Response($response, 200);
  }

  public function get_navs($request) {
    $response = simple_cache_fetch('navs', array($this, 'retrieve_navs'));
    return new \WP_REST_Response($response, 200);
  }

  public function get_posts_by_params($request) {
    $ids    = $request['ids'] ?? 'all';
    $type   = $request['type'];
    $keys = [
      'posts_by_params',
      $type,
      $ids,
    ];
    $response = simple_cache_fetch($keys, array($this, 'retrieve_posts_by_params', $request));
    return new \WP_REST_Response($response, 200);
  }

  public function get_terms_by_type($request) {
    $keys = [
      'terms',
      $request['type'],
      $request['ids'],
    ];
    $response = simple_cache_fetch($keys, array($this, 'retrieve_terms_by_type', $request));
    return new \WP_REST_Response($response, 200);
  }


  public function retrieve_terms_by_type($request) {
    $type   = $request['type'];
    $ids    = $request['ids'];
    $args   = array(
      'taxonomy'   => $type,
      'hide_empty' => true,
    );
    if ($ids) {
      $args['include'] = $ids;
      $args['orderby'] = 'include';
    }
    return array_values(get_terms($args));
  }

  function get_pages_by_template($request) {
    $template = $request['template'];
    $args = array(
      'meta_key' => '_wp_page_template',
      'meta_value' => "$template.php"
    );
    $admin_url = get_admin_url(null, 'post.php?post=ID&action=edit');
    $data = array_map(function($page) use($admin_url) {
      return [
        'url'   => get_permalink($page->ID),
        'admin' => str_replace('ID', $page->ID, $admin_url)
      ];
    }, get_pages($args));
    return new \WP_REST_Response($data, 200);
  }

  public function retrieve_blogs() {
    $sites = get_sites();
    $response = array();
    foreach ($sites as $site) {
      $key = $site->home;
      $response[$key] = $site;
    }
    return $response;
  }

  public function retrieve_global_options() {
    global $sitepress;
    $default_lang = array('code' => 'en');
    $current_lang = current_language();
    $languages    = apply_filters('wpml_active_languages', null) ?? array($default_lang);
    $response     = array();
    foreach( $languages as $language ) {
      $lang = $language['code'];
      if ($current_lang && $lang !== $current_lang) $sitepress->switch_lang($lang);
      $response[$lang] = $this->get_options_data();
      if ($current_lang && $lang !== $current_lang) $sitepress->switch_lang($current_lang);
    }
    return $response;
  }

  public function retrieve_by_url($url) {
    if (
      isset($_GET['page_id']) ||
      isset($_GET['p']) ||
      isset($_GET['preview'])
    ) {
      $post = $this->get_post_by_parameters($_GET);
    } else {
      $post = $this->get_post_by_url($url);
    }
    if ($post && $post && 'page' === $post->post_type) {
      $response = \ElasticPress\Serializers\page_data($post);
    } else {
      $response = \ElasticPress\Serializers\post_data($post);
    }
    $response = (empty($response)) ? null : $response;

    return $response;
  }

  public function retrieve_navs() {
    $menus = wp_get_nav_menus();
    $response = array_map(function($menu) {
      $data = self::get_nav_data($menu);
      $data['menu_items'] = $this->structure_menu($data['menu_items']);
      return $data;
    }, $menus);
    // add language switcher for all languages
    $current_lang = current_language();
    if ($current_lang) {
      $languages = apply_filters('wpml_active_languages', null) ?? [];
      $languages = array_map(function($lang) use($current_lang){
        $lang['locale'] = $current_lang;
        return $lang;
      }, array_values($languages));
      foreach($languages as $language) {
        if ($current_lang === $language['code']) continue;
        switch_language($language['code']);
        $new_langs = apply_filters('wpml_active_languages', null) ?? [];
        $new_langs = array_map(function($lang) use($current_lang, $language){
          $lang['locale'] = $language['code'];
          return $lang;
        }, array_values($new_langs));
        $languages = array_merge($languages, $new_langs);
      }
      switch_language($current_lang);
      $response = array_merge($response, $languages);
    }

    $response = apply_filters('ep_get_navs', $response);

    return $response;
  }

  public function get_options_data($id = 'options', $page = null) {
    $id         = apply_filters( 'ep_options_id', $id );
    $data       = \ElasticPress\Acf\acf_data( $id );
    $clean_data = array();
    $return     = array();
    foreach ( $data as $key => $value ) {
      $key_array         = explode( '_', $key );
      $id                = array_shift( $key_array );
      $key               = implode( '_', $key_array );
      $new_data          = array( $key => $value );
      $clean_data[ $id ] = isset( $clean_data[ $id ] ) ? array_merge( $clean_data[ $id ], $new_data ) : $new_data;
    }

    foreach ( $clean_data as $key => $value ) {
      if ( $page && $page !== $key ) {
        continue;
      }
      $key         = apply_filters( 'ep_options_key', $key );
      $value       = apply_filters( 'ep_options_value', $value, $key );
      $value['ID'] = $key;
      $return[] = $value;
    }
    return $return;
  }

  public function retrieve_posts_by_params($request) {
    $ids  = $request['ids'];
    $args = array(
      'posts_per_page' => -1,
      'post_status'    => 'publish',
      'post_type'      => $request['type'],
      'meta_query' => array(
        array(
          'key'   => '_locale',
          'value' => $request['locale']
        ),
      ),
    );
    if ($ids) {
      $args['post__in'] = explode(',', $ids);
      $args['orderby']  = 'post__in';
    }
    return array_map('\ElasticPress\Serializers\post_data', get_posts($args));
  }

  public function get_post_by_url($url) {
    $posts = get_posts(array(
      'posts_per_page' => 1,
      'post_status'    => 'publish',
      'post_type'      => 'any',
      'meta_query' => array(
        array(
          'key'     => '_url',
          'value'   => $url
        ),
      ),
    ));

    return (empty($posts)) ? null : $posts[0];
  }

  public function get_post_by_parameters($query) {
    if (isset($query['page_id'])) {
      $preview_id = $query['page_id'];
    }
    if (isset($query['p'])) {
      $preview_id = $query['p'];
    }
    if (isset($query['preview_id'])) {
      $preview_id = $query['preview_id'];
    }
    $posts = array_values(wp_get_post_revisions($preview_id));
    return (empty($posts)) ? null : $posts[0];
  }

  public static function get_nav_data($menu) {
    $items              = wp_get_nav_menu_items( $menu->name );
    $data               = \ElasticPress\Serializers\term_data( $menu );
    $data['menu_items'] = array_map( '\ElasticPress\Serializers\nav_map', $items );
    return $data;
  }

  public function structure_submenu($all_items, $sub_menu_items) {
    return array_map(function($item) use($all_items) {
      $key = strval($item->ID);
      if (isset($all_items[$key])) {
        $sub_menu_items = $all_items[$key];
        $item->sub_menu = $this->structure_submenu($all_items, $sub_menu_items);
      }
      return $item;
    }, $sub_menu_items);
  }

  public function structure_menu($menu_items) {
    $menu_items = $menu_items ?? array();
    $items = array_reduce($menu_items, function($carry, $item) {
      $parent = strval($item->menu_item_parent) ;
      $carry[$parent] = $carry[$parent] ?? array();
      array_push($carry[$parent], $item);
      return $carry;
    }, array());

    return $this->structure_submenu($items, $items['0'] ?? array());
  }

}