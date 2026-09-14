<?php

// One-shot runner intended for cron/scheduler execution every 5 minutes.
// Social posting remains manual; this runner only reads discovery APIs and
// sends operator notifications.

$root = dirname(__DIR__, 3);
require_once $root . '/research/intent-radar/includes/class-bitmomo-intent-radar-v1.php';
require_once $root . '/research/intent-radar/includes/class-bitmomo-intent-radar-queue-v1.php';
require_once $root . '/research/content-compiler/includes/class-bitmomo-content-compiler-v1.php';
require_once $root . '/research/engagement-copilot/includes/class-bitmomo-engagement-copilot-v1.php';
require_once $root . '/research/engagement-copilot/includes/class-bitmomo-engagement-copilot-queue-v1.php';
require_once $root . '/research/growth-attribution/includes/class-bitmomo-growth-attribution-v1.php';
require_once $root . '/research/growth-attribution/includes/class-bitmomo-growth-attribution-report-v1.php';
require_once $root . '/research/acquisition-orchestrator/includes/class-bitmomo-acquisition-orchestrator-v1.php';
require_once __DIR__ . '/../includes/class-bitmomo-indonesia-discovery-v1.php';
require_once __DIR__ . '/../includes/class-bitmomo-operator-alert-engine-v1.php';

function oa_env($name, $required = false) {
    $value = getenv($name);
    $value = is_string($value) ? trim($value) : '';
    if ($required && $value === '') {
        fwrite(STDERR, "Missing required environment variable: {$name}\n");
        exit(2);
    }
    return $value;
}

function oa_load_json($path, $fallback = []) {
    if (!is_string($path) || $path === '' || !is_file($path)) return $fallback;
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function oa_write_json_atomic($path, array $value) {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('state_dir');
    $tmp = $path . '.tmp.' . getmypid();
    $bytes = file_put_contents($tmp, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($bytes === false || !rename($tmp, $path)) throw new RuntimeException('state_write');
    @chmod($path, 0600);
}

function oa_http_get(array $request) {
    $url = (string) ($request['url'] ?? '');
    $query = is_array($request['query'] ?? null) ? $request['query'] : [];
    $headers = is_array($request['headers'] ?? null) ? $request['headers'] : [];
    if ($query) $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('curl_init');
    $header_lines = [];
    foreach ($headers as $key => $value) $header_lines[] = $key . ': ' . $value;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $header_lines,
        CURLOPT_USERAGENT => 'BitmomoOperatorAlert/1.0',
    ]);
    $body = curl_exec($ch);
    if ($body === false) { $error = curl_error($ch); curl_close($ch); throw new RuntimeException('transport:' . $error); }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body];
}

function oa_telegram_notify($bot_token, $chat_id, $message) {
    if ($bot_token === '' || $chat_id === '') return false;
    $ch = curl_init('https://api.telegram.org/bot' . rawurlencode($bot_token) . '/sendMessage');
    if ($ch === false) return false;
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => $chat_id,
            'text' => $message,
            'disable_web_page_preview' => false,
        ], '', '&', PHP_QUERY_RFC3986),
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $body !== false && $status >= 200 && $status < 300;
}

$research_path = oa_env('BITMOMO_RESEARCH_FEED_PATH', true);
$state_path = oa_env('BITMOMO_ALERT_STATE_PATH');
if ($state_path === '') $state_path = __DIR__ . '/../var/operator-alert-state.json';

$research_doc = oa_load_json($research_path, []);
$research_items = is_array($research_doc['items'] ?? null) ? $research_doc['items'] : $research_doc;
if (!is_array($research_items)) {
    fwrite(STDERR, "Research feed JSON is invalid.\n");
    exit(3);
}

$state = oa_load_json($state_path, []);
$credentials = [
    'x_bearer_token' => oa_env('BITMOMO_X_BEARER_TOKEN'),
    'youtube_api_key' => oa_env('BITMOMO_YOUTUBE_API_KEY'),
];

$result = Bitmomo_Operator_Alert_Engine_V1::run($credentials, 'oa_http_get', $research_items, $state, [], [], time());

$bot_token = oa_env('BITMOMO_TELEGRAM_BOT_TOKEN');
$chat_id = oa_env('BITMOMO_TELEGRAM_CHAT_ID');
$dispatch = Bitmomo_Operator_Alert_Engine_V1::dispatch(
    (array) ($result['alerts'] ?? []),
    function ($message) use ($bot_token, $chat_id) {
        if ($bot_token === '' || $chat_id === '') {
            // Safe fallback for local validation. Contains no API credentials.
            fwrite(STDOUT, $message . "\n---\n");
            return true;
        }
        return oa_telegram_notify($bot_token, $chat_id, $message);
    }
);

$next_state = is_array($result['next_state'] ?? null) ? $result['next_state'] : [];
// Persist only successfully notified alert IDs. Failed notifications should retry.
$successful = array_fill_keys((array) ($dispatch['sent'] ?? []), true);
$prior = array_values(array_map('strval', (array) ($state['alerted_ids'] ?? [])));
$new_ids = [];
foreach ((array) ($result['alerts'] ?? []) as $alert) {
    if (!is_array($alert)) continue;
    $id = (string) ($alert['alert_id'] ?? '');
    if ($id !== '' && isset($successful[$id])) $new_ids[] = $id;
}
$next_state['alerted_ids'] = array_slice(array_values(array_unique(array_merge($prior, $new_ids))), -500);
oa_write_json_atomic($state_path, $next_state);

fwrite(STDOUT, json_encode([
    'ok' => true,
    'discovered' => (int) ($result['counts']['discovered'] ?? 0),
    'alerts' => count((array) ($result['alerts'] ?? [])),
    'sent' => count((array) ($dispatch['sent'] ?? [])),
    'notify_failed' => count((array) ($dispatch['failed'] ?? [])),
    'x_read_cost_usd' => (float) ($result['discovery']['usage']['x_estimated_read_cost_usd'] ?? 0),
], JSON_UNESCAPED_SLASHES) . "\n");
