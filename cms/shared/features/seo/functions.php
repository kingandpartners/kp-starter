<?php

function kp_nuxt_set_favicon($output) {
  $output['link'] = $output['link'] ?? [];
  $output['link'][] = ['rel' => 'icon', 'href' => get_site_icon_url()];
  return $output;
}

function kp_nuxt_add_canonical($data, $post) {
  $data['seo']['link'] = $data['seo']['link'] ?? [];
  $data['seo']['link'][] = ['rel' => 'canonical', 'href' => $data['url'] ?? ''];
  return $data;
}

add_filter('ep_seo_output', 'kp_nuxt_set_favicon', 10, 1);
add_filter('ep_post_data', 'kp_nuxt_add_canonical', 10, 2);
add_filter('ep_page_data', 'kp_nuxt_add_canonical', 10, 2);
