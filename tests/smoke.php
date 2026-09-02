<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$required_files = [
    'kp-starter.php',
    'url_modification.php',
    'config/application.php',
    'config/customizations.php',
    'features/runtime/bedrock-autoloader.php',
    'features/runtime/phpbrake-init.php',
    'features/runtime/route-error-logs.php',
    'features/acf-relationship-multisite/acf-relationship-multisite.php',
    'features/acf-relationship-multisite/acf-relationship-multisite-v5.php',
    'features/acf-relationship-multisite/js/input.js',
    'features/acf-relationship-multisite/lang/acf-relationship-multisite-de_DE.mo',
];

foreach ($required_files as $relative_path) {
    $path = $root . '/' . $relative_path;

    if (!is_file($path)) {
        throw new RuntimeException("Missing packaged file: {$relative_path}");
    }
}

$php_files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($php_files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $command = sprintf('php -l %s 2>&1', escapeshellarg($file->getPathname()));
    exec($command, $output, $exit_code);

    if ($exit_code !== 0) {
        throw new RuntimeException(implode(PHP_EOL, $output));
    }

    $output = [];
}

$bootstrap = file_get_contents($root . '/kp-starter.php');

foreach ([
    "features/runtime/bedrock-autoloader.php",
    "features/runtime/phpbrake-init.php",
    "features/runtime/route-error-logs.php",
    "features/acf-relationship-multisite/acf-relationship-multisite.php",
] as $required_include) {
    if (!str_contains($bootstrap, $required_include)) {
        throw new RuntimeException("Bootstrap does not include {$required_include}");
    }
}

$application_config = file_get_contents($root . '/config/application.php');

if (!str_contains($application_config, "getenv('WORDPRESS_ROOT') ?: dirname(__DIR__, 5)")) {
    throw new RuntimeException('Bedrock root must be independent of PROJECT_ROOT.');
}

define('WP_CLI', true);

class WP_CLI
{
    public static array $commands = [];

    public static function add_command(string $name, callable $handler): void
    {
        self::$commands[$name] = $handler;
    }
}

function add_action(): void
{
}

require $root . '/features/multisite_generator/functions.php';

foreach (['kp multisite generate', 'kp multisite manifest'] as $command) {
    if (!isset(WP_CLI::$commands[$command])) {
        throw new RuntimeException("WP-CLI command was not registered: {$command}");
    }
}

echo "kp-starter smoke tests passed.\n";
