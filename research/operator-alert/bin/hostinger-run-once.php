<?php

// Hostinger-native one-shot worker for Bitmomo Opportunity Radar.
// Designed for a 5-minute PHP cron. It never posts/replies on social platforms.

function boa_fail($message, $code = 2) {
    fwrite(STDERR, '[bitmomo-opportunity-radar] ' . $message . "\n");
    exit($code);
}

function boa_text($value) {
    return is_scalar($value) ? trim((string) $value) : '';
}

function boa_bool($value) {
    if (is_bool($value)) return $value;
    $value = strtolower(boa_text($value));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function boa_load_json($path, $fallback = []) {
    if (!is_string($path) || $path === '' || !is_file($path)) return $fallback;
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function boa_write_json_atomic($path, array $value) {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('state_dir_create_failed');
    }
    $tmp = $path . '.tmp.' . getmypid();
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('state_write_failed');
    }
    @chmod($path, 0600);
}

function boa_http_get(array $request) {
    $url = boa_text($request['url'] ?? '');
    $query = is_array($request['query'] ?? null) ? $request['query'] : [];
    $headers = is_array($request['headers'] ?? null) ? $request['headers'] : [];
    if ($query) $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('curl_init_failed');
    $header_lines = [];
    foreach ($headers as $key => $value) $header_lines[] = $key . ': ' . $value;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => $header_lines,
        CURLOPT_USERAGENT => 'BitmomoOpportunityRadar/1.0',
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('transport_error:' . $error);
    }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body];
}

function boa_telegram_notify($bot_token, $chat_id, $message) {
    $bot_token = boa_text($bot_token);
    $chat_id = boa_text($chat_id);
    if ($bot_token === '' || $chat_id === '') return false;
    $ch = curl_init('https://api.telegram.org/bot' . rawurlencode($bot_token) . '/sendMessage');
    if ($ch === false) return false;
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
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

function boa_manual_research($path) {
    $path = boa_text($path);
    if ($path === '') return [];
    $doc = boa_load_json($path, []);
    return array_values(array_filter($doc, 'is_array'));
}

function boa_budget_for_today(array $state, $now) {
    $today = gmdate('Y-m-d', $now);
    $budget = is_array($state['runtime_budget'] ?? null) ? $state['runtime_budget'] : [];
    if (($budget['utc_date'] ?? '') !== $today) {
        return ['utc_date' => $today, 'x_estimated_read_cost_usd' => 0.0, 'runs' => 0];
    }
    return [
        'utc_date' => $today,
        'x_estimated_read_cost_usd' => max(0.0, (float) ($budget['x_estimated_read_cost_usd'] ?? 0.0)),
        'runs' => max(0, (int) ($budget['runs'] ?? 0)),
    ];
}

$home = boa_text(getenv('HOME'));
$config_path = boa_text(getenv('BITMOMO_ALERT_CONFIG'));
if ($config_path === '' && isset($argv[1])) $config_path = boa_text($argv[1]);
if ($config_path === '') {
    if ($home === '') boa_fail('HOME is unavailable; pass the private config path as argv[1].');
    $config_path = rtrim($home, '/') . '/.bitmomo/opportunity-radar.php';
}
if (!is_file($config_path)) boa_fail('Private config not found: ' . $config_path);
if (strpos(str_replace('\\', '/', $config_path), '/public_html/') !== false) {
    boa_fail('Refusing config inside public_html. Move secrets to a private directory.');
}
$config = require $config_path;
if (!is_array($config)) boa_fail('Private config must return an array.');

$repo_root = rtrim(boa_text($config['repo_root'] ?? ''), '/');
$wp_root = rtrim(boa_text($config['wordpress_root'] ?? ''), '/');
$state_path = boa_text($config['state_path'] ?? '');
if ($repo_root === '' || !is_dir($repo_root)) boa_fail('repo_root is invalid.');
if ($wp_root === '' || !is_file($wp_root . '/wp-load.php')) boa_fail('wordpress_root is invalid.');
if ($state_path === '') boa_fail('state_path is required.');
if (strpos(str_replace('\\', '/', $state_path), '/public_html/') !== false) boa_fail('Refusing state_path inside public_html.');

$state_dir = dirname($state_path);
if (!is_dir($state_dir) && !mkdir($state_dir, 0700, true) && !is_dir($state_dir)) boa_fail('Unable to create private state directory.');
$lock_handle = fopen($state_path . '.lock', 'c');
if ($lock_handle === false) boa_fail('Unable to open runtime lock.');
if (!flock($lock_handle, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, json_encode(['ok' => true, 'skipped' => 'previous_run_still_active']) . "\n");
    exit(0);
}

// Load WordPress first so canonical Bitmomo AI runtime classes are available.
ob_start();
require_once $wp_root . '/wp-load.php';
ob_end_clean();

// Canonical Research Feed V1 and provider.
require_once $repo_root . '/research/distribution-core/includes/class-bitmomo-research-feed-v1.php';
require_once $repo_root . '/research/distribution-core/includes/class-bitmomo-research-feed-provider-v1.php';

// P1-P5 + P7 runtime modules.
require_once $repo_root . '/research/intent-radar/includes/class-bitmomo-intent-radar-v1.php';
require_once $repo_root . '/research/intent-radar/includes/class-bitmomo-intent-radar-queue-v1.php';
require_once $repo_root . '/research/content-compiler/includes/class-bitmomo-content-compiler-v1.php';
require_once $repo_root . '/research/engagement-copilot/includes/class-bitmomo-engagement-copilot-v1.php';
require_once $repo_root . '/research/engagement-copilot/includes/class-bitmomo-engagement-copilot-queue-v1.php';
require_once $repo_root . '/research/growth-attribution/includes/class-bitmomo-growth-attribution-v1.php';
require_once $repo_root . '/research/growth-attribution/includes/class-bitmomo-growth-attribution-report-v1.php';
require_once $repo_root . '/research/acquisition-orchestrator/includes/class-bitmomo-acquisition-orchestrator-v1.php';
require_once $repo_root . '/research/operator-alert/includes/class-bitmomo-indonesia-discovery-v1.php';
require_once $repo_root . '/research/operator-alert/includes/class-bitmomo-operator-alert-engine-v1.php';

$now = time();
$state = boa_load_json($state_path, []);
$budget = boa_budget_for_today($state, $now);
$daily_limit = max(0.0, (float) ($config['daily_x_read_budget_usd'] ?? 1.0));
$max_next_poll_cost = 0.05;
$budget_exhausted = $daily_limit > 0 && ($budget['x_estimated_read_cost_usd'] + $max_next_poll_cost) > $daily_limit;

$feed = Bitmomo_Research_Feed_Provider_V1::build(
    boa_manual_research($config['manual_research_path'] ?? ''),
    $now
);
$research_items = array_values(array_filter((array) ($feed['items'] ?? []), function ($item) {
    return is_array($item) && !empty($item['distribution']['eligible']);
}));
if (!$research_items) {
    boa_fail('No distribution-eligible canonical research is available; fail-closed, no discovery alert sent.', 3);
}

$x_token = $budget_exhausted ? '' : boa_text($config['x_bearer_token'] ?? '');
$credentials = [
    'x_bearer_token' => $x_token,
    'youtube_api_key' => boa_text($config['youtube_api_key'] ?? ''),
];

$result = Bitmomo_Operator_Alert_Engine_V1::run(
    $credentials,
    'boa_http_get',
    $research_items,
    $state,
    [],
    [],
    $now
);

$dry_run = boa_bool($config['dry_run'] ?? false);
$bot_token = boa_text($config['telegram_bot_token'] ?? '');
$chat_id = boa_text($config['telegram_chat_id'] ?? '');

$dispatch = Bitmomo_Operator_Alert_Engine_V1::dispatch(
    (array) ($result['alerts'] ?? []),
    function ($message) use ($dry_run, $bot_token, $chat_id) {
        if ($dry_run) {
            fwrite(STDOUT, $message . "\n---\n");
            return true;
        }
        if ($bot_token === '' || $chat_id === '') return false;
        return boa_telegram_notify($bot_token, $chat_id, $message);
    }
);

$run_cost = max(0.0, (float) ($result['discovery']['usage']['x_estimated_read_cost_usd'] ?? 0.0));
$budget['x_estimated_read_cost_usd'] = round($budget['x_estimated_read_cost_usd'] + $run_cost, 6);
$budget['runs']++;

$next_state = is_array($result['next_state'] ?? null) ? $result['next_state'] : [];
$next_state['runtime_budget'] = $budget;
$next_state['last_run_at'] = gmdate('c', $now);
$next_state['last_run_summary'] = [
    'discovered' => (int) ($result['counts']['discovered'] ?? 0),
    'alerts' => count((array) ($result['alerts'] ?? [])),
    'sent' => count((array) ($dispatch['sent'] ?? [])),
    'notify_failed' => count((array) ($dispatch['failed'] ?? [])),
    'x_read_cost_usd' => $run_cost,
    'x_daily_estimated_cost_usd' => $budget['x_estimated_read_cost_usd'],
    'x_daily_budget_usd' => $daily_limit,
    'x_budget_exhausted_before_run' => $budget_exhausted,
    'research_items' => count($research_items),
];

// In dry-run mode do not mark alerts as delivered. This keeps validation honest.
if ($dry_run) {
    $next_state['alerted_ids'] = array_values(array_map('strval', (array) ($state['alerted_ids'] ?? [])));
} else {
    $successful = array_fill_keys((array) ($dispatch['sent'] ?? []), true);
    $prior = array_values(array_map('strval', (array) ($state['alerted_ids'] ?? [])));
    $new_ids = [];
    foreach ((array) ($result['alerts'] ?? []) as $alert) {
        if (!is_array($alert)) continue;
        $id = boa_text($alert['alert_id'] ?? '');
        if ($id !== '' && isset($successful[$id])) $new_ids[] = $id;
    }
    $next_state['alerted_ids'] = array_slice(array_values(array_unique(array_merge($prior, $new_ids))), -500);
}

boa_write_json_atomic($state_path, $next_state);
flock($lock_handle, LOCK_UN);
fclose($lock_handle);

fwrite(STDOUT, json_encode([
    'ok' => true,
    'mode' => $dry_run ? 'dry_run' : 'live_operator_alert',
    'research_items' => count($research_items),
    'discovered' => (int) ($result['counts']['discovered'] ?? 0),
    'alerts' => count((array) ($result['alerts'] ?? [])),
    'sent' => count((array) ($dispatch['sent'] ?? [])),
    'notify_failed' => count((array) ($dispatch['failed'] ?? [])),
    'x_read_cost_usd' => $run_cost,
    'x_daily_estimated_cost_usd' => $budget['x_estimated_read_cost_usd'],
    'x_daily_budget_usd' => $daily_limit,
    'x_budget_exhausted_before_run' => $budget_exhausted,
], JSON_UNESCAPED_SLASHES) . "\n");
