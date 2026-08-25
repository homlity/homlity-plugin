<?php
/**
 * Dependency-free regression suite: php tests/error-reporting/run.php
 */

declare(strict_types=1);

$pluginRoot = dirname(__DIR__, 2);
$wordpressRoot = dirname($pluginRoot, 3);

define('ABSPATH', $wordpressRoot . '/');
define('WP_CONTENT_DIR', $wordpressRoot . '/wp-content');
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
define('HOMLITY_PLUGIN_VERSION', 'test');
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);

$GLOBALS['test_options'] = [];
$GLOBALS['test_http_response'] = ['status' => 202, 'body' => '{}', 'error' => false];
$GLOBALS['test_http_request'] = [];
$GLOBALS['test_uuid'] = 0;
$GLOBALS['test_environment'] = 'local';
$GLOBALS['test_is_admin'] = false;
$GLOBALS['test_reentrant_client'] = null;
$GLOBALS['test_reentrant_result'] = null;

final class WP_Error
{
    public function __construct(private string $code, private string $message) {}
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
}

function __(string $value): string { return $value; }
function apply_filters(string $name, $value, ...$args) { return $value; }
function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
function sanitize_key(string $value): string { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $value) ?? ''); }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function esc_url_raw(string $value): string { return filter_var($value, FILTER_VALIDATE_URL) ? $value : ''; }
function wp_check_invalid_utf8(string $value): string { return $value; }
function wp_parse_url(string $url, int $component) { return parse_url($url, $component); }
function wp_json_encode($value, int $flags = 0): string|false { return json_encode($value, $flags); }
function wp_generate_uuid4(): string {
    $GLOBALS['test_uuid']++;
    return sprintf('00000000-0000-4000-8000-%012d', $GLOBALS['test_uuid']);
}
function get_bloginfo(string $key): string { return $key === 'version' ? '6.8' : ''; }
function wp_get_environment_type(): string { return $GLOBALS['test_environment']; }
function is_multisite(): bool { return false; }
function is_admin(): bool { return $GLOBALS['test_is_admin']; }
function get_option(string $key, $default = false) { return $GLOBALS['test_options'][$key] ?? $default; }
function update_option(string $key, $value, $autoload = null): bool { $GLOBALS['test_options'][$key] = $value; return true; }
function delete_option(string $key): bool { unset($GLOBALS['test_options'][$key]); return true; }
function add_option(string $key, $value, string $deprecated = '', $autoload = null): bool {
    if (array_key_exists($key, $GLOBALS['test_options'])) { return false; }
    $GLOBALS['test_options'][$key] = $value;
    return true;
}
function wp_rand(int $min, int $max): int { return $min; }
function add_query_arg(array $query, string $url): string { return $url . '?' . http_build_query($query); }
function wp_remote_request(string $url, array $args) {
    $GLOBALS['test_http_request'] = compact('url', 'args');
    if ($GLOBALS['test_reentrant_client'] !== null && $GLOBALS['test_reentrant_result'] === null) {
        $GLOBALS['test_reentrant_result'] = $GLOBALS['test_reentrant_client']->get('https://homi.example.test/recursive');
    }
    if (!empty($GLOBALS['test_http_response']['error'])) {
        return new WP_Error('http_request_failed', 'timeout');
    }
    return ['response' => ['code' => $GLOBALS['test_http_response']['status']], 'body' => $GLOBALS['test_http_response']['body']];
}
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code(array $response): int { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body(array $response): string { return (string) ($response['body'] ?? ''); }
function get_plugin_data(string $file): array { return ['Version' => '9.9.9']; }
function absint($value): int { return abs((int) $value); }
function add_action(...$args): bool { return true; }
function add_filter(...$args): bool { return true; }
function current_user_can(string $capability): bool { return true; }
function get_transient(string $key) { return false; }
function set_transient(string $key, $value, int $expiration): bool { return true; }
function wp_next_scheduled(string $hook) { return false; }
function wp_schedule_event(int $timestamp, string $recurrence, string $hook): bool { return true; }
function register_rest_route(...$args): bool { return true; }
function do_action(...$args): void {}

require_once $pluginRoot . '/src/Core/Contracts/ServiceInterface.php';
require_once $pluginRoot . '/includes/error-reporting/class-homlity-error-reporter.php';
require_once $pluginRoot . '/src/ErrorReporting/OfficialPluginRegistry.php';
require_once $pluginRoot . '/src/ErrorReporting/ErrorSanitizer.php';
require_once $pluginRoot . '/src/ErrorReporting/ErrorEventFactory.php';
require_once $pluginRoot . '/src/ErrorReporting/ErrorEventQueue.php';
require_once $pluginRoot . '/src/Services/HomiApiClient.php';
require_once $pluginRoot . '/src/ErrorReporting/HomiErrorTransport.php';
require_once $pluginRoot . '/src/ErrorReporting/ErrorReporterService.php';

use Homlity\PluginInmobiliario\ErrorReporting\ErrorEventFactory;
use Homlity\PluginInmobiliario\ErrorReporting\ErrorEventQueue;
use Homlity\PluginInmobiliario\ErrorReporting\ErrorReporterService;
use Homlity\PluginInmobiliario\ErrorReporting\ErrorSanitizer;
use Homlity\PluginInmobiliario\ErrorReporting\HomiErrorTransport;
use Homlity\PluginInmobiliario\ErrorReporting\OfficialPluginRegistry;
use Homlity\PluginInmobiliario\Services\HomiApiClient;

$tests = 0;
$failures = [];

function check(bool $condition, string $message): void
{
    global $tests, $failures;
    ++$tests;
    if (!$condition) {
        $failures[] = $message;
    }
}

$capturedFatals = 0;
Homlity_Error_Reporter::set_fatal_callback(static function () use (&$capturedFatals): void { ++$capturedFatals; });
$duplicateFatal = ['type' => E_ERROR, 'message' => 'same fatal', 'file' => WP_PLUGIN_DIR . '/homlity-real-estate/Test.php', 'line' => 1];
Homlity_Error_Reporter::capture_fatal($duplicateFatal);
Homlity_Error_Reporter::capture_fatal($duplicateFatal);
check($capturedFatals === 1, 'same fatal captured once per request');
foreach (['cli', 'cron', 'rest', 'ajax', 'admin'] as $mode) {
    check(ErrorEventFactory::executionFromFlags([$mode => true]) === $mode, "execution mode $mode");
}
check(ErrorEventFactory::executionFromFlags([]) === 'web', 'execution mode web');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/inmueble/demo?token=secret';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';

$registry = new OfficialPluginRegistry();
check($registry->originForFile(WP_PLUGIN_DIR . '/plugin-wasi-sync/src/Worker.php') === 'homlity-wasi', 'alias WASI');
check($registry->originForFile(WP_PLUGIN_DIR . '/plugin-simi-sync/src/Worker.php') === 'homlity-simi', 'alias SIMI');
check($registry->originForFile(WP_PLUGIN_DIR . '/plugin-softinm-sync/src/Worker.php') === 'homlity-softinm', 'alias Softinm');
check($registry->originForFile(WP_PLUGIN_DIR . '/woocommerce/file.php') === null, 'third-party origin rejected');
check($registry->originForFile(WP_CONTENT_DIR . '/themes/divi/file.php') === null, 'theme origin rejected');
check($registry->originForFile(ABSPATH . 'wp-includes/load.php') === null, 'core origin rejected');

$sanitizer = new ErrorSanitizer();
$dirty = 'license_key=abc123 token:xyz user@example.com +57 300 123 4567 Bearer qwerty';
$clean = $sanitizer->text($dirty);
check(strpos($clean, 'abc123') === false && strpos($clean, 'xyz') === false, 'secrets redacted');
check(strpos($clean, 'user@example.com') === false && strpos($clean, '300 123 4567') === false, 'PII redacted');
check($sanitizer->requestPath('/demo?a=1&token=x') === '/demo', 'query string removed');
check(strpos($sanitizer->path(WP_PLUGIN_DIR . '/homlity-sync/src/Test.php'), 'wp-content/plugins/') === 0, 'absolute path normalized');
$context = $sanitizer->syncContext(['run_id' => 'r1', 'password' => 'secret', 'raw_payload' => ['email' => 'x@y.com']]);
check(($context['run_id'] ?? '') === 'r1' && !isset($context['raw_payload']) && !isset($context['password']), 'context allowlist');
$crumbs = array_fill(0, 60, ['message' => 'step', 'data' => ['token' => 'secret', 'run_id' => 'r1']]);
check(count($sanitizer->breadcrumbs($crumbs)) === 50, 'breadcrumbs bounded to 50');
check(strpos(json_encode($sanitizer->breadcrumbs($crumbs)), 'secret') === false, 'breadcrumbs sanitized');

$factory = new ErrorEventFactory($registry, $sanitizer);
$fatal = ['type' => E_ERROR, 'message' => 'Boom token=secret', 'file' => WP_PLUGIN_DIR . '/homlity-real-estate/src/Test.php', 'line' => 7];
$event = $factory->fromFatal($fatal);
check(is_array($event), 'official fatal accepted');
check(($event['environment'] ?? '') === 'development', 'environment normalized');
check(($event['request']['path'] ?? '') === '/inmueble/demo', 'safe request path');
check(strpos((string) ($event['exception']['message'] ?? ''), 'secret') === false, 'fatal sanitized before event');
check(
    ($event['application'] ?? '') === 'plugin'
    && ($event['platform'] ?? '') === 'wordpress'
    && ($event['tags']['origin_plugin'] ?? '') === 'homlity-real-estate'
    && ($event['tags']['error_kind'] ?? '') === 'fatal'
    && ($event['runtime']['plugin_collector'] ?? '') === 'homlity-real-estate',
    'payload contract compatible with Homi'
);
check(strpos((string) ($event['culprit'] ?? ''), 'wp-content/plugins/homlity-real-estate/') === 0, 'culprit normalized');
check(strlen((string) wp_json_encode($factory->fromFatal([
    'type' => E_ERROR,
    'message' => str_repeat('x', 400000),
    'file' => WP_PLUGIN_DIR . '/homlity-real-estate/src/Test.php',
    'line' => 8,
]))) < 256000, 'payload bounded below 256KB');
$GLOBALS['test_environment'] = 'production';
$GLOBALS['test_is_admin'] = true;
$adminEvent = $factory->fromFatal($fatal);
check(($adminEvent['environment'] ?? '') === 'production' && ($adminEvent['tags']['execution'] ?? '') === 'admin', 'production admin context');
$GLOBALS['test_environment'] = 'local';
$GLOBALS['test_is_admin'] = false;
$warning = $fatal; $warning['type'] = E_WARNING;
check($factory->fromFatal($warning) === null, 'warning rejected');
foreach ([E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED] as $ignoredType) {
    $ignoredError = $fatal;
    $ignoredError['type'] = $ignoredType;
    check($factory->fromFatal($ignoredError) === null, "non-fatal PHP type $ignoredType rejected");
}
$external = $fatal; $external['file'] = WP_PLUGIN_DIR . '/woocommerce/file.php';
check($factory->fromFatal($external) === null, 'external fatal rejected');
foreach ([
    'homlity-sync' => 'homlity-sync',
    'plugin-wasi-sync' => 'homlity-wasi',
    'plugin-simi-sync' => 'homlity-simi',
    'plugin-softinm-sync' => 'homlity-softinm',
] as $directory => $canonical) {
    $pluginFatal = $fatal;
    $pluginFatal['file'] = WP_PLUGIN_DIR . '/' . $directory . '/src/Test.php';
    $pluginEvent = $factory->fromFatal($pluginFatal);
    check(($pluginEvent['tags']['origin_plugin'] ?? '') === $canonical, "fatal canonical origin $canonical");
}
$_SERVER['REQUEST_URI'] = '/.env';
check($factory->fromFatal($fatal) === null, 'scanner path rejected');
$_SERVER['REQUEST_URI'] = '/inmueble/demo?token=secret';

check($factory->fromSync('homlity-wasi', new RuntimeException('network failed'), ['status' => 'failed', 'run_id' => 'r1']) !== null, 'real sync failure accepted');
check($factory->fromSync('homlity-wasi', new RuntimeException('none'), ['status' => 'empty']) === null, 'successful empty sync rejected');
check($factory->fromSync('homlity-wasi', new WP_Error('validation_error', 'bad input'), ['status' => 'failed']) === null, 'expected validation rejected');
check($factory->fromSync('homlity-wasi', new WP_Error('remote_server_error', 'server failed'), ['status' => 'failed']) !== null, 'final WP_Error accepted');
check($factory->fromSync('woocommerce', 'failure', ['status' => 'failed']) === null, 'unknown plugin rejected');

$GLOBALS['test_options'] = [];
$service = new ErrorReporterService();
$workerError = new RuntimeException('worker failure');
$service->captureWorkerFailure(['status' => 'retry_scheduled', 'attempt' => 1, 'max_attempts' => 3], $workerError);
check(count(get_option(ErrorEventQueue::OPTION, [])) === 0, 'intermediate worker retry rejected');
$service->captureWorkerFailure(['status' => 'dead', 'attempt' => 3, 'max_attempts' => 3], $workerError);
check(count(get_option(ErrorEventQueue::OPTION, [])) === 1, 'terminal worker failure accepted');

$GLOBALS['test_options'] = [];
$queue = new ErrorEventQueue();
check($queue->enqueue($event), 'queue accepts event');
check(count(get_option(ErrorEventQueue::OPTION, [])) === 1, 'queue persisted');
$same = $event; $same['event_id'] = wp_generate_uuid4();
check($queue->enqueue($same) && count(get_option(ErrorEventQueue::OPTION, [])) === 1, 'duplicate aggregated');
$stored = get_option(ErrorEventQueue::OPTION, []);
check(($stored[0]['aggregate_count'] ?? 0) === 2, 'aggregate counter');
$originalId = $stored[0]['event_id'];
$queue->retry($originalId, 500);
$stored = get_option(ErrorEventQueue::OPTION, []);
check(($stored[0]['event_id'] ?? '') === $originalId && ($stored[0]['attempts'] ?? 0) === 1, 'retry preserves id');
$queue->block($originalId, 403);
check(count($queue->due()) === 0 && ($queue->diagnostics()['blocked'] ?? 0) === 1, 'auth failure blocked');
$queue->unblockOrigins(['homlity-real-estate']);
check(count($queue->due()) === 1, 'revalidated origin unblocked');
$lock = $queue->acquireLock();
check(is_string($lock) && $queue->acquireLock() === null, 'delivery lock excludes concurrency');
$queue->releaseLock((string) $lock);
check(is_string($queue->acquireLock()), 'delivery lock releases safely');
delete_option('homlity_error_reporter_delivery_lock');

$GLOBALS['test_options'] = [];
for ($i = 0; $i < 105; ++$i) {
    $copy = $event;
    $copy['event_id'] = wp_generate_uuid4();
    $copy['exception']['message'] = 'unique-' . $i;
    $queue->enqueue($copy);
}
check(count(get_option(ErrorEventQueue::OPTION, [])) === 100, 'queue bounded to 100');
$expiredEntry = get_option(ErrorEventQueue::OPTION, [])[0];
$expiredEntry['created_at'] = time() - (8 * DAY_IN_SECONDS);
update_option(ErrorEventQueue::OPTION, [$expiredEntry]);
check(($queue->diagnostics()['queued'] ?? -1) === 0, 'events older than seven days expire');

$GLOBALS['test_options']['plugin_wasi_sync_license_key'] = 'license-never-in-body';
$GLOBALS['test_options']['plugin_wasi_sync_license_site_id'] = 'installation-uuid';
$GLOBALS['test_options']['plugin_wasi_sync_license_status'] = 'active';
$GLOBALS['test_options']['plugin_wasi_sync_homi_api_url'] = 'https://homi.example.test';
$transport = new HomiErrorTransport($registry);
$response = $transport->send('homlity-wasi', $event);
check($response['success'] && $response['status'] === 202, 'transport accepts 202');
check(($GLOBALS['test_http_request']['args']['headers']['X-Plugin-License'] ?? '') === 'license-never-in-body', 'license sent only as header');
check(strpos((string) $GLOBALS['test_http_request']['args']['body'], 'license-never-in-body') === false, 'license absent from JSON');
check(strpos(serialize(get_option(ErrorEventQueue::OPTION, [])), 'license-never-in-body') === false, 'license absent from queue option');
check(strpos((string) $GLOBALS['test_http_request']['url'], '/plugin-installations/installation-uuid/error-events') !== false, 'installation endpoint');
$GLOBALS['test_reentrant_client'] = new HomiApiClient();
$GLOBALS['test_reentrant_result'] = null;
$GLOBALS['test_reentrant_client']->get('https://homi.example.test/outer');
check(
    ($GLOBALS['test_reentrant_result']['message'] ?? '') === 'recursive_request'
    && !empty($GLOBALS['test_reentrant_result']['transport_error']),
    'HTTP recursion guard'
);
$GLOBALS['test_reentrant_client'] = null;

$GLOBALS['test_options']['plugin_wasi_sync_license_status'] = 'expired';
$requestBeforeExpiredLicense = $GLOBALS['test_http_request'];
$response = $transport->send('homlity-wasi', $event);
check(!$response['success'] && $response['status'] === 403 && $GLOBALS['test_http_request'] === $requestBeforeExpiredLicense, 'expired license rejected locally');
$GLOBALS['test_options']['plugin_wasi_sync_license_status'] = 'active';
unset($GLOBALS['test_options']['plugin_wasi_sync_license_key']);
$response = $transport->send('homlity-wasi', $event);
check(!$response['success'] && $response['status'] === 403, 'missing license rejected locally');
$GLOBALS['test_options']['plugin_wasi_sync_license_key'] = 'valid-license';
unset($GLOBALS['test_options']['plugin_wasi_sync_license_site_id']);
$response = $transport->send('homlity-wasi', $event);
check(!$response['success'] && $response['status'] === 403, 'missing installation UUID rejected locally');

function deliveryFixture(array $event, int $status, string $body = '{}', bool $transportError = false): array
{
    $GLOBALS['test_options'] = [
        'plugin_wasi_sync_license_key' => 'valid-license',
        'plugin_wasi_sync_license_site_id' => 'installation-uuid',
        'plugin_wasi_sync_license_status' => 'active',
        'plugin_wasi_sync_homi_api_url' => 'https://homi.example.test',
    ];
    $GLOBALS['test_http_response'] = ['status' => $status, 'body' => $body, 'error' => $transportError];
    $event['event_id'] = wp_generate_uuid4();
    $event['culprit'] = 'homlity-wasi';
    $event['release'] = 'homlity-wasi@1.0.0';
    $event['tags']['origin_plugin'] = 'homlity-wasi';
    $queue = new ErrorEventQueue();
    $queue->enqueue($event);
    $service = new ErrorReporterService();
    $service->deliverQueued(10, 2);
    return [get_option(ErrorEventQueue::OPTION, []), get_option(ErrorEventQueue::STATE_OPTION, [])];
}

foreach ([200, 202] as $status) {
    [$remaining, $state] = deliveryFixture($event, $status);
    check(count($remaining) === 0 && ($state['last_http_status'] ?? 0) === $status, "HTTP $status removes event");
}
[$remaining, $state] = deliveryFixture($event, 202, '<not-json>');
check(count($remaining) === 0, 'malformed success body does not duplicate accepted event');
foreach ([401, 403] as $status) {
    [$remaining, $state] = deliveryFixture($event, $status);
    check(count($remaining) === 1 && !empty($remaining[0]['blocked']) && !empty($state['license_revalidation_required']), "HTTP $status blocks retries");
}
[$remaining, $state] = deliveryFixture($event, 422);
check(count($remaining) === 0 && ($state['last_local_error'] ?? '') === 'invalid_event_discarded', 'HTTP 422 discarded with sanitized diagnostic');
foreach ([429, 500, 503] as $status) {
    [$remaining, $state] = deliveryFixture($event, $status);
    check(count($remaining) === 1 && ($remaining[0]['attempts'] ?? 0) === 1 && empty($remaining[0]['blocked']), "HTTP $status schedules retry");
}
[$remaining, $state] = deliveryFixture($event, 0, '{}', true);
check(count($remaining) === 1 && ($remaining[0]['attempts'] ?? 0) === 1, 'timeout schedules retry');

$GLOBALS['test_http_response'] = ['status' => 202, 'body' => '{}', 'error' => false];

if ($failures !== []) {
    fwrite(STDERR, "FAIL (" . count($failures) . "/$tests)\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK ($tests assertions)\n");
