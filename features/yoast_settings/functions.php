<?php
function yoasttobottom() {
  return 'low';
}
add_filter('wpseo_metabox_prio', 'yoasttobottom');

// hide Yoast Premium notifications since this is a multisite and Yoast
// pretends we don't have an active subscription when we dos
add_action(
  'admin_head',
  function() {
  ?>
    <style type="text/css">
      .wpseo-admin-page .yoast-sidebar__section:first-child,
      .wpseo-admin-page .yoast-sidebar__section:last-child,
      .wpseo-admin-page .yoast-container__error,
      .wpseo-admin-page .yoast-container__warning,
      .wp-menu-name .update-plugins { display: none !important; }
    </style>
  <?php
  }
);
