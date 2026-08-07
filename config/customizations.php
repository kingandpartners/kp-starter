<?php

use Roots\WPConfig\Config;

use function Env\env;

// USE_ENV_ARRAY + CONVERT_* + STRIP_QUOTES
Env\Env::$options = 31;

/**
 * AWS S3 Settings
 */
Config::define('AS3CF_SETTINGS', serialize(array(
  'provider'          => 'aws',
  'access-key-id'     => env('AWS_ACCESS_KEY_ID'),
  'secret-access-key' => env('AWS_SECRET_ACCESS_KEY'),
  'bucket'            => env('AS3CF_BUCKET'),
  'region'            => env('AWS_REGION'),
  'copy-to-s3'        => true,
  'serve-from-s3'     => true,
  'force-https'       => true,
)));

/**
 * Bootstrap WordPress
 */
$frontend_domain = env('FRONTEND_DOMAIN') ?? 'localhost:8080';
$wp_domain       = env('WP_DOMAIN') ?? 'nuxt-ssr.test';

// Conditionally include generated SITE_URLS mapping if present
$project_root = getenv('PROJECT_ROOT') ?: dirname(__DIR__, 5);
$generated_site_urls = $project_root . '/wordpress/config/generated-site-urls.php';
if (file_exists($generated_site_urls)) {
  require_once $generated_site_urls;
} else {
  Config::define(
    'SITE_URLS',
    array(
      $wp_domain => $frontend_domain,
    )
  );
}

/**
 * Redis Settings
 * NOTE: Redis server is shared between environments, using numerical "DBs" for
 * isolation. All options get set via the REDIS_URL variable.
 *
 * Enviroment => DB Index
 * Staging => 1
 * Beta => 2
 * Production => 3
 */
Config::define('REDIS_OPTIONS', env('REDIS_URL'));

/**
 * Multisite
 */
if (env('MULTISITE')) {
  Config::define('WP_ALLOW_MULTISITE', true);
  Config::define('MULTISITE', true);
  Config::define('SUBDOMAIN_INSTALL', false);
  Config::define('DOMAIN_CURRENT_SITE', env('DOMAIN_CURRENT_SITE'));
  Config::define('PATH_CURRENT_SITE', '/');
  Config::define('SITE_ID_CURRENT_SITE', 1);
  Config::define('BLOG_ID_CURRENT_SITE', 1);
  Config::define('COOKIE_DOMAIN', '');
  Config::define('ADMIN_COOKIE_PATH', '/');
  Config::define('COOKIEPATH', '/');
  Config::define('SITECOOKIEPATH', '/');
}

/**
 * Memory Limit
 */
Config::define('WP_MEMORY_LIMIT', env('WP_MEMORY_LIMIT') ?? '128M');
