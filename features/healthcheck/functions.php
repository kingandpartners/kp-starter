<?php

namespace Nuxt;

class HealthcheckController {
  public function register_routes() {
    $namespace = 'healthcheck/v1';

    // i.e. /wp-json/healthcheck/v1/status
    register_rest_route($namespace, '/status/', array(
      'methods'  => 'GET',
      'callback' => array($this, 'get_status'),
      'permission_callback' => '__return_true'
    ));
  }

  public function get_status($request) {
    $response = [
      'status' => 'ok'
    ];
    return new \WP_REST_Response($response, 200);
  }
}

add_action('rest_api_init', function() {
  $controller = new HealthcheckController;
  $controller->register_routes();
});
