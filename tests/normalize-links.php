<?php

declare(strict_types=1);

// features/normalize_links (normalize_links_for_elasticpress): a link to the
// FRONTEND_URL host becomes relative on a single site, and is left alone on
// multisite, where FRONTEND_URL is the main site's front end, not the current
// site's (#18).

$root = dirname(__DIR__);

$GLOBALS['kp_test_multisite'] = false;

function add_filter(...$args) {}

function is_multisite() {
  return $GLOBALS['kp_test_multisite'];
}

putenv('FRONTEND_URL=https://main.example.com');

require $root . '/features/normalize_links/functions.php';

$assert = function (bool $condition, string $message) {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};

$link = fn(string $url) => ['url' => $url, 'title' => 'Link', 'target' => ''];

// Single site: links to its own front end become relative.
$GLOBALS['kp_test_multisite'] = false;
$assert(
  normalize_links_for_elasticpress($link('https://main.example.com/'), 1, [])['url'] === '/',
  'Single site: the front end home should become /',
);
$assert(
  normalize_links_for_elasticpress($link('https://main.example.com/about/?a=1#b'), 1, [])['url'] === '/about/?a=1#b',
  'Single site: the path, query and fragment should be kept',
);
$assert(
  normalize_links_for_elasticpress($link('https://other.example.com/'), 1, [])['url'] === 'https://other.example.com/',
  'Single site: other hosts should be left alone',
);
$assert(
  normalize_links_for_elasticpress('', 1, []) === null,
  'An empty link should load as null',
);

// Multisite: a link to the main site's front end keeps its domain.
$GLOBALS['kp_test_multisite'] = true;
$assert(
  normalize_links_for_elasticpress($link('https://main.example.com/'), 3, [])['url'] === 'https://main.example.com/',
  'Multisite: a link to the main site should keep its domain',
);
$assert(
  normalize_links_for_elasticpress('', 3, []) === null,
  'Multisite: an empty link should still load as null',
);

echo "normalize-links: OK\n";
