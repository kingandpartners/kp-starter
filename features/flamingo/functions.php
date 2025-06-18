<?php

namespace NuxtSsr\Flamingo;

function store_message($data) {
  \Flamingo_Inbound_Message::add($data);
}

add_filter(
  'ep_indicies',
  function($indexes) {
    $new_indicies = array(
      'flamingo_contact',
      'flamingo_contact_tag',
      'flamingo_inbound',
      'flamingo_inbound_channel'
    );
    foreach($new_indicies as $new_index) {
      if (!in_array($new_index, $indexes)) {
        array_push($indexes, $new_index);
      }
    }
    return $indexes;
  },
  10, 2
);

add_filter(
  'ep_insert_post_object_filter',
  function($object) {
    $skip = array(
      'flamingo_contact',
      'flamingo_contact_tag',
      'flamingo_inbound',
      'flamingo_inbound_channel'
    );
    if (in_array($object->post_type, $skip)) {
      $object->skip_indexing = true;
    }
    return $object;
  },
  10, 2
);

/**
 * Flamingo_Inbound_Message::add was taking a very long time due to
 * wp_insert_post triggering the Yoast SEO indexable process. Flamingo post
 * types do not need to be indexed by Yoast SEO, so we exclude them.
 *
 */
add_filter(
  'wpseo_indexable_excluded_post_types',
  function($excluded_post_types) {
    $excluded_post_types[] = 'flamingo_contact';
    $excluded_post_types[] = 'flamingo_contact_tag';
    $excluded_post_types[] = 'flamingo_inbound';
    $excluded_post_types[] = 'flamingo_inbound_channel';
    return $excluded_post_types;
  },
  10, 1
);

/**
 * wp_insert_post also calls wp_unique_post_slug which also takes a very long
 * time to execute due to the number of flamingo_inbound posts. This filter
 * short circuits the slug generation for flamingo post types to return a
 * simple slug so the function can return quickly.
 *
 */
add_filter(
  'pre_wp_unique_post_slug',
  function($override_slug, $slug, $post_id, $post_status, $post_type, $post_parent) {
    $excluded_post_types = array(
      'flamingo_contact',
      'flamingo_contact_tag',
      'flamingo_inbound',
      'flamingo_inbound_channel'
    );
    if (in_array($post_type, $excluded_post_types)) {
      return "$slug-$post_id";
    }
    return $override_slug;
  },
  10, 6
);
