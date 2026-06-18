<?php

if (is_multisite()) {
  // Add Site and Domain fields to the network Add New Site form.
  add_action('network_site_new_form', function () {
    ?>
    <h2><?php esc_html_e('KP Settings', 'kp-starter'); ?></h2>
    <table class="form-table" role="presentation">
      <tr class="form-field">
        <th scope="row">
          <label for="kp_site_name"><?php esc_html_e('Site Name', 'kp-starter'); ?></label>
        </th>
        <td>
          <input type="text" name="kp_site_name" id="kp_site_name" class="regular-text" value="default" style="max-width: 25em;" />
          <p class="description"><?php esc_html_e('Adjusts which theme is used for the site (e.g. default).', 'kp-starter'); ?></p>
        </td>
      </tr>
      <tr class="form-field">
        <th scope="row">
          <label for="kp_site_domain"><?php esc_html_e('Domain', 'kp-starter'); ?></label>
        </th>
        <td>
          <input type="text" name="kp_site_domain" id="kp_site_domain" class="regular-text" value="" placeholder="e.g. example.com" style="max-width: 25em;" />
          <p class="description"><?php esc_html_e('The primary frontend domain for this site.', 'kp-starter'); ?></p>
        </td>
      </tr>
    </table>
    <?php
  });

  // When a new site is created: save the custom fields and apply defaults.
  add_action('wpmu_new_blog', function ($blog_id) {
    switch_to_blog((int) $blog_id);

    // Save globalOptionsComponentSite fields if submitted from the Add New Site form.
    if (!empty($_POST['kp_site_name'])) {
      update_option(
        'options_globalOptionsComponentSite_site',
        sanitize_text_field(wp_unslash($_POST['kp_site_name']))
      );
    }

    if (!empty($_POST['kp_site_domain'])) {
      update_option(
        'options_globalOptionsComponentSite_domain',
        sanitize_text_field(wp_unslash($_POST['kp_site_domain']))
      );
    }

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
