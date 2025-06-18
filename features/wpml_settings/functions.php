<?php

// since we modify home_url for localhost support in features/url_modification
// we need to do the same for WPML
add_filter(
	'wpml_url_converter_get_abs_home',
	'UrlModification\modify_url',
	10,
	1
);

function apply_wpml_data($data, $obj) {
  global $sitepress;
  $current_lang = current_language();
  $languages    = apply_filters('wpml_active_languages', null) ?? [];
  $ids          = [];

  foreach($languages as $code => $language) {
    $translated_id = icl_object_id($obj->ID, $obj->post_type, false, $code);
    if ($translated_id) array_push($ids, $translated_id);
  }

  if ($current_lang) {
    $data['source_id'] = icl_object_id($obj->ID, $obj->post_type, false, default_language());
    $item_lang = get_item_language($obj);
    $data['locale'] = $item_lang;
    foreach ($ids as $id) {
      $item = get_post($id);
      if ($item->post_status !== 'publish') continue;
      $item_lang = get_item_language($item);
      if ($current_lang !== $item_lang) {
        switch_language($item_lang);
        $current_lang = $item_lang;
      }
      $permalink = get_the_permalink($id);
      $urls[$item_lang] = $permalink;
    }
    restore_current_blog();
    update_post_meta($obj->ID, '_language_urls', $urls);
    $data['urls'] = $urls;
  } else {
    $data['locale'] = 'en';
  }

  return $data;
}

function set_wpml_data( $id, $obj, $update ) {
  $current_lang = current_language();
  $locale       = $current_lang ?? 'en';
  update_post_meta($obj->ID, '_locale', $locale);
}

function set_wpml_term_data( $term_id, $tt_id = null, $taxonomy = null, $update = null ) {
  $current_lang = current_language();
  $locale       = $current_lang ?? 'en';
  update_term_meta($term_id, '_locale', $locale);
}

function get_post_translations($post_type) {
  global $wpdb;
  $table_name = $wpdb->prefix . 'icl_translations';
  $current_lang = current_language();
  $sql = "SELECT element_id FROM $table_name WHERE element_type = 'post_$post_type' AND language_code = '$current_lang'";
  $ids = $wpdb->get_results($sql);
  return array_column($ids, 'element_id');
}

add_filter('sweep_post_type', function($query) {
  global $sitepress;
  if ($sitepress) {
    $ids = get_post_translations($query['post_type']);
    $query['post__in'] = $ids;
    if (empty($ids)) $ids = [-1];
    $query['post__in'] = $ids;
  }
  return $query;
}, 10, 1);

add_action('ep_warm_site_cache', function() {
  global $sitepress;

  if (!isset($sitepress)) return;
  $current_lang = current_language();
  $languages    = apply_filters('wpml_active_languages', null) ?? array();
  $default_lang = $sitepress->get_default_language();
  foreach( $languages as $language ) {
    $lang = $language['code'];
    if ($default_lang === $lang) continue;
    // for languages that are not main language store data
    $sitepress->switch_lang($lang);
    ElasticPress\Storage\store_options('options');
    ElasticPress\Sweepers\sweep_menu_cache();
    ElasticPress\Sweepers\sweep_pages();
    ElasticPress\Sweepers\sweep_posts();
  }
  $sitepress->switch_lang($current_lang);
});

add_filter('ep_options_key', function($key) {
  $lang = current_language() ?? 'en';
  return $lang . "_" . $key;
}, 10, 3);

add_filter('ep_options_id', function($id) {
  $lang = current_language();
  if (
    empty($lang) ||
    $lang === default_language() ||
    (strpos($id, "_$lang") !== false)
  ) return $id;
  return $id . "_" . $lang;
}, 10, 3);

add_filter('ep_term_data', function($data, $term) {
  global $sitepress;
  global $icl_adjust_id_url_filter_off;

  if ($sitepress) {
    $orig_flag_value = $icl_adjust_id_url_filter_off;
    $term_id         = icl_object_id($term->term_id, $term->taxonomy, false, default_language());
    $data['locale']  = get_menu_language($term->term_id);
    $icl_adjust_id_url_filter_off = true;
    $data['source_slug'] = get_term($term_id)->slug;
    $icl_adjust_id_url_filter_off = $orig_flag_value;
  } else {
    $data['locale']      = 'en';
    $data['source_slug'] = $term->slug;
  }

  return $data;
}, 10, 2);

add_filter('ep_page_data', 'apply_wpml_data', 10, 2);
add_filter('ep_post_data', 'apply_wpml_data', 10, 2);
add_action('wp_insert_post', 'set_wpml_data', 10, 3);
add_action('saved_term', 'set_wpml_term_data', 10, 4);

function current_language() {
  global $sitepress;
  return ($sitepress) ? $sitepress->get_current_language() : null;
}

function switch_language($lang) {
  global $sitepress;
  if ($sitepress) {
    $sitepress->switch_lang($lang);
  }
}

function default_language() {
  global $sitepress;
  return ($sitepress) ? $sitepress->get_default_language() : null;
}

function get_menu_language($menu_id){
  global $sitepress, $wpdb;
  $sql  = "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy='nav_menu'";
  $t_id = $wpdb->get_var($wpdb->prepare($sql, $menu_id));
  $lang = $sitepress->get_element_language_details($t_id, 'tax_nav_menu');
  return (is_object($lang)) ? $lang->language_code : null;
}

function get_item_language($obj) {
  global $sitepress;
  $type = $obj->post_type;
  $item_details = $sitepress->get_element_language_details($obj->ID, "post_$type");
  if (isset($item_details->language_code)) {
    $item_lang = $item_details->language_code;
  } else {
    $item_lang = current_language();
  }
  return $item_lang;
}

function sweep_terms() {
  global $sitepress;

  $current_lang = current_language() ?? 'en';
  $languages    = apply_filters('wpml_active_languages', null) ?? array(array('code' => 'en'));
  $default_lang = (!isset($sitepress)) ? 'en' : $sitepress->get_default_language();
  foreach($languages as $language) {
    $lang = $language['code'];
    if ($default_lang !== $lang) $sitepress->switch_lang($lang);
    $taxonomies = get_taxonomies(array(
      '_builtin' => false,
    ));
    foreach($taxonomies as $taxonomy) {
      $terms = get_terms(
        array(
          'taxonomy'   => $taxonomy,
          'hide_empty' => false,
        )
      );
      foreach($terms as $term) {
        set_wpml_term_data($term->term_id);
      }
    }
  }
}

// this filter allows us to switch languages and get correct acf values
add_filter('acf/settings/current_language', function() {
  return current_language();
});
