<?php

/**
 * Page template discovery, exercised against stubbed WordPress functions.
 *
 * The theme_templates filter must find templates in every root that
 * Site::register() loads field groups from, or WordPress rejects pages using
 * them as having an "Invalid page template".
 */

declare(strict_types=1);

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

function kp_assert($actual, $expected, string $message = ''): void {
    if ($actual !== $expected) {
        throw new RuntimeException(sprintf(
            '%s expected %s, got %s',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

/* -- WordPress stubs ---------------------------------------------------- */

$GLOBALS['kp_test_filters'] = [];
$GLOBALS['kp_test_options'] = [];

function add_filter(string $hook, $callback): void {
    $GLOBALS['kp_test_filters'][$hook][] = $callback;
}

function add_action(): void {
}

function add_post_type_support(): void {
}

function remove_post_type_support(): void {
}

function get_template_directory(): string {
    return '/var/www/wp-content/themes/nuxtpress';
}

function get_option(string $name) {
    return $GLOBALS['kp_test_options'][$name] ?? false;
}

/* -- Fixture project ------------------------------------------------------ */

$project_root = sys_get_temp_dir() . '/kp-starter-templates-' . getmypid();

foreach ([
    'cms/shared/templates/TemplateSplash/fields.json',
    'cms/sixth/templates/TemplateDetailLight/fields.json',
    'cms/other/templates/TemplateOtherSite/fields.json',
    'src/themes/timberhouse/templates/TemplateLegal/fields.json',
    'src/themes/timberhouse/templates/TemplateTeaser.vue',
    'cms/timberhouse/templates/TemplateLegal/fields.json',
] as $relative_path) {
    $path = "{$project_root}/{$relative_path}";
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, '{}');
}

register_shutdown_function(static function () use ($project_root): void {
    exec('rm -rf ' . escapeshellarg($project_root));
});

putenv("PROJECT_ROOT={$project_root}");

require dirname(__DIR__) . '/features/templates/functions.php';

function kp_templates(string $site): array {
    $GLOBALS['kp_test_options']['options_globalOptionsComponentSite_site'] = $site;
    $filter = $GLOBALS['kp_test_filters']['theme_templates'][0];

    return $filter([]);
}

/* -- Tests ------------------------------------------------------------------ */

echo "templates\n";

kp_test('registers templates whose fields live in cms/shared', function () {
    kp_assert(kp_templates('timberhouse')['template/template-splash.php'] ?? null, 'Splash');
});

kp_test('registers templates from cms/<site> for that site only', function () {
    kp_assert(kp_templates('sixth')['template/template-detail-light.php'] ?? null, 'Detail Light');
    kp_assert(isset(kp_templates('sixth')['template/template-other-site.php']), false, 'other site:');
});

kp_test('still registers src/themes/<site> templates, as directories or files', function () {
    $templates = kp_templates('timberhouse');
    kp_assert($templates['template/template-legal.php'] ?? null, 'Legal');
    kp_assert($templates['template/template-teaser.php'] ?? null, 'Teaser');
});

kp_test('lists a template found in more than one root once', function () {
    $legal = array_filter(kp_templates('timberhouse'), static fn($label) => $label === 'Legal');
    kp_assert(count($legal), 1);
});

kp_test('keeps templates already passed to the filter', function () {
    $filter = $GLOBALS['kp_test_filters']['theme_templates'][0];
    kp_assert($filter(['existing.php' => 'Existing'])['existing.php'] ?? null, 'Existing');
});

if ($failures) {
    exit(1);
}

echo "kp-starter template tests passed.\n";
