<?php

// NOTE: you must disable the WP Offload SES Lite plugin for this to work

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
    return ('development' === getenv('WP_ENV'));
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
