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
const proSales = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-sales.php');
const proSalesOutput = withoutCommentLines(proSales);
const proHelp = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-help-center.php');
const proWhitelist = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-whitelist.php');

check('Homepage template includes BTC Intelligence before Pro teaser', frontPage.indexOf("template-parts/btc-intelligence', 'card'") > -1 && frontPage.indexOf("template-parts/btc-intelligence', 'card'") < frontPage.indexOf("template-parts/pro', 'teaser'"));
check('Homepage Pro CTAs point to /pro/', /home_url\(\s*'\/pro\/'\s*\)/.test(homeHero) && /home_url\(\s*'\/pro\/'\s*\)/.test(read('website/wp-content/themes/bitmomo-child-v3/template-parts/pro-teaser.php')));
check('Primary nav exposes /pro/ as the public Pro destination', /home_url\(\s*'\/pro\/'\s*\)/.test(header));
check('Homepage BTC card consumes only the public/free projection', /Bitmomo_AI_Intelligence::free_projection\(\)/.test(btcCard) && !/Bitmomo_Pro_/.test(btcCard));
check('Homepage BTC card hides unavailable data instead of defaulting to values', /status'\s*=>\s*'unavailable'/.test(btcCard) && /!\s*\$bitmomo_available/.test(btcCard));

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
