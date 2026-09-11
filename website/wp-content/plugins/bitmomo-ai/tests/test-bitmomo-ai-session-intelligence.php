<?php
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['session_options'] = [];
function get_option($name, $default = false) { return $GLOBALS['session_options'][$name] ?? $default; }
function update_option($name, $value, $autoload = null) { $GLOBALS['session_options'][$name] = $value; return true; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }

class Bitmomo_AI_Intelligence {
    const FRESH_AGE_SECONDS = 21600;
    const DELAYED_AGE_SECONDS = 108000;
}
class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct($code, $message, $data = null) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

require __DIR__ . '/../includes/class-bitmomo-ai-session-intelligence.php';
require __DIR__ . '/../includes/class-bitmomo-ai-runtime-state.php';

$checks = [];
function session_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }

function session_data($timestamp, $bias = 'bullish', $confidence = 70, $structure = 'hh_hl') {
    return [
        'timestamp' => $timestamp,
        'close' => 65000,
        'quality' => ['status' => 'complete', 'completeness_pct' => 100],
        'volatility' => ['regime' => 'normal'],
        'structure' => ['state' => $structure],
        'carry' => ['funding_rate' => 0.0001, 'basis_pct' => 0.05],
        'crowding' => ['price_change_24h_pct' => 1.25, 'oi_change_24h_pct' => 2.5],
        'source_diagnostics' => [
            ['requested_input' => 'candles_1h', 'provider' => 'binance_usdm', 'observed_at' => $timestamp, 'freshness' => 'fresh'],
        ],
        'tradfi_observations' => [
            ['requested_input' => 'rates', 'source' => 'existing_feed', 'observed_at' => '2026-07-02T20:00:00+00:00', 'freshness' => 'closed_market'],
        ],
        '_fixture_bias' => $bias,
        '_fixture_confidence' => $confidence,
    ];
}

function session_evaluation(array $data) {
    return [
        'bias' => $data['_fixture_bias'],
        'direction_strength' => $data['_fixture_bias'],
        'confidence' => $data['_fixture_confidence'],
        'quality' => $data['quality'],
        'axes' => [],
    ];
}

function session_record($type, $generated_at, array $history = [], $bias = 'bullish', $confidence = 70, $structure = 'hh_hl') {
    $data = session_data($generated_at, $bias, $confidence, $structure);
    return Bitmomo_AI_Session_Intelligence::build_record($type, $data, session_evaluation($data), ['status' => 'passed'], $generated_at, $history);
}

$dst = Bitmomo_AI_Session_Intelligence::next_anchor(
    Bitmomo_AI_Session_Intelligence::PRE_OPEN,
    new DateTimeImmutable('2026-03-09T07:00:00', new DateTimeZone('America/New_York'))
);
session_check('New York DST conversion keeps 08:10 ET and resolves to 12:10 UTC', $dst->format('H:i P') === '08:10 -04:00' && $dst->setTimezone(new DateTimeZone('UTC'))->format('H:i') === '12:10');

$standard = Bitmomo_AI_Session_Intelligence::next_anchor(
    Bitmomo_AI_Session_Intelligence::PRE_OPEN,
    new DateTimeImmutable('2026-01-12T07:00:00', new DateTimeZone('America/New_York'))
);
session_check('New York standard-time conversion keeps 08:10 ET and resolves to 13:10 UTC', $standard->format('H:i P') === '08:10 -05:00' && $standard->setTimezone(new DateTimeZone('UTC'))->format('H:i') === '13:10');

$weekend_anchor = Bitmomo_AI_Session_Intelligence::next_anchor(
    Bitmomo_AI_Session_Intelligence::PRE_OPEN,
    new DateTimeImmutable('2026-09-12T07:00:00', new DateTimeZone('America/New_York'))
);
$weekend = session_record(Bitmomo_AI_Session_Intelligence::PRE_OPEN, $weekend_anchor->format(DateTimeInterface::ATOM));
session_check('weekend still generates a canonical BTC Intelligence edition', $weekend['session_type'] === 'us_pre_open' && $weekend['canonical_status'] === 'valid');
session_check('weekend us_market_status is contextual metadata', $weekend['us_market_status'] === 'weekend');
session_check('closed-day presentation does not claim US pre-open', $weekend['session_label'] === 'US MARKETS CLOSED - BTC UPDATE' && stripos($weekend['session_label'], 'PRE-OPEN') === false);

$holiday_anchor = Bitmomo_AI_Session_Intelligence::next_anchor(
    Bitmomo_AI_Session_Intelligence::PRE_OPEN,
    new DateTimeImmutable('2026-07-03T07:00:00', new DateTimeZone('America/New_York'))
);
$holiday = session_record(Bitmomo_AI_Session_Intelligence::PRE_OPEN, $holiday_anchor->format(DateTimeInterface::ATOM));
session_check('US holiday still generates canonical BTC Intelligence', $holiday['canonical_status'] === 'valid' && $holiday['us_market_status'] === 'holiday');
session_check('closed TradFi observation retains its original timestamp', $holiday['session_intelligence']['tradfi_context']['observations'][0]['observed_at'] === '2026-07-02T20:00:00+00:00');
session_check('crypto 24/7 context retains the current generation timestamp', $holiday['session_intelligence']['crypto_context']['market_status'] === 'trading_24_7' && $holiday['session_intelligence']['crypto_context']['observed_at'] === $holiday_anchor->format(DateTimeInterface::ATOM));
$early_close = Bitmomo_AI_Session_Intelligence::market_day(new DateTimeImmutable('2026-11-27T12:00:00-05:00'));
session_check('early-close day is retained as context without suppressing generation', $early_close['us_market_status'] === 'early_close' && $early_close['close_time'] === '13:00');

$pre = session_record(Bitmomo_AI_Session_Intelligence::PRE_OPEN, '2026-09-10T08:10:00-04:00');
session_check('pre-open canonical edition has explicit identity and schema', $pre['session_type'] === 'us_pre_open' && $pre['schema_version'] === '2.0' && $pre['edition_id'] !== '');

$post = session_record(Bitmomo_AI_Session_Intelligence::POST_CLOSE, '2026-09-10T20:10:00-04:00', [$pre['edition_id'] => $pre], 'bearish', 55, 'lh_ll');
session_check('post-close canonical edition has explicit identity', $post['session_type'] === 'us_post_close' && $post['session_anchor'] === '2026-09-10T20:10:00-04:00');
session_check('post-close selects the correct preceding pre-open edition', $post['comparison_source_record_id'] === $pre['edition_id']);

$next_pre = session_record(Bitmomo_AI_Session_Intelligence::PRE_OPEN, '2026-09-11T08:10:00-04:00', [$pre['edition_id'] => $pre, $post['edition_id'] => $post]);
session_check('pre-open selects the correct preceding post-close edition', $next_pre['comparison_source_record_id'] === $post['edition_id']);
$friday_post = session_record(Bitmomo_AI_Session_Intelligence::POST_CLOSE, '2026-09-11T20:10:00-04:00');
$saturday_pre = session_record(Bitmomo_AI_Session_Intelligence::PRE_OPEN, '2026-09-12T08:10:00-04:00', [$friday_post['edition_id'] => $friday_post]);
session_check('session comparison continues across a weekend boundary', $saturday_pre['comparison_source_record_id'] === $friday_post['edition_id']);

$future = session_record(Bitmomo_AI_Session_Intelligence::POST_CLOSE, '2026-09-11T20:10:00-04:00');
$no_future = session_record(Bitmomo_AI_Session_Intelligence::PRE_OPEN, '2026-09-11T08:10:00-04:00', [$future['edition_id'] => $future]);
session_check('comparison never leaks a future edition', $no_future['session_intelligence']['comparison']['status'] === 'insufficient_history');

$no_history = session_record(Bitmomo_AI_Session_Intelligence::POST_CLOSE, '2026-09-12T20:10:00-04:00');
session_check('missing opposite-session history is explicit', $no_history['session_intelligence']['comparison']['status'] === 'insufficient_history' && $no_history['session_intelligence']['what_changed'] === []);

$passed_gate = ['status' => 'passed', 'checked_at' => gmdate('c')];
$blocked_gate = ['status' => 'blocked', 'checked_at' => gmdate('c'), 'hard_blocked' => true];
Bitmomo_AI_Runtime_State::record_valid_snapshot($pre, $passed_gate);
Bitmomo_AI_Runtime_State::record_attempt('us_post_close', 'blocked', [], $blocked_gate, new WP_Error('quality_blocked', 'fixture'));
session_check('blocked attempt retains the prior valid session edition', Bitmomo_AI_Runtime_State::latest_valid_snapshot()['edition_id'] === $pre['edition_id']);

$delayed = $pre;
$delayed['generated_at'] = gmdate('c', time() - (7 * HOUR_IN_SECONDS));
session_check('session state is delayed after six hours', Bitmomo_AI_Runtime_State::freshness_state($delayed) === 'delayed');
$expired = $pre;
$expired['generated_at'] = gmdate('c', time() - (31 * HOUR_IN_SECONDS));
session_check('session state is unavailable after thirty hours', Bitmomo_AI_Runtime_State::freshness_state($expired) === 'unavailable');

$wib = (new DateTimeImmutable($pre['session_anchor']))->setTimezone(new DateTimeZone('Asia/Jakarta'));
session_check('canonical session identity is independent from WIB display conversion', $pre['session_type'] === 'us_pre_open' && $wib->format('H:i') === '19:10');

$free = Bitmomo_AI_Session_Intelligence::free_fields($post);
$pro = Bitmomo_AI_Session_Intelligence::pro_fields($post);
session_check('Free and Pro structured fields stay separated', !isset($free['scenario_contract'], $free['monitoring_conditions'], $free['what_matters_next']) && isset($pro['scenario_contract'], $pro['monitoring_conditions']));
session_check('known events remain empty without a trusted source', $free['known_events'] === []);
$source = file_get_contents(__DIR__ . '/../includes/class-bitmomo-ai-session-intelligence.php');
session_check('P0.2A has no Bond API call and exposes only a null extension point', strpos($source, 'wp_remote_') === false && $free['bond_context'] === null && $pro['bond_context'] === null);
session_check('legacy edition names remain backward compatible', Bitmomo_AI_Session_Intelligence::normalize_session_type('morning') === 'us_pre_open' && Bitmomo_AI_Session_Intelligence::normalize_session_type('us_session') === 'us_post_close');

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " session-intelligence checks passed.\n";
