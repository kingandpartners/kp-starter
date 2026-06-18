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
  add_action('wp_initialize_site', function (WP_Site $new_site, array $args) {
    switch_to_blog((int) $new_site->blog_id);

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

    update_option('permalink_structure', '/%postname%/');
    flush_rewrite_rules(false);

    $post = get_post(2);
    if ($post) {
      // Trigger a full wp_update_post so wp_insert_post fires after the
      // permalink structure is set — this sets _url meta and bumps the cache,
      // making the homepage visible in the NuxtPress API immediately.
      wp_update_post(['ID' => 2]);
    }

    // Scaffold theme directory from createCustomTheme (mirrors init.mjs) if site name provided.
    $site_name = !empty($_POST['kp_site_name'])
      ? sanitize_text_field(wp_unslash($_POST['kp_site_name']))
      : '';
    $project_root = rtrim((string) getenv('PROJECT_ROOT'), '/');
    if ($site_name && $project_root) {
      $theme_dir = $project_root . '/src/themes/' . $site_name;
      if (!is_dir($theme_dir)) {
        kp_scaffold_theme($theme_dir, $site_name);
      }
    }

    restore_current_blog();
  }, 10, 2);
}

if (!function_exists('kp_scaffold_theme')) {
  function kp_scaffold_theme($theme_dir, $theme_name) {
    $dirs = [
      $theme_dir . '/components',
      $theme_dir . '/templates',
      $theme_dir . '/assets/scss/base',
      $theme_dir . '/assets/scss/abstracts',
    ];
    foreach ($dirs as $dir) {
      wp_mkdir_p($dir);
    }

    // .gitkeep for empty dirs
    file_put_contents($theme_dir . '/components/.gitkeep', '');
    file_put_contents($theme_dir . '/templates/.gitkeep', '');

    // abstracts/_colors.scss — required by shared _functions.scss
    file_put_contents(
      $theme_dir . '/assets/scss/abstracts/_colors.scss',
      "/* {$theme_name} colors */\n\$colors: (\n  'white': #ffffff,\n  'black': #000000,\n  'error-red': #cb0000,\n);\n"
    );

    // base/index.scss
    file_put_contents(
      $theme_dir . '/assets/scss/base/index.scss',
      "// {$theme_name} theme styles\n// Add theme-specific styles here\n"
    );

    // Per-theme layout file in src/layouts/
    $layouts_dir  = dirname($theme_dir) . '/../layouts';
    $layout_file  = $layouts_dir . '/' . $theme_name . '.vue';
    if (!file_exists($layout_file)) {
      wp_mkdir_p($layouts_dir);
      file_put_contents(
        $layout_file,
        "<template>\n  <div class=\"{$theme_name}\">\n    <SkipToMainContent />\n\n    <main id=\"content\">\n      <NuxtPage />\n    </main>\n\n  </div>\n</template>\n"
      );
    }
  }
}
