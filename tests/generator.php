<?php

/**
 * The per-site Apache vhosts written by `wp kp multisite generate`.
 *
 * Nuxt's dev server imports its virtual modules by ids with an encoded slash
 * (`/_nuxt-<site>/@id/virtual:nuxt:.nuxt-<site>%2Fnuxt.config.mjs`). With
 * Apache's default, AllowEncodedSlashes Off, every one of those requests 404s
 * before it reaches the proxy, so no page hydrates. The directive has to be in
 * each vhost: vhosts don't inherit it from the server config.
 */

declare(strict_types=1);

require __DIR__ . '/../features/multisite_generator/Generator.php';

$failures = [];

function kp_test(string $name, callable $assertion): void {
    global $failures;

    try {
        $assertion();
        echo "  ok   {$name}\n";
    } catch (Throwable $error) {
        $failures[] = $name;
        echo "  FAIL {$name}: {$error->getMessage()}\n";
    }
}

function kp_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function kp_render_vhost(array $site): string {
    $method = new ReflectionMethod(KPMultisiteGenerator\Generator::class, 'render_apache_vhost');
    return $method->invoke(null, $site);
}

$site = [
    'blog_id'       => 2,
    'prod_domain'   => 'example.com',
    'beta_domain'   => 'example-beta.example.com',
    'local_domain'  => 'example.test',
    'robots_file'   => 'example.txt',
    'yoast_include' => '${APACHE_DOCUMENT_ROOT}/app/uploads/sites/2/wpseo-redirects/.redirects',
    'admin_hosts'   => ['local' => 'admin.example.test', 'beta' => '', 'production' => ''],
    'admin_path'    => '',
    'service_name'  => 'nuxt_2',
    'port'          => 3001,
];

echo "Generator vhost tests\n";

kp_test('vhost accepts encoded slashes without decoding them', function () use ($site) {
    $vhost = kp_render_vhost($site);
    $open  = strpos($vhost, '<VirtualHost');
    $line  = strpos($vhost, "\n  AllowEncodedSlashes NoDecode\n");
    $close = strpos($vhost, '</VirtualHost>');

    kp_assert($line !== false, 'AllowEncodedSlashes NoDecode is missing');
    kp_assert($open < $line && $line < $close, 'AllowEncodedSlashes NoDecode is outside the VirtualHost');
});

kp_test('vhost still proxies to the site service', function () use ($site) {
    $vhost = kp_render_vhost($site);

    kp_assert(str_contains($vhost, 'RewriteRule ^/(.*) http://nuxt_2:3001/$1 [P,L]'), 'proxy rule is missing');
});

function kp_render(string $method_name, ...$arguments) {
    $method = new ReflectionMethod(KPMultisiteGenerator\Generator::class, $method_name);
    $result = $method->invoke(null, ...$arguments);
    return is_array($result) ? implode("\n", $result) : $result;
}

$sites = [
    $site + ['current_site' => 'example', 'image_name' => 'example-image'],
    array_merge($site, [
        'blog_id'      => 3,
        'prod_domain'  => 'other.com',
        'beta_domain'  => '',
        'service_name' => 'nuxt_3',
        'port'         => 3002,
        'current_site' => 'other',
        'image_name'   => 'other-image',
    ]),
];

echo "Generator Traefik middleware tests\n";

kp_test('deploy router reads its own middleware variable before the project-wide one', function () use ($sites) {
    $service = kp_render('render_compose_service', $sites[0], './docker-compose.yml', 'nuxt', false);

    kp_assert(
        str_contains($service, '"traefik.http.routers.nuxt_2.middlewares=${TRAEFIK_MIDDLEWARES_NUXT_2:-${TRAEFIK_MIDDLEWARES:-no-www@file}}"'),
        'per-site middleware label is missing'
    );
});

kp_test('lite compose gives each site its own middleware chain', function () use ($sites) {
    $compose = kp_render('render_lite_compose', ['sites' => $sites]);

    kp_assert(str_contains($compose, 'routers.nuxt_2.middlewares=${TRAEFIK_MIDDLEWARES_NUXT_2:-${TRAEFIK_MIDDLEWARES:-no-www@file}}'), 'nuxt_2 label is missing');
    kp_assert(str_contains($compose, 'routers.nuxt_3.middlewares=${TRAEFIK_MIDDLEWARES_NUXT_3:-${TRAEFIK_MIDDLEWARES:-no-www@file}}'), 'nuxt_3 label is missing');
});

kp_test('lite compose routes each site\'s XML through that site\'s middleware chain', function () use ($sites) {
    $compose = kp_render('render_lite_compose', ['sites' => $sites]);

    kp_assert(!str_contains($compose, 'routers.wordpress-public-xml.'), 'the shared XML router is still generated');
    kp_assert(str_contains($compose, 'routers.wordpress-public-xml-nuxt_2.rule=Host(`example-beta.example.com`) && PathRegexp('), 'nuxt_2 XML rule is missing');
    kp_assert(str_contains($compose, 'routers.wordpress-public-xml-nuxt_2.middlewares=${TRAEFIK_MIDDLEWARES_NUXT_2:-${TRAEFIK_MIDDLEWARES:-no-www@file}}'), 'nuxt_2 XML middlewares are missing');
    kp_assert(str_contains($compose, 'routers.wordpress-public-xml-nuxt_3.rule=Host(`other.com`) && PathRegexp('), 'nuxt_3 XML rule is missing');
    kp_assert(str_contains($compose, 'routers.wordpress-public-xml-nuxt_3.priority=100'), 'nuxt_3 XML priority is missing');
});

kp_test('middleware variable names are safe for service names with hyphens', function () {
    kp_assert(
        kp_render('traefik_middlewares', 'nuxt-sixth-blanco') === '${TRAEFIK_MIDDLEWARES_NUXT_SIXTH_BLANCO:-${TRAEFIK_MIDDLEWARES:-no-www@file}}',
        'unexpected variable name'
    );
});

if ($failures) {
    exit(1);
}

echo "kp-starter generator tests passed.\n";
