<?php

// Yoast Redirects are handled using Apache redirects loaded in separate files.
//
// Upon save of redirects, Yoast generates the .redirect files mentioned above.
// In order to enact these updates we reload apache (httpd) after a 5 second
// delay to allow for those files to be updated. That is what this script does below.
add_action('update_option_wpseo-premium-redirects-export-regex', 'update_redirect_files', 10, 3);
add_action('update_option_wpseo-premium-redirects-export-plain', 'update_redirect_files', 10, 3);

function command_exists($cmd) {
  $return = shell_exec(sprintf("which %s", escapeshellarg($cmd)));
  return !empty($return);
}

function update_redirect_files( $old_value, $value, $option_name ) {
    // In order for this command to run the `apache` user needs sudo access to the command
    // to do this I added a file in /etc/sudoers.d/apache with the following content:
    // apache ALL=NOPASSWD: /bin/systemctl reload httpd.service
    $reload_cmd = "";
    if (command_exists('systemctl')) {
      $reload_cmd = 'sudo /bin/systemctl reload httpd.service';
    } elseif (command_exists('apachectl')) {
      $reload_cmd = "apachectl graceful";
    }
    $file = WPSEO_Redirect_File_Util::get_file_path();
    $cmd  = 'sed -i -e \'s#" "/#" "' . getenv('FRONTEND_URL') . '/#g\' ' . $file . ' && ';
    $cmd .= $reload_cmd;
    $cmd  = "( sleep 5 ; $cmd ) > /dev/null &";
    system($cmd);
    error_log($cmd);
}

// since Yoast doesn't call install in a must-use setup we manually call the
// function to create the redirect directory
$redirect_dir = WPSEO_Redirect_File_Util::get_dir();
if (!is_dir($redirect_dir)) {
  WPSEO_Redirect_File_Util::create_upload_dir();
}

// Helper to parse exported redirects and ensure they match the current site
// and lead to a 200 response
//
// require __DIR__ . '/RedirectCsv.php';
// function run_csv_parse() {
//     $csv = new RedirectCsv('/var/www/html/wordpress-seo-redirects-sto.csv');
//     return $csv->update_prefix();
// }

// Yoast inconsistently sets incorrect redirects automatically
// to avoid bad redirect issues we disable all automatic redirects
add_filter('Yoast\WP\SEO\post_redirect_slug_change', '__return_true');
add_filter('Yoast\WP\SEO\term_redirect_slug_change', '__return_true');
add_filter('Yoast\WP\SEO\enable_notification_post_trash', '__return_false');
add_filter('Yoast\WP\SEO\enable_notification_post_slug_change', '__return_false');
add_filter('Yoast\WP\SEO\enable_notification_term_delete', '__return_false');
add_filter('Yoast\WP\SEO\enable_notification_term_slug_change', '__return_false');
