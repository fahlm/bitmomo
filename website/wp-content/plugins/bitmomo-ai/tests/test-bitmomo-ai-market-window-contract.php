<?php
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

require __DIR__ . '/../includes/class-bitmomo-ai-binance.php';

$checks = array();
function market_window_check($label, $condition) {
    global $checks;
    $checks[] = array($label, (bool) $condition);
}

$values = range(1, 30);
$change = Bitmomo_AI_Binance::percent_change_over_periods($values, 24);
market_window_check('24H change uses exactly 25 hourly observations / 24 intervals', abs($change - 400.0) < 0.000001);
market_window_check('24H change refuses fewer than 25 observations', null === Bitmomo_AI_Binance::percent_change_over_periods(range(1, 24), 24));

$source = file_get_contents(__DIR__ . '/../includes/class-bitmomo-ai-binance.php');
market_window_check('open interest requires at least 25 hourly observations', false !== strpos($source, 'count($oi) >= 25'));
market_window_check('open-interest 24H field uses the exact-period helper', false !== strpos($source, 'self::percent_change_over_periods($oi_values, 24)'));
market_window_check('legacy first-vs-last OI calculation is absent', false === strpos($source, '$oi_first = $oi_ok ? reset($oi_values)'));

$failed = array_filter($checks, function($row) { return !$row[1]; });
foreach ($checks as $row) printf("[%s] %s\n", $row[1] ? 'PASS' : 'FAIL', $row[0]);
printf("\n%d/%d passed.\n", count($checks) - count($failed), count($checks));
exit($failed ? 1 : 0);
