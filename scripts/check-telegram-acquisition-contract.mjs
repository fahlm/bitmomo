import fs from 'node:fs';

const files = {
  btcBootstrap: 'website/wp-content/plugins/bitmomo-btc-intelligence/bitmomo-btc-intelligence.php',
  brief: 'website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-telegram-brief.php',
  transport: 'website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-telegram-transport.php',
  publisher: 'website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-telegram-publisher.php',
  proBootstrap: 'website/wp-content/plugins/bitmomo-pro/bitmomo-pro.php',
  acquisition: 'website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-founding149-acquisition.php',
  manifest: 'config/production-runtime.json',
};

const read = (path) => fs.readFileSync(path, 'utf8');
const src = Object.fromEntries(Object.entries(files).map(([key, path]) => [key, read(path)]));
const manifest = JSON.parse(src.manifest);
const checks = [];
const check = (label, pass) => checks.push({ label, pass: Boolean(pass) });

check('BTC bootstrap loads all Telegram runtime classes',
  src.btcBootstrap.includes("class-bitmomo-btc-telegram-brief.php") &&
  src.btcBootstrap.includes("class-bitmomo-btc-telegram-transport.php") &&
  src.btcBootstrap.includes("class-bitmomo-btc-telegram-publisher.php"));
check('Telegram formatter owns current public-safe message entrypoint',
  /public static function current\(/.test(src.brief) &&
  src.brief.includes('Bitmomo_Public_Intelligence_Adapter::snapshot()') &&
  !/expected_range|scenario_contract|monitoring_conditions|derivatives_context/.test(src.brief));
check('Transport identity and channel are canonical',
  src.transport.includes("const CHANNEL_USERNAME      = '@bitmomodaily';") &&
  src.transport.includes("const BOT_USERNAME          = 'Bitmomo_id_bot';"));
check('Transport is runtime-secret only and fail-closed',
  src.transport.includes('BITMOMO_TELEGRAM_BOT_TOKEN') &&
  !src.transport.includes("define( 'BITMOMO_TELEGRAM_BOT_TOKEN'") &&
  !src.transport.includes("get_option( 'bitmomo_telegram_bot_token'"));
check('Staging canary is bounded to canonical guard + Telegram host/methods',
  src.transport.includes('BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED') &&
  src.transport.includes('BITMOMO_STAGING_SIDE_EFFECTS_DISABLED') &&
  src.transport.includes("bitmomo_staging_outbound_write_disabled") &&
  src.transport.includes("'api.telegram.org'") &&
  src.transport.includes("'getMe'         => 'GET'") &&
  src.transport.includes("'getChatMember' => 'GET'") &&
  src.transport.includes("'sendMessage'   => 'POST'"));
check('Publisher reuses canonical daily generation and creates no scheduler',
  src.publisher.includes("const CANONICAL_GENERATION_HOOK = 'bitmomo_ai_daily_generation';") &&
  src.publisher.includes('BITMOMO_TELEGRAM_AUTOPOST_ENABLED') &&
  !/wp_schedule_event|wp_schedule_single_event|wp_next_scheduled/.test(src.publisher));
check('Pro bootstrap loads Founding 149 scoreboard only',
  src.proBootstrap.includes('class-bitmomo-pro-founding149-acquisition.php') &&
  src.proBootstrap.includes('Bitmomo_Pro_Founding149_Acquisition::instance()'));
check('Founding 149 scoreboard reuses canonical whitelist storage',
  src.acquisition.includes("const CAMPAIGN = 'founding149_tlw_v1';") &&
  src.acquisition.includes('Bitmomo_Pro_Whitelist::POST_TYPE') &&
  src.acquisition.includes('Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN') &&
  !/register_post_type|register_rest_route/.test(src.acquisition));

const components = Object.fromEntries((manifest.components || []).map((component) => [component.name, component]));
const btc = components['bitmomo-btc-intelligence'] || {};
const pro = components['bitmomo-pro'] || {};
check('Runtime manifest accounts for Telegram/Founding files',
  Number.isInteger(manifest.expected_file_count) && manifest.expected_file_count > 0 &&
  Number.isInteger(btc.expected_file_count) && btc.expected_file_count >= 3 &&
  Number.isInteger(pro.expected_file_count) && pro.expected_file_count >= 1 &&
  (btc.required || []).includes('includes/class-bitmomo-btc-telegram-brief.php') &&
  (btc.required || []).includes('includes/class-bitmomo-btc-telegram-transport.php') &&
  (btc.required || []).includes('includes/class-bitmomo-btc-telegram-publisher.php') &&
  (pro.required || []).includes('includes/class-bitmomo-pro-founding149-acquisition.php'));

let passed = 0;
for (const result of checks) {
  if (result.pass) {
    passed += 1;
    console.log(`PASS ${result.label}`);
  } else {
    console.error(`FAIL ${result.label}`);
  }
}
console.log(`${passed}/${checks.length} Telegram acquisition checks passed`);
if (passed !== checks.length) process.exit(1);
