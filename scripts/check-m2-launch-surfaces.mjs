import fs from 'node:fs';
import path from 'node:path';

const repoRoot = process.cwd();
const checks = [];

function read(file) {
	return fs.readFileSync(path.join(repoRoot, file), 'utf8');
}

function check(label, condition) {
	checks.push({ label, pass: Boolean(condition) });
}

function withoutCommentLines(source) {
	return source
		.split('\n')
		.filter((line) => !/^\s*(?:\/\*|\*|\/\/)/.test(line))
		.join('\n');
}

const frontPage = read('website/wp-content/themes/bitmomo-child-v3/front-page.php');
const header = read('website/wp-content/themes/bitmomo-child-v3/header.php');
const homeHero = read('website/wp-content/themes/bitmomo-child-v3/template-parts/home-hero.php');
const btcCard = read('website/wp-content/themes/bitmomo-child-v3/template-parts/btc-intelligence-card.php');
const publicAdapter = read('website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-public-intelligence-adapter.php');
const btcIntelligencePlugin = read('website/wp-content/plugins/bitmomo-btc-intelligence/bitmomo-btc-intelligence.php');
const proSales = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-sales.php');
const proSalesOutput = withoutCommentLines(proSales);
const proHelp = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-help-center.php');
const proWhitelist = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-whitelist.php');

check('Homepage template includes BTC Intelligence before Pro teaser', frontPage.indexOf("template-parts/btc-intelligence', 'card'") > -1 && frontPage.indexOf("template-parts/btc-intelligence', 'card'") < frontPage.indexOf("template-parts/pro', 'teaser'"));
check('Homepage Pro CTAs point to /pro/', /home_url\(\s*'\/pro\/'\s*\)/.test(homeHero) && /home_url\(\s*'\/pro\/'\s*\)/.test(read('website/wp-content/themes/bitmomo-child-v3/template-parts/pro-teaser.php')));
check('Primary nav exposes /pro/ as the public Pro destination', /home_url\(\s*'\/pro\/'\s*\)/.test(header));

// Homepage intelligence must consume the narrow public adapter now that
// Opportunity V1, regime, direction and confidence share one public contract.
// The presentation layer must not bypass that contract to private/current
// engine internals or Pro state.
check(
	'Homepage BTC card consumes only the public intelligence adapter',
	/Bitmomo_Public_Intelligence_Adapter::snapshot\(\)/.test(btcCard)
		&& !/Bitmomo_AI_Intelligence::free_projection\(\)/.test(btcCard)
		&& !/Bitmomo_Regime_State_Store/.test(btcCard)
		&& !/Bitmomo_Pro_/.test(btcCard)
);
check(
	'Homepage BTC card fails closed when the public snapshot is unavailable',
	/is_array\(\s*\$bitmomo_snapshot\s*\)/.test(btcCard)
		&& /in_array\(\s*\$bitmomo_status,\s*array\(\s*'fresh',\s*'delayed'\s*\),\s*true\s*\)/.test(btcCard)
		&& /if\s*\(\s*!\s*\$bitmomo_available\s*\)/.test(btcCard)
		&& /bm-btc-unavailable/.test(btcCard)
);
check(
	'Homepage BTC card presents Opportunity as activity, never direction',
	/\['opportunity'\]/.test(btcCard)
		&& /Aktivitas · bukan arah/.test(btcCard)
		&& /Opportunity mengukur aktivitas, bukan arah harga/.test(btcCard)
		&& !/Risk-On|Risk-Off|RISK-ON|RISK-OFF/.test(btcCard)
);
check(
	'Public adapter exposes source, as-of, and display timezone as one fail-closed provenance contract',
	/'provenance'\s*=>\s*\[/.test(publicAdapter)
		&& /'source'\s*=>\s*\$public_source/.test(publicAdapter)
		&& /'as_of'\s*=>\s*\$as_of/.test(publicAdapter)
		&& /'timezone'\s*=>\s*self::PUBLIC_DISPLAY_TIMEZONE/.test(publicAdapter)
		&& /\$public_source\s*===\s*''/.test(publicAdapter)
);
check(
	'Homepage BTC card renders canonical source plus explicit as-of timezone',
	/\['provenance'\]/.test(btcCard)
		&& /SOURCE %s/.test(btcCard)
		&& /AS OF/.test(btcCard)
		&& /\$bitmomo_timezone_label/.test(btcCard)
);
check(
	'Public BTC Intelligence renders provenance from the adapter without hardcoded providers',
	/surface_context\(\)/.test(btcIntelligencePlugin)
		&& /\$surface\['provenance'\]/.test(btcIntelligencePlugin)
		&& /bm-bi__snapshot-provenance/.test(btcIntelligencePlugin)
		&& /SOURCE/.test(btcIntelligencePlugin)
		&& /AS OF/.test(btcIntelligencePlugin)
		&& !/Binance public market data|Bybit derivatives fallback/.test(btcCard + btcIntelligencePlugin)
);

check('/pro sales page is public and does not read entitlement state', /add_shortcode\(\s*'bitmomo_pro_sales'/.test(proSales) && !/bitmomo_user_has_pro_access|get_current_user_id|Bitmomo_Pro_Briefs::get_current_brief_for_display/.test(proSales));
check('/pro sales page renders one whitelist/purchase CTA path from canonical checkout URL', /bitmomo_pro_get_checkout_url\(\)/.test(proSales) && /Bitmomo_Pro_Whitelist::instance\(\)->render_widget/.test(proSales));
check('/pro sales copy keeps future capabilities clearly not-live', /SEGERA HADIR/.test(proSalesOutput) && /Belum tersedia hari ini/.test(proHelp) && !/24\/7|real-time|real time/.test(proSalesOutput));
check('/pro pricing terms match M2 founding package', /Rp149\.000/.test(proSales) && /Rp1\.490\.000/.test(proSales) && /const SEAT_CAP\s*=\s*149/.test(proSales) && /const BATCH_ONE\s*=\s*25/.test(proSales));
check('/pro avoids removed placeholder preview values', !/XX%|\$XX,XXX|\(placeholder\)|Contoh Tampilan Decision View/.test(proSales));
check('/pro avoids old public 7-day refund promise', !/7\s*(hari|day)|refund 7|7-day/i.test(proSales + proHelp));
check('/pro avoids fabricated accuracy percentage', !/\d+%\s*akurat/i.test(proSales + proHelp));
check('Whitelist says joining does not guarantee a seat', /Masuk whitelist tidak menjamin tempat/.test(proWhitelist));
check('Whitelist submit JS can survive LiteSpeed-localization issues via data attributes', /data-ajax-url/.test(proWhitelist) && /data-nonce/.test(proWhitelist) && /bitmomoProWhitelist/.test(read('website/wp-content/plugins/bitmomo-pro/assets/js/bitmomo-pro-whitelist.js')));

let pass = 0;
for (const result of checks) {
	if (result.pass) {
		pass++;
	}
	console.log(`[${result.pass ? 'PASS' : 'FAIL'}] ${result.label}`);
}

console.log(`${pass}/${checks.length} M2 launch-surface checks passed`);
process.exit(pass === checks.length ? 0 : 1);