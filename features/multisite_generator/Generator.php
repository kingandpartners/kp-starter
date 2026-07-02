<?php

namespace KPMultisiteGenerator;

class Generator
{
  const GENERATED_APACHE_DIR = 'wordpress/web/conf/generated';
  const GENERATED_DEPLOY_COMPOSE = 'deploy/multisite.generated.yml';
  const GENERATED_AWS_COMPOSE = 'docker-compose-aws.yml';
  const GENERATED_LITE_COMPOSE = 'deploy/lite.yml';
  const GENERATED_LOCAL_COMPOSE = 'docker-compose.generated.yml';
  const GENERATED_LOCAL_ARM_COMPOSE = 'docker-compose.generated.arm.yml';
  const GENERATED_SITE_URLS = 'wordpress/config/generated-site-urls.php';

  public static function manifest_command()
  {
    self::ensure_multisite_enabled();
    self::cli('line', wp_json_encode(self::manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  public static function generate_command()
  {
    self::ensure_multisite_enabled();
    ['generated' => $generated, 'manifest' => $manifest] = self::generate();
    self::cli('success', sprintf(
      'Generated %s, and %d Apache vhosts.',
      implode(', ', $generated),
      count($manifest['sites'])
    ));
  }

  /**
   * Run the full generation pipeline and return the generated file list and
   * manifest. Safe to call outside of a CLI context (no output side-effects).
   */
  public static function generate()
  {
    $manifest = self::manifest();
    $is_local = 'development' === getenv('WP_ENV');
    $has_arm  = file_exists(self::project_path('docker-compose.arm.yml'));

    if ($is_local) {
      // Local: write all compose files into the repo tree so they can be committed.
      self::write_file(self::project_path(self::GENERATED_AWS_COMPOSE), self::render_aws_compose($manifest));
      self::write_file(self::project_path(self::GENERATED_LITE_COMPOSE), self::render_lite_compose($manifest));
      self::write_file(self::project_path(self::GENERATED_LOCAL_COMPOSE), self::render_local_compose($manifest));
      if ($has_arm) {
        self::write_file(self::project_path(self::GENERATED_LOCAL_ARM_COMPOSE), self::render_local_arm_compose($manifest));
      }
    } else {
      // Server: PROJECT_ROOT is the deploy tree root (e.g. /var/app/current).
      // Write multisite.generated.yml directly at the root — no deploy/ prefix —
      // so it lands on the bind-mounted shared path that docker compose can read.
      self::write_file(self::project_path('multisite.generated.yml'), self::render_deploy_compose($manifest));
    }

    self::write_apache_vhosts($manifest);
    self::write_site_urls_php($manifest);

    if ($is_local) {
      $generated = [
        self::GENERATED_AWS_COMPOSE,
        self::GENERATED_LITE_COMPOSE,
        self::GENERATED_LOCAL_COMPOSE,
      ];
      if ($has_arm) {
        $generated[] = self::GENERATED_LOCAL_ARM_COMPOSE;
      }
    } else {
      $generated = ['multisite.generated.yml'];
    }

    return compact('generated', 'manifest');
  }

  /**
   * Write the SITE_URLS PHP mapping file for config inclusion.
   */
  protected static function write_site_urls_php($manifest)
  {
    $lines = [];
    $lines[] = '<?php';
    $lines[] = '';
    $lines[] = 'use Roots\\WPConfig\\Config;';
    $lines[] = '';
    $lines[] = 'Config::define(';
    $lines[] = "  'SITE_URLS',";
    $lines[] = '  array(';

    // Collect all mappings first so we can sort them. More specific paths
    // (subsites) must come before the bare admin hostname so that URL matching
    // resolves correctly (longest/most-specific key first).
    $mappings = [];
    foreach ($manifest['sites'] as $site) {
      $site_path = $site['site_path'] ? '/' . $site['site_path'] : '';
      foreach ([
        getenv('ADMIN_SERVERNAME') => $site['local_domain'],
        getenv('BETA_ADMIN_DOMAIN') => $site['beta_domain'],
        getenv('PROD_ADMIN_DOMAIN') => $site['prod_domain'],
      ] as $host => $frontend) {
        if ($host && $frontend) {
          $mappings[$host . $site_path] = $frontend;
        }
      }
    }

    uksort($mappings, fn($a, $b) => strlen($b) - strlen($a));

    foreach ($mappings as $admin => $frontend) {
      $lines[] = sprintf("    '%s' => '%s',", $admin, $frontend);
    }

    $lines[] = '  )';
    $lines[] = ');';
    $lines[] = '';
    $path = self::project_path(self::GENERATED_SITE_URLS);
    self::write_file($path, implode(PHP_EOL, $lines));
  }

  public static function manifest()
  {
    $sites = get_sites(['number' => 0]);
    $manifest_sites = [];

    foreach ($sites as $index => $site) {
      $manifest_sites[] = self::site_manifest($site, $index);
    }

    return [
      'generated_at' => gmdate('c'),
      'project_root' => self::project_root(),
      'sites' => $manifest_sites,
    ];
  }

  protected static function site_manifest($site, $index)
  {
    switch_to_blog((int) $site->blog_id);

    $current_site = (string) get_option('options_globalOptionsComponentSite_site');
    $current_site = $current_site ?: self::fallback_current_site($site, $index);
    $config = self::site_config();
    $port = isset($config['port']) ? (int) $config['port'] : 3000 + $index;
    $service_name = $config['service_name'] ?? sprintf('nuxt_%d', $index + 1);
    $site_path = trim((string) ($site->path ?? '/'), '/');
    $site_home = get_home_url((int) $site->blog_id);
    $home_host = (string) parse_url($site_home, PHP_URL_HOST);
    $home_scheme = (string) parse_url($site_home, PHP_URL_SCHEME);
    // Read the ACF domain field directly — do not fall back to $home_host, which
    // in this setup is the admin URL, not the frontend domain.
    $acf_domain = (string) get_option('options_globalOptionsComponentSite_domain');
    $domain = $acf_domain ?: $home_host;
    $prod_domain = $config['prod_domain'] ?? ($acf_domain ?: '');
    // For local_domain: prefer the ACF field, then FRONTEND_DOMAIN, then derive
    // from ADMIN_SERVERNAME. Never fall back to $home_host (admin URL).
    $admin_servername = (string) getenv('ADMIN_SERVERNAME');
    $local_domain_fallback = $acf_domain
      ?: getenv('FRONTEND_DOMAIN')
      ?: ($admin_servername ? (string) preg_replace('/^[^.]+\./', '', $admin_servername) : '');
    $local_domain = $config['local_domain'] ?? $local_domain_fallback;
    $beta_domain = $config['beta_domain'] ?? '';
    $admin_path = $config['admin_path'] ?? ($site_path ? $site_path . '/' : '');

    restore_current_blog();

    return [
      'blog_id' => (int) $site->blog_id,
      'current_site' => $current_site,
      'service_name' => $service_name,
      'port' => $port,
      'site_path' => $site_path,
      'admin_path' => $admin_path,
      'home_url' => $site_home,
      'home_scheme' => $home_scheme,
      'prod_domain' => $prod_domain,
      'local_domain' => $local_domain,
      'beta_domain' => $beta_domain,
      'server_aliases' => array_values(array_filter(array_unique([
        $local_domain,
        $beta_domain,
        $prod_domain,
      ]))),
      'yoast_include' => self::yoast_redirect_include((int) $site->blog_id),
      'robots_file' => sanitize_file_name($current_site) . '.txt',
      'image_name' => sprintf('ghcr.io/${REPO_ORG}/${REPO_NAME}/%s_${WP_ENV}:latest', $service_name),
    ];
  }

  protected static function site_config()
  {
    $config = get_option('kp_multisite_generator_config');
    if (!is_array($config)) {
      $config = [];
    }

    return apply_filters('kp_multisite_generator_site_config', $config, get_current_blog_id());
  }

  protected static function render_deploy_compose($manifest)
  {
    $services = [];
    $services[] = 'services:';
    $services[] = '  mysql:';
    $services[] = '    environment:';
    $services[] = '      MYSQL_DATABASE: "${DB_NAME}"';
    $services[] = '      MYSQL_ROOT_PASSWORD: "${DB_PASSWORD}"';
    $services[] = '    healthcheck:';
    $services[] = "      test: \"tail -f /var/lib/mysql/error-log.log | sed -e '/ready for connections. Bind/q'\"";
    $services[] = '      timeout: 20s';
    $services[] = '      retries: 10';
    $services[] = '    image: mysql:8.0.36';
    $services[] = '    restart: unless-stopped';
    $services[] = '    volumes:';
    $services[] = '      - mysql-data:/var/lib/mysql';
    $services[] = '    networks:';
    $services[] = '      nuxt_ssr:';
    $services[] = '';
    $services[] = '  redis:';
    $services[] = '    environment:';
    $services[] = '      - ALLOW_EMPTY_PASSWORD=yes';
    $services[] = '    image: redis:latest';
    $services[] = '    restart: unless-stopped';
    $services[] = '    volumes:';
    $services[] = '      - redis-data:/data';
    $services[] = '    networks:';
    $services[] = '      nuxt_ssr:';
    $services[] = '';

    foreach ($manifest['sites'] as $site) {
      $services = array_merge($services, self::render_compose_service($site, './docker-compose.yml', 'nuxt', false));
      $services[] = '';
    }

    $services[] = '  wordpress:';
    $services[] = '    extends:';
    $services[] = '      file: ./docker-compose.yml';
    $services[] = '      service: wordpress';
    $services[] = '    networks:';
    $services[] = '      nuxt_ssr:';
    $services[] = '    environment:';
    $services[] = '      DOMAIN_CURRENT_SITE: "${DOMAIN_CURRENT_SITE}"';
    $services[] = '    env_file:';
    $services[] = '      - ./.env';
    $services[] = '';
    $services[] = 'volumes:';
    $services[] = '  mysql-data:';
    $services[] = '  redis-data:';
    $services[] = '';
    $services[] = 'networks:';
    $services[] = '  nuxt_ssr:';
    $services[] = '  traefik:';
    $services[] = '    external: true';

    return implode(PHP_EOL, $services) . PHP_EOL;
  }

  protected static function render_aws_compose($manifest)
  {
    $services = [];
    $services[] = 'services:';
    $services[] = '';

    foreach ($manifest['sites'] as $site) {
      $services[] = sprintf('  %s:', $site['service_name']);
      $services[] = sprintf('    image: "%s"', $site['image_name']);
      $services[] = '    extends:';
      $services[] = '      file: ./docker/services/nuxt.deploy.yml';
      $services[] = '      service: nuxt';
      if ($site['port'] !== 3000) {
        $services[] = sprintf('    command: sh -c "corepack yarn start --port %d"', $site['port']);
      }
      $services[] = '    build:';
      $services[] = '      args:';
      $services[] = sprintf('        CURRENT_SITE: %s', $site['current_site']);
      $services[] = '    environment:';
      $services[] = sprintf('      CURRENT_SITE: %s', $site['current_site']);
      if ($site['port'] !== 3000) {
        $services[] = sprintf('      HMR_PORT: %d', $site['port']);
      }
      $services[] = '    ports:';
      $services[] = sprintf('      - %d:%d', $site['port'], $site['port']);
      $services[] = '';
    }

    $services[] = '  wordpress:';
    $services[] = '    extends:';
    $services[] = '      file: ./docker/services/wordpress.deploy.yml';
    $services[] = '      service: wordpress';
    $services[] = '';
    $services[] = 'networks:';
    $services[] = '  nuxt_ssr:';

    return implode(PHP_EOL, $services) . PHP_EOL;
  }

  protected static function render_lite_compose($manifest)
  {
    $services = [];
    $services[] = 'services:';
    $services[] = '  mysql:';
    $services[] = '    environment:';
    $services[] = '      MYSQL_DATABASE: "${DB_NAME}"';
    $services[] = '      MYSQL_ROOT_PASSWORD: "${DB_PASSWORD}"';
    $services[] = '    healthcheck:';
    $services[] = "      test: \"tail -f /var/lib/mysql/error-log.log | sed -e '/ready for connections. Bind/q'\"";
    $services[] = '      timeout: 20s';
    $services[] = '      retries: 10';
    $services[] = '    image: mysql:8.0.36';
    $services[] = '    restart: unless-stopped';
    $services[] = '    volumes:';
    $services[] = '      - mysql-data:/var/lib/mysql';
    $services[] = '    networks:';
    $services[] = '      nuxt_ssr:';
    $services[] = '';
    $services[] = '  redis:';
    $services[] = '    environment:';
    $services[] = '      - ALLOW_EMPTY_PASSWORD=yes';
    $services[] = '    image: redis:latest';
    $services[] = '    restart: unless-stopped';
    $services[] = '    volumes:';
    $services[] = '      - redis-data:/data';
    $services[] = '    networks:';
    $services[] = '      nuxt_ssr:';
    $services[] = '';

    foreach ($manifest['sites'] as $site) {
      $name = $site['service_name'];
      $domain = $site['beta_domain'] ?: $site['prod_domain'];
      $services[] = sprintf('  %s:', $name);
      $services[] = sprintf('    image: "%s"', $site['image_name']);
      $services[] = '    extends:';
      $services[] = '      file: ./docker-compose.yml';
      $services[] = '      service: nuxt';
      if ($site['port'] !== 3000) {
        $services[] = sprintf('    command: sh -c "corepack yarn start --port %d"', $site['port']);
      }
      $services[] = '    environment:';
      $services[] = sprintf('      CURRENT_SITE: %s', $site['current_site']);
      if ($domain) {
        $services[] = sprintf('      FRONTEND_DOMAIN: %s', $domain);
        $services[] = sprintf('      FRONTEND_URL: https://%s', $domain);
      }
      $services[] = '    ports:';
      $services[] = sprintf('      - %d:%d', $site['port'], $site['port']);
      $services[] = '    networks:';
      $services[] = '      nuxt_ssr:';
      $services[] = '      traefik:';
      if ($domain) {
        $services[] = '    labels:';
        $services[] = '      - "traefik.enable=true"';
        $services[] = sprintf('      - "traefik.http.routers.%s.rule=Host(`%s`)"', $name, $domain);
        $services[] = sprintf('      - "traefik.http.routers.%s.entrypoints=websecure"', $name);
        $services[] = sprintf('      - "traefik.http.routers.%s.service=%s"', $name, $name);
        $services[] = sprintf('      - "traefik.http.routers.%s.middlewares=${TRAEFIK_MIDDLEWARES:-no-www@file}"', $name);
        $services[] = sprintf('      - "traefik.http.services.%s.loadbalancer.server.port=%d"', $name, $site['port']);
      }
      $services[] = '';
    }

    $admin_servername = getenv('ADMIN_SERVERNAME') ?: '${ADMIN_SERVERNAME}';
    $services[] = '  wordpress:';
    $services[] = '    extends:';
    $services[] = '      file: ./docker-compose.yml';
    $services[] = '      service: wordpress';
    $services[] = '    networks:';
    $services[] = '      nuxt_ssr:';
    $services[] = '      traefik:';
    $services[] = '    environment:';
    $services[] = '      DOMAIN_CURRENT_SITE: "${DOMAIN_CURRENT_SITE}"';
    $services[] = '    labels:';
    $services[] = '      - "traefik.enable=true"';
    $services[] = sprintf('      - "traefik.http.routers.wordpress.rule=Host(`%s`)"', $admin_servername);
    $services[] = '      - "traefik.http.routers.wordpress.entrypoints=websecure"';
    $services[] = '      - "traefik.http.routers.wordpress.service=wordpress"';
    $services[] = '      - "traefik.http.services.wordpress.loadbalancer.server.port=80"';
    $services[] = '';
    $services[] = 'volumes:';
    $services[] = '  mysql-data:';
    $services[] = '  redis-data:';
    $services[] = '';
    $services[] = 'networks:';
    $services[] = '  nuxt_ssr:';
    $services[] = '  traefik:';
    $services[] = '    external: true';

    return implode(PHP_EOL, $services) . PHP_EOL;
  }

  protected static function render_local_compose($manifest)
  {
    $aliases = self::compose_aliases($manifest);

    $services = [];
    $services[] = 'services:';
    $services[] = '  mysql:';
    $services[] = '    extends:';
    $services[] = '      file: ./docker/services/mysql.yml';
    $services[] = '      service: mysql';
    $services[] = '';

    foreach ($manifest['sites'] as $site) {
      $services = array_merge($services, self::render_compose_service($site, './docker/services/nuxt.yml', 'nuxt', true));
      $services[] = '';
    }

    $services[] = '  redis:';
    $services[] = '    extends:';
    $services[] = '      file: ./docker/services/redis.yml';
    $services[] = '      service: redis';
    $services[] = '';
    $services[] = '  wordpress:';
    $services[] = '    extends:';
    $services[] = '      file: ./docker/services/wordpress.dev.yml';
    $services[] = '      service: wordpress';
    $services[] = '    networks:';
    $services[] = '      default:';
    $services[] = '        aliases:';
    foreach ($aliases as $alias) {
      $services[] = sprintf('          - "%s"', $alias);
    }
    $services[] = '';
    $services[] = 'volumes:';
    $services[] = '  mysql-data:';
    $services[] = '  redis-data:';

    return implode(PHP_EOL, $services) . PHP_EOL;
  }

  protected static function render_local_arm_compose($manifest)
  {
    $services = [];
    $services[] = 'services:';
    $services[] = '  mysql:';
    $services[] = '    extends:';
    $services[] = '      file: ./docker-compose.generated.yml';
    $services[] = '      service: mysql';
    $services[] = '';

    foreach ($manifest['sites'] as $site) {
      $services[] = sprintf('  %s:', $site['service_name']);
      $services[] = '    extends:';
      $services[] = '      file: ./docker-compose.generated.yml';
      $services[] = sprintf('      service: %s', $site['service_name']);
      $services[] = '';
    }

    $services[] = '  redis:';
    $services[] = '    extends:';
    $services[] = '      file: ./docker/services/redis.yml';
    $services[] = '      service: redis_arm';
    $services[] = '';
    $services[] = '  wordpress:';
    $services[] = '    extends:';
    $services[] = '      file: ./docker-compose.generated.yml';
    $services[] = '      service: wordpress';
    $services[] = '';
    $services[] = 'volumes:';
    $services[] = '  mysql-data:';
    $services[] = '  redis-data:';

    return implode(PHP_EOL, $services) . PHP_EOL;
  }

  protected static function compose_aliases($manifest)
  {
    $aliases = [];
    foreach ($manifest['sites'] as $site) {
      foreach ($site['server_aliases'] as $alias) {
        if (!in_array($alias, $aliases, true)) {
          $aliases[] = $alias;
        }
      }
    }

    return $aliases;
  }

  protected static function render_compose_service($site, $file, $service, $local)
  {
    $lines = [];
    $lines[] = sprintf('  %s:', $site['service_name']);
    if (!$local) {
      $lines[] = sprintf('    image: "%s"', $site['image_name']);
    }
    $lines[] = '    extends:';
    $lines[] = sprintf('      file: %s', $file);
    $lines[] = sprintf('      service: %s', $service);
    if ($local) {
      $lines[] = sprintf('    command: sh -c "docker/scripts/healthcheck && corepack yarn dev --port %d"', $site['port']);
    } elseif ($site['port'] !== 3000) {
      $lines[] = sprintf('    command: sh -c "corepack yarn start --port %d"', $site['port']);
    }
    $lines[] = '    ports:';
    $lines[] = sprintf('      - %d:%d', $site['port'], $site['port']);
    $lines[] = '    environment:';
    $lines[] = sprintf('      CURRENT_SITE: %s', $site['current_site']);
    $domain = $local ? $site['local_domain'] : $site['prod_domain'];
    if ($domain) {
      $scheme = $local ? ($site['home_scheme'] ?: 'http') : 'https';
      $lines[] = sprintf('      FRONTEND_DOMAIN: %s', $domain);
      $lines[] = sprintf('      FRONTEND_URL: %s://%s', $scheme, $domain);
    }
    if ($local) {
      $lines[] = sprintf('      HMR_PORT: %d', $site['port']);
    }
    if (!$local && $domain) {
      $name = $site['service_name'];
      $lines[] = '    labels:';
      $lines[] = '      - "traefik.enable=true"';
      $lines[] = sprintf('      - "traefik.http.routers.%s.rule=Host(`%s`)"', $name, $domain);
      $lines[] = sprintf('      - "traefik.http.routers.%s.entrypoints=websecure"', $name);
      $lines[] = sprintf('      - "traefik.http.routers.%s.service=wordpress"', $name);
      $lines[] = sprintf('      - "traefik.http.routers.%s.middlewares=${TRAEFIK_MIDDLEWARES:-no-www@file}"', $name);
      $lines[] = sprintf('      - "traefik.http.services.%s.loadbalancer.server.port=80"', $name);
    }
    $lines[] = '    networks:';
    if ($local) {
      $lines[] = '      default:';
    } else {
      $lines[] = '      nuxt_ssr:';
      $lines[] = '      traefik:';
    }
    return $lines;
  }

  protected static function write_apache_vhosts($manifest)
  {
    $directory = self::project_path(self::GENERATED_APACHE_DIR);
    if (!is_dir($directory)) {
      wp_mkdir_p($directory);
    }

    foreach (glob($directory . '/*.conf') ?: [] as $file) {
      unlink($file);
    }

    foreach ($manifest['sites'] as $site) {
      // Skip the primary site — nuxt.conf already handles it and proxies to
      // NUXT_HOST (nuxt_1). Generating site-1.conf would duplicate that vhost.
      if ((int) $site['blog_id'] === 1) {
        continue;
      }
      $path = $directory . '/site-' . $site['blog_id'] . '.conf';
      self::write_file($path, self::render_apache_vhost($site));
    }
  }

  protected static function render_apache_vhost($site)
  {
    $lines = [];
    $lines[] = '<VirtualHost *:80>';
    $lines[] = sprintf('  ServerName %s', $site['local_domain']);
    $lines[] = '';
    $lines[] = '  DocumentRoot ${APACHE_DOCUMENT_ROOT}';
    $lines[] = '  Protocols h2 http/1.1';
    $lines[] = '  RewriteEngine on';
    $lines[] = '  SSLProxyEngine on';
    $lines[] = '  SSLProxyVerify none';
    $lines[] = '  SSLProxyCheckPeerCN off';
    $lines[] = '  SSLProxyCheckPeerName off';
    $lines[] = '  SSLProxyCheckPeerExpire off';
    $lines[] = '';
    $lines[] = '  RewriteCond %{REQUEST_URI} ^/robots\.txt$ [NC]';
    $lines[] = sprintf('  RewriteRule ^/(.*)$ ${APACHE_DOCUMENT_ROOT}/robots/%s [L]', $site['robots_file']);
    $lines[] = '';
    $lines[] = sprintf('  IncludeOptional %s', $site['yoast_include']);
    $lines[] = '';

    foreach (['local' => 'http', 'beta' => 'https', 'production' => 'https'] as $environment => $protocol) {
      $host = $environment === 'local' ? $site['local_domain'] : ($environment === 'beta' ? $site['beta_domain'] : $site['prod_domain']);
      $admin_host = $site['admin_hosts'][$environment] ?? '';
      if (!$host || !$admin_host) continue;

      $lines[] = sprintf('  RewriteCond %%{HTTP_HOST} ^%s$ [NC]', preg_quote($host, '/'));
      $lines[] = '  RewriteCond %{REQUEST_URI} \.xsl$ [NC]';
      $lines[] = sprintf('  RewriteRule ^/(.*) %s://%s/$1 [L,NC,P]', $protocol, $admin_host);
      $lines[] = '';
      $lines[] = sprintf('  RewriteCond %%{HTTP_HOST} ^%s$ [NC]', preg_quote($host, '/'));
      $lines[] = '  RewriteCond %{REQUEST_URI} \.xml$ [NC]';
      if ($site['admin_path']) {
        $lines[] = sprintf('  RewriteRule ^/(.*) %s://%s/%s$1 [L,NC,P]', $protocol, $admin_host, $site['admin_path']);
      } else {
        $lines[] = sprintf('  RewriteRule ^/(.*) %s://%s/$1 [L,NC,P]', $protocol, $admin_host);
      }
      $lines[] = '';
    }

    $lines[] = '  RewriteCond %{REQUEST_URI} !\.(xml|xsl)$ [NC]';
    $lines[] = sprintf('  RewriteRule ^/(.*) http://%s:%d/$1 [P,L]', $site['service_name'], $site['port']);
    $lines[] = sprintf('  ProxyPassReverse / "http://%s:%d/"', $site['service_name'], $site['port']);
    $lines[] = '';
    $lines[] = '  RequestHeader unset X-Forwarded-Host';
    $lines[] = '';
    $lines[] = '  <Directory ${APACHE_DOCUMENT_ROOT}>';
    $lines[] = '    Options FollowSymLinks MultiViews';
    $lines[] = '    AllowOverride All';
    $lines[] = '    Require all granted';
    $lines[] = '  </Directory>';
    $lines[] = '</VirtualHost>';

    return implode(PHP_EOL, $lines) . PHP_EOL;
  }

  protected static function ensure_multisite_enabled()
  {
    if ('true' === strtolower((string) getenv('MULTISITE'))) {
      return;
    }

    self::cli('error', 'MULTISITE must be set to true before running the multisite generator.');
  }

  protected static function cli($method, $message)
  {
    if (class_exists('WP_CLI')) {
      call_user_func(['WP_CLI', $method], $message);
      return;
    }

    if ('error' === $method) {
      wp_die(esc_html($message));
    }

    echo $message . PHP_EOL;
  }

  protected static function fallback_current_site($site, $index)
  {
    $path = trim((string) ($site->path ?? '/'), '/');
    if ($path) {
      return str_replace('-', '_', $path);
    }

    return $index === 0 ? 'default' : sprintf('site_%d', $site->blog_id);
  }

  protected static function yoast_redirect_include($blog_id)
  {
    if (1 === $blog_id) {
      return '${APACHE_DOCUMENT_ROOT}/app/uploads/wpseo-redirects/.redirects';
    }

    return sprintf('${APACHE_DOCUMENT_ROOT}/app/uploads/sites/%d/wpseo-redirects/.redirects', $blog_id);
  }

  protected static function write_file($path, $contents)
  {
    $directory = dirname($path);
    if (!is_dir($directory)) {
      wp_mkdir_p($directory);
    }

    file_put_contents($path, $contents);
  }

  protected static function project_root()
  {
    return rtrim((string) getenv('PROJECT_ROOT'), '/');
  }

  protected static function project_path($path)
  {
    return self::project_root() . '/' . ltrim($path, '/');
  }
}
