<?php

if (is_multisite()) {
  add_action('wpmu_new_blog', function ($blog_id) {
    switch_to_blog((int) $blog_id);

    switch_theme('kp-nuxt');

    update_option('show_on_front', 'page');
    update_option('page_on_front', 2);

    $post = get_post(2);
    if ($post && function_exists('NuxtPress\\set_url_post_meta')) {
      \NuxtPress\set_url_post_meta(2, $post, true);
    }

    restore_current_blog();
  }, 10, 1);
}
