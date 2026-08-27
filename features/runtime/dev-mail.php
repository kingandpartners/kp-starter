<?php

/**
 * Keep development mail inside development.
 *
 * Post SMTP takes over the mail transport, so the MailHog wiring in
 * `features/mailhog` -- which works through `phpmailer_init` -- never gets the
 * chance to apply while Post SMTP is active. This package installs Post SMTP
 * itself, so it usually is.
 *
 * That matters more than an empty MailHog inbox. Post SMTP keeps its transport
 * in an option, which means it arrives with every database restored from
 * staging or production: a developer pulls a database and their machine is
 * quietly configured to send real mail, through the live account, to real
 * addresses. `wp_mail()` returns true, nothing is logged as an error, and the
 * only symptom is that MailHog stays empty.
 *
 * So the transport is forced rather than trusted. The option is data, and data
 * comes back with the next sync; only a filter holds in spite of it. The
 * stored credentials are left untouched -- they are simply never reached,
 * because the transport that would use them is no longer the active one.
 *
 * This lives in `runtime` and is required from the plugin bootstrap rather
 * than sitting with the rest of the MailHog code, and that placement is the
 * whole point: features load on `after_setup_theme`, whereas Post SMTP reads
 * the option into a singleton while plugins load. A filter added at feature
 * time is registered after the copy Post SMTP actually sends with has already
 * been taken, and is silently ignored.
 */

function kp_starter_is_development() {
  return defined('WP_ENV') ? WP_ENV === 'development' : getenv('WP_ENV') === 'development';
}

function kp_starter_development_mail_options($options) {
  if (!kp_starter_is_development()) {
    return $options;
  }

  return array_merge(is_array($options) ? $options : [], [
    'transport_type' => 'smtp',
    'hostname'       => 'mailhog',
    'port'           => '1025',
    'auth_type'      => 'none',
    'enc_type'       => 'none',
  ]);
}

// `option_` covers a stored value; `default_option_` covers a site that has
// never opened the Post SMTP settings, where there is no row to filter.
add_filter('option_postman_options', 'kp_starter_development_mail_options');
add_filter('default_option_postman_options', 'kp_starter_development_mail_options');
