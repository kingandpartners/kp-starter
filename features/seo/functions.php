<?php

add_filter('wpseo_enhanced_slack_data', function($data) {
  unset($data['Written by']);
  return $data;
});

add_filter('ep_seo_output', function($output){
  if (!isset($output['link'])) {
    $output['link'] = [];
  }

  // add the favicon from WordPress
  $output['link'][] = [
    'rel' => 'icon',
    'href' => get_site_icon_url(),
  ];
  return $output;
}, 10, 1);
