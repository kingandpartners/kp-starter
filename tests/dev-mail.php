<?php

/**
 * Development mail behaviour, exercised against stubbed WordPress functions.
 *
 * dev-mail.php runs before WordPress is loaded far enough for a test harness
 * to exist, and what it guards -- a live mail credential arriving on a laptop
 * with a database copy -- is not something to leave to a reading of the code.
 * The stubs below are the whole of the WordPress surface it touches.
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

$GLOBALS['kp_test_options'] = [];
$GLOBALS['kp_test_filters'] = [];
$GLOBALS['kp_test_actions'] = [];
$GLOBALS['kp_test_writes'] = 0;

function add_filter(string $hook, $callback): void {
    $GLOBALS['kp_test_filters'][$hook][] = $callback;
}

function remove_filter(string $hook, $callback): void {
    $GLOBALS['kp_test_filters'][$hook] = array_values(array_filter(
        $GLOBALS['kp_test_filters'][$hook] ?? [],
        static fn($registered) => $registered !== $callback
    ));
}

function add_action(string $hook, $callback): void {
    $GLOBALS['kp_test_actions'][$hook][] = $callback;
}

function apply_filters(string $hook, $value) {
    foreach ($GLOBALS['kp_test_filters'][$hook] ?? [] as $callback) {
        $value = $callback($value);
    }

    return $value;
}

/** Mirrors get_option: the stored value, passed through `option_<name>`. */
function get_option(string $name) {
    return apply_filters("option_{$name}", $GLOBALS['kp_test_options'][$name] ?? false);
}

function update_option(string $name, $value): bool {
    $GLOBALS['kp_test_options'][$name] = $value;
    $GLOBALS['kp_test_writes']++;

    return true;
}

define('WP_ENV', 'development');

require_once dirname(__DIR__) . '/features/runtime/dev-mail.php';

/* -- The credentials a production database arrives carrying -------------- */

$production_options = [
    'transport_type'         => 'sendgrid_api',
    'sendgrid_api_key'       => 'SG.a-real-live-key',
    'fallback_smtp_password' => 'hunter2',
    'oauth_client_secret'    => 'shhh',
    'mailjet_secret_key'     => 'also-secret',
    'envelope_sender'        => 'noreply@example.com',
    'hostname'               => 'smtp.sendgrid.net',
    'port'                   => '587',
    'reply_to'               => 'concierge@example.com',
];

echo "development mail\n";

kp_test('the transport is forced to MailHog', function () use ($production_options) {
    $GLOBALS['kp_test_options']['postman_options'] = $production_options;
    $options = get_option('postman_options');

    kp_assert($options['transport_type'], 'smtp', 'transport_type');
    kp_assert($options['hostname'], 'mailhog', 'hostname');
    kp_assert($options['port'], '1025', 'port');
    kp_assert($options['auth_type'], 'none', 'auth_type');
});

kp_test('no credential is readable', function () use ($production_options) {
    $GLOBALS['kp_test_options']['postman_options'] = $production_options;
    $options = get_option('postman_options');

    foreach ([ 'sendgrid_api_key', 'fallback_smtp_password', 'oauth_client_secret', 'mailjet_secret_key' ] as $secret) {
        kp_assert($options[$secret], '', $secret);
    }
});

kp_test('settings that are not credentials survive', function () use ($production_options) {
    $GLOBALS['kp_test_options']['postman_options'] = $production_options;
    $options = get_option('postman_options');

    kp_assert($options['envelope_sender'], 'noreply@example.com', 'envelope_sender');
    kp_assert($options['reply_to'], 'concierge@example.com', 'reply_to');
});

kp_test('the credentials are deleted from the row, not just hidden', function () use ($production_options) {
    $GLOBALS['kp_test_options']['postman_options'] = $production_options;

    kp_starter_scrub_stored_mail_credentials();

    $stored = $GLOBALS['kp_test_options']['postman_options'];
    kp_assert($stored['sendgrid_api_key'], '', 'stored sendgrid_api_key');
    kp_assert($stored['fallback_smtp_password'], '', 'stored fallback_smtp_password');
    kp_assert($stored['envelope_sender'], 'noreply@example.com', 'stored envelope_sender');
});

// Persisting the forced transport would put MailHog into the database, where
// it would then travel with the next dump -- the filter exists so the row need
// not be trusted, and writing its output back would undo that.
kp_test('the forced transport is not written to the database', function () use ($production_options) {
    $GLOBALS['kp_test_options']['postman_options'] = $production_options;

    kp_starter_scrub_stored_mail_credentials();

    $stored = $GLOBALS['kp_test_options']['postman_options'];
    kp_assert($stored['transport_type'], 'sendgrid_api', 'stored transport_type');
    kp_assert($stored['hostname'], 'smtp.sendgrid.net', 'stored hostname');
});

kp_test('an already-scrubbed site writes nothing', function () use ($production_options) {
    $GLOBALS['kp_test_options']['postman_options'] = $production_options;
    kp_starter_scrub_stored_mail_credentials();

    $GLOBALS['kp_test_writes'] = 0;
    kp_starter_scrub_stored_mail_credentials();

    kp_assert($GLOBALS['kp_test_writes'], 0, 'writes on a second pass');
});

kp_test('OAuth tokens go too', function () {
    $GLOBALS['kp_test_options']['postman_auth_token'] = [
        'access_token'  => 'ya29.live',
        'refresh_token' => '1//refresh',
        'user_email'    => 'mail@example.com',
    ];

    kp_starter_scrub_stored_mail_credentials();

    $stored = $GLOBALS['kp_test_options']['postman_auth_token'];
    kp_assert($stored['access_token'], '', 'access_token');
    kp_assert($stored['refresh_token'], '', 'refresh_token');
    kp_assert($stored['user_email'], 'mail@example.com', 'user_email');
});

// The token option must not pick up the transport settings when the scrub
// restores the filters it removed.
kp_test('the token option keeps its own filter', function () {
    $GLOBALS['kp_test_options']['postman_auth_token'] = [ 'access_token' => 'ya29.live' ];

    kp_starter_scrub_stored_mail_credentials();

    kp_assert(isset(get_option('postman_auth_token')['hostname']), false, 'hostname leaked into the token option');
});

kp_test('a site that has never configured Post SMTP is left alone', function () {
    unset($GLOBALS['kp_test_options']['postman_options'], $GLOBALS['kp_test_options']['postman_auth_token']);
    $GLOBALS['kp_test_writes'] = 0;

    kp_starter_scrub_stored_mail_credentials();

    kp_assert($GLOBALS['kp_test_writes'], 0, 'writes with nothing stored');
});

if ($failures) {
    throw new RuntimeException(count($failures) . ' dev-mail assertion(s) failed');
}

echo "dev-mail: all assertions passed\n";
