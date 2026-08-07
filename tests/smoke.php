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
    'features/runtime/suppress-deprecated-notices.php',
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
    "features/runtime/suppress-deprecated-notices.php",
    "features/acf-relationship-multisite/acf-relationship-multisite.php",
] as $required_include) {
    if (!str_contains($bootstrap, $required_include)) {
        throw new RuntimeException("Bootstrap does not include {$required_include}");
    }
}

echo "kp-starter smoke tests passed.\n";
