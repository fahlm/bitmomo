<?php
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['audit_options'] = array();
function __($value, $domain = null) { return $value; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function absint($value) { return abs((int) $value); }
function update_option($name, $value, $autoload = null) { $GLOBALS['audit_options'][$name] = $value; return true; }

class WP_Error {
    private $code;
    private $message;
    public function __construct($code = '', $message = '') { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

require __DIR__ . '/../includes/class-bitmomo-ai-session-intelligence.php';
require __DIR__ . '/../includes/class-bitmomo-ai-scheduler.php';

$checks = array();
function audit_check($label, $condition) { global $checks; $checks[] = array($label, (bool) $condition); }

$result = Bitmomo_AI_Scheduler::run('made_up_session');
audit_check('scheduler rejects unsupported session before touching market data', $result instanceof WP_Error && $result->get_error_code() === 'bitmomo_invalid_session_type');
audit_check('unsupported scheduler session is recorded as blocked', ($GLOBALS['audit_options']['bitmomo_ai_last_run']['status'] ?? '') === 'blocked');
audit_check('canonical session enum remains explicit', Bitmomo_AI_Session_Intelligence::is_supported_session_type('us_pre_open') && Bitmomo_AI_Session_Intelligence::is_supported_session_type('us_post_close') && !Bitmomo_AI_Session_Intelligence::is_supported_session_type('made_up_session'));

$main = file_get_contents(__DIR__ . '/../bitmomo-ai.php');
$adapter = file_get_contents(__DIR__ . '/../includes/class-bitmomo-public-intelligence-adapter.php');
$page = file_get_contents(__DIR__ . '/../../bitmomo-btc-intelligence/includes/class-bitmomo-btc-intelligence-page.php');

audit_check('canonical intelligence validates raw session before normalization', false !== strpos($main, 'is_supported_session_type($raw_session_type)') && strpos($main, 'is_supported_session_type($raw_session_type)') < strpos($main, 'normalize_session_type($raw_session_type)'));
audit_check('canonical intelligence rejects invalid bias instead of coercing to neutral', false !== strpos($main, "if (!in_array(\$bias, ['bullish', 'neutral', 'bearish'], true)) return null;") && false === strpos($main, "\$bias = 'neutral';"));
audit_check('canonical intelligence requires numeric confidence and score', false !== strpos($main, "!is_numeric(\$evaluation['confidence'])") && false !== strpos($main, "!is_numeric(\$evaluation['score'])"));
audit_check('public adapter requires supported session and positive numeric price', false !== strpos($adapter, 'is_supported_session_type($raw_session_type)') && false !== strpos($adapter, '!is_numeric($price)') && false !== strpos($adapter, '(float) $price <= 0'));

$pulse_position = strpos($page, '$this->render_market_pulse( $activity, $pulse_line );');
$delayed_position = strpos($page, 'if ( $is_delayed )');
audit_check('Market Pulse renders before Major Brief freshness gating', false !== $pulse_position && false !== $delayed_position && $pulse_position < $delayed_position);
audit_check('insufficient sample accuracy is withheld at presentation boundary', false !== strpos($page, 'Akurasi ditahan sampai sampel minimum terpenuhi.'));
audit_check('methodology discloses independent clocks and exact New York anchors', false !== strpos($page, '08:10 dan 20:10 America/New_York') && false !== strpos($page, 'clock yang terpisah'));

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " institutional fail-closed checks passed.\n";
