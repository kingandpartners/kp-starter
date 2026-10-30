<?php

use function NuxtSsr\Redis\redis_get;
use function NuxtSsr\Redis\redis_store;

const KP_YOAST_REDIRECTS_OPTION = 'wpseo-premium-redirects-base';
const KP_YOAST_REDIRECTS_REDIS_KEY = 'yoast_redirects';
const KP_YOAST_REDIRECTS_SYNC_TRANSIENT = 'kp_yoast_redirects_synced';

function kp_normalize_redirect_path($path)
{
    $parsed_path = wp_parse_url($path, PHP_URL_PATH);
    if (is_string($parsed_path)) {
        $path = $parsed_path;
    }

    $path = rawurldecode('/'.ltrim((string) $path, '/'));

    return $path === '/' ? $path : untrailingslashit($path);
}

function kp_build_yoast_redirect_payload($redirects)
{
    $exact = [];
    $regex = [];
    $valid_statuses = [301, 302, 307, 410, 451];

    foreach ((array) $redirects as $redirect) {
        if (! is_array($redirect)) {
            continue;
        }

        $origin = (string) ($redirect['origin'] ?? '');
        $target = (string) ($redirect['url'] ?? '');
        $status = (int) ($redirect['type'] ?? 0);
        $format = (string) ($redirect['format'] ?? 'plain');

        if ($origin === '' || ! in_array($status, $valid_statuses, true)) {
            continue;
        }

        if ($status < 400 && $target === '') {
            continue;
        }

        if ($status < 400 && ! str_starts_with($target, '/') && ! wp_parse_url($target, PHP_URL_SCHEME)) {
            $target = '/'.$target;
        }

        $entry = [
            'target' => $target,
            'status' => $status,
        ];

        if ($format === 'regex') {
            $regex[] = array_merge(['origin' => $origin], $entry);
            continue;
        }

        $exact[kp_normalize_redirect_path($origin)] = $entry;
    }

    $redirect_data = [
        'exact' => $exact,
        'regex' => $regex,
    ];
    $encoded_data = wp_json_encode($redirect_data);

    return array_merge([
        'version' => hash('sha256', $encoded_data === false ? '' : $encoded_data),
        'generated_at' => gmdate(DATE_ATOM),
    ], $redirect_data);
}

function kp_publish_yoast_redirects($redirects)
{
    try {
        redis_store(
            KP_YOAST_REDIRECTS_REDIS_KEY,
            kp_build_yoast_redirect_payload($redirects)
        );
        set_transient(
            KP_YOAST_REDIRECTS_SYNC_TRANSIENT,
            true,
            5 * MINUTE_IN_SECONDS
        );
    } catch (Throwable $exception) {
        error_log(sprintf(
            'Unable to publish Yoast redirects to Redis: %s',
            $exception->getMessage()
        ));
    }

    return $redirects;
}

add_filter(
    'Yoast\\WP\\SEO\\save_redirects',
    'kp_publish_yoast_redirects',
    20,
    1
);

function kp_ensure_yoast_redirects_are_published()
{
    if (get_transient(KP_YOAST_REDIRECTS_SYNC_TRANSIENT)) {
        return;
    }

    set_transient(
        KP_YOAST_REDIRECTS_SYNC_TRANSIENT,
        true,
        MINUTE_IN_SECONDS
    );

    $redirects = get_option(KP_YOAST_REDIRECTS_OPTION, []);
    $payload = kp_build_yoast_redirect_payload($redirects);

    try {
        $published_payload = redis_get(KP_YOAST_REDIRECTS_REDIS_KEY);
        if (($published_payload['version'] ?? null) !== $payload['version']) {
            redis_store(KP_YOAST_REDIRECTS_REDIS_KEY, $payload);
        }

        set_transient(
            KP_YOAST_REDIRECTS_SYNC_TRANSIENT,
            true,
            5 * MINUTE_IN_SECONDS
        );
    } catch (Throwable $exception) {
        error_log(sprintf(
            'Unable to synchronize Yoast redirects with Redis: %s',
            $exception->getMessage()
        ));
    }
}

add_action('init', 'kp_ensure_yoast_redirects_are_published', 100);

add_filter('kp_seo_redirects_command', function ($cmd, $file) {
    $home_url = home_url('/');

    $cmd = 'sed -i -e \'s#^Redirect 410 "\\([^\"]*\\)"#RewriteRule ^\\1/?$ - [G,L]#g\' '.$file;
    $cmd .= ' && sed -i -e \'s#^Redirect 451 "\\([^\"]*\\)"#RewriteRule ^\\1/?$ - [R=451,L]#g\' '.$file;
    $cmd .= ' && sed -i -e \'s#^Redirect \\([0-9]\\{3\\}\\) "\\([^\"]*\\)" "\\(https\\?://[^\"]*\\)"#RewriteRule ^\\2/?$ \\3 [R=\\1,L]#g\' '.$file;
    $cmd .= ' && sed -i -e \'s#^Redirect \\([0-9]\\{3\\}\\) "\\([^\"]*\\)" "\\(/[^\"]*\\)"#RewriteRule ^\\2/?$ '.rtrim($home_url, '/').'\\3 [R=\\1,L]#g\' '.$file;

    return $cmd;
}, 10, 3);
