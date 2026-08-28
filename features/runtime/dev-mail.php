<?php

/**
 * Keep development mail, and production's mail credentials, inside their own
 * environments.
 *
 * Post SMTP takes over the mail transport, so the MailHog wiring in
 * `features/mailhog` -- which works through `phpmailer_init` -- never gets the
 * chance to apply while Post SMTP is active. This package installs Post SMTP
 * itself, so it usually is.
 *
 * That matters more than an empty MailHog inbox. Post SMTP keeps its transport
 * *and its credentials* in options, which means both arrive with every
 * database restored from staging or production: a developer pulls a database
 * and their machine is quietly configured to send real mail, through the live
 * account, to real addresses. `wp_mail()` returns true, nothing is logged as an
 * error, and the only symptom is that MailHog stays empty.
 *
 * So two things happen in development, and only in development.
 *
 * The transport is forced rather than trusted. The option is data, and data
 * comes back with the next sync; only a filter holds in spite of it.
 *
 * The credentials are then removed outright -- filtered out of every read, and
 * deleted from the row itself. Forcing the transport alone leaves the live API
 * key sitting in the database of every laptop that has ever pulled a copy,
 * where the next `wp db export`, the next sync onward, or anyone with the
 * dump, still has it. It cannot be lost by removing it here: development sends
 * through MailHog, which wants no credentials at all.
 *
 * This lives in `runtime` and is required from the plugin bootstrap rather
 * than sitting with the rest of the MailHog code, and that placement is the
 * whole point: features load on `after_setup_theme`, whereas Post SMTP reads
 * the option into a singleton while plugins load. A filter added at feature
 * time is registered after the copy Post SMTP actually sends with has already
 * been taken, and is silently ignored.
 */

/**
 * Option keys whose values are credentials.
 *
 * Matched by shape rather than listed by name. Post SMTP grows a provider or
 * two per release -- there are already more than twenty `*_api_key` entries --
 * and a list of names would go stale silently, which for this is the same as
 * not being here at all. Nothing in `postman_options` matches this and is not
 * a secret.
 */
const KP_STARTER_MAIL_SECRET_PATTERN = '/(password|secret|api_key|_key|token)/i';

/**
 * Options holding Post SMTP credentials, each with the filter that guards it.
 *
 * `postman_options` also has its transport forced; `postman_auth_token` is only
 * ever stripped. The scrub below restores exactly the filter an option had, so
 * the transport settings never leak into the token option.
 */
const KP_STARTER_MAIL_OPTIONS = [
  'postman_options'    => 'kp_starter_development_mail_options',
  'postman_auth_token' => 'kp_starter_development_mail_credentials',
];

function kp_starter_is_development() {
  return defined('WP_ENV') ? WP_ENV === 'development' : getenv('WP_ENV') === 'development';
}

/**
 * Blank every credential-shaped entry, leaving the rest of the option alone.
 *
 * Blanked rather than unset, because Post SMTP reads these keys directly and a
 * missing one is a notice where an empty one is simply unconfigured.
 */
function kp_starter_strip_mail_credentials($options) {
  if (!is_array($options)) {
    return $options;
  }

  foreach ($options as $key => $value) {
    if (is_string($key) && is_scalar($value) && preg_match(KP_STARTER_MAIL_SECRET_PATTERN, $key)) {
      $options[$key] = '';
    }
  }

  return $options;
}

function kp_starter_development_mail_options($options) {
  if (!kp_starter_is_development()) {
    return $options;
  }

  return array_merge(kp_starter_strip_mail_credentials(is_array($options) ? $options : []), [
    'transport_type' => 'smtp',
    'hostname'       => 'mailhog',
    'port'           => '1025',
    'auth_type'      => 'none',
    'enc_type'       => 'none',
  ]);
}

function kp_starter_development_mail_credentials($options) {
  return kp_starter_is_development() ? kp_starter_strip_mail_credentials($options) : $options;
}

/**
 * Delete the credentials from the rows themselves.
 *
 * The filters above stop this WordPress reading them; only this stops the next
 * database dump carrying them somewhere else.
 *
 * The stored value has to be read with the filters off, or the transport
 * settings forced above would be written back into the database and then
 * travel with it -- the filter exists precisely so that the row need not be
 * trusted, and persisting its output would undo that.
 *
 * Runs on `init` rather than on a sync, because there is no hook for "a
 * database just arrived": the credentials are gone by the first request after
 * one does. Both reads are of autoloaded options and the write only happens on
 * the request that finds something, so a scrubbed site pays a cached read and
 * a regex.
 */
function kp_starter_scrub_stored_mail_credentials() {
  if (!kp_starter_is_development()) {
    return;
  }

  foreach (KP_STARTER_MAIL_OPTIONS as $option => $filter) {
    remove_filter("option_$option", $filter);
    $stored = get_option($option);
    add_filter("option_$option", $filter);

    if (!is_array($stored)) {
      continue;
    }

    $scrubbed = kp_starter_strip_mail_credentials($stored);

    if ($scrubbed !== $stored) {
      update_option($option, $scrubbed);
    }
  }
}

// `option_` covers a stored value; `default_option_` covers a site that has
// never opened the Post SMTP settings, where there is no row to filter.
add_filter('option_postman_options', 'kp_starter_development_mail_options');
add_filter('default_option_postman_options', 'kp_starter_development_mail_options');

// The OAuth tokens live in their own option and need no transport forcing,
// only stripping.
add_filter('option_postman_auth_token', 'kp_starter_development_mail_credentials');

add_action('init', 'kp_starter_scrub_stored_mail_credentials');
