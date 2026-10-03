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

if ($failures) {
    exit(1);
}

echo "kp-starter generator tests passed.\n";
