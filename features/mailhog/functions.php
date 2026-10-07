<?php

// NOTE: you must disable the WP Offload SES Lite plugin for this to work
//
// This hook only reaches the mailer when nothing else has taken the transport
// over. Post SMTP does exactly that, and this package installs it, so in most
// projects the setting that actually routes development mail to MailHog is the
// option filter in features/runtime/dev-mail.php. Both are kept: this covers a
// site running without Post SMTP.

class WP_MAILHOG {

  function __construct() {
    // Config only on local
    if ($this->isLocal()) {
      $this->AddSMTP();
    }
  }


  /**
   * Config Your local rule
   * default is check if the host is *.test or  *.local
   * @return bool
   */
  private function isLocal() {
    return kp_starter_is_development();
  }

  /*
    * Wordpress default hook to config php mail
    */
  private function AddSMTP() {
    add_action('phpmailer_init', array($this, 'configEmailSMTP'));
  }


  /*
   * Config MailHog SMTP
   */
  public function configEmailSMTP($phpmailer) {
    $phpmailer->IsSMTP();
    $phpmailer->Host     = 'mailhog';
    $phpmailer->Port     = 1025;
    $phpmailer->Username = '';
    $phpmailer->Password = '';
    $phpmailer->SMTPAuth = true;
  }
}

new WP_MAILHOG();

add_action('wp_mail_failed', function($error) {
  error_log(print_r($error, true));
}, 10, 1 );
