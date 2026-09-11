<?php
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function __($value, $domain = null) { return $value; }
function is_wp_error($value) { return $value instanceof WP_Error; }

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct($code, $message, $data = null) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

require __DIR__ . '/../includes/class-bitmomo-ai-binance.php';

$checks = [];
function diagnostics_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }
function invoke_diagnostic($method, array $args) {
    $reflection = new ReflectionMethod('Bitmomo_AI_Binance', $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs(null, $args);
}

$primary_error = new WP_Error('binance_http', 'provider request failed', ['status' => 451]);
$fallback = [
    ['fundingRate' => 0.0001, 'fundingTime' => (time() - 300) * 1000],
    ['fundingRate' => 0.0002, 'fundingTime' => time() * 1000],
];
$fallback_row = invoke_diagnostic('input_diagnostic', ['funding', $fallback, true, $primary_error, true, null, 'fundingTime']);
diagnostics_check('fallback records requested input', $fallback_row['requested_input'] === 'funding');
diagnostics_check('fallback records selected provider', $fallback_row['provider'] === 'bybit_linear');
diagnostics_check('fallback attempt and success are explicit', $fallback_row['fallback_attempted'] && $fallback_row['fallback_success']);
diagnostics_check('fallback preserves the primary safe error category', $fallback_row['primary_error']['category'] === 'http');
diagnostics_check('fallback preserves the safe provider status', $fallback_row['primary_error']['provider_status'] === 451);
diagnostics_check('successful source retains observed_at and age', $fallback_row['observed_at'] !== '' && is_int($fallback_row['age_minutes']));

$failed_row = invoke_diagnostic('input_diagnostic', ['open_interest', $primary_error, false, $primary_error, true, $primary_error, 'timestamp']);
diagnostics_check('failed source is explicitly unavailable', !$failed_row['success'] && $failed_row['freshness'] === 'unavailable');
diagnostics_check('failed source retains normalized reason', $failed_row['error_category'] === 'http' && $failed_row['provider_status'] === 451);
diagnostics_check('failed fallback remains non-canonical', $failed_row['fallback_attempted'] && !$failed_row['fallback_success']);

$wrapped = invoke_diagnostic('error_with_diagnostics', [$primary_error, [$failed_row]]);
diagnostics_check('terminal source error carries concise diagnostics', $wrapped->get_error_data()['source_diagnostics'][0]['requested_input'] === 'open_interest');

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " source-diagnostic checks passed.\n";

