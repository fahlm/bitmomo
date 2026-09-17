import { chromium } from 'playwright';

const baseUrl = (process.env.BITMOMO_UI_BASE_URL || '').replace(/\/$/, '');
if (!baseUrl) throw new Error('BITMOMO_UI_BASE_URL is required');

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const failures = [];
const check = (label, condition, detail = '') => {
  if (!condition) failures.push(detail ? `${label}: ${detail}` : label);
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}${detail ? ` — ${detail}` : ''}`);
};

try {
  const response = await page.goto(`${baseUrl}/btc-intelligence/`, { waitUntil: 'networkidle', timeout: 60000 });
  check('BTC Intelligence returns HTTP success', !!response && response.ok(), response ? `HTTP ${response.status()}` : 'no response');

  const metaRaw = await page.locator('meta[name="bitmomo-snapshot-contract"]').getAttribute('content').catch(() => null);
  let contract = null;
  try { contract = metaRaw ? JSON.parse(metaRaw) : null; } catch {}

  check('Public snapshot contract is present', !!contract);
  check('Major Brief is available', contract?.available === true, JSON.stringify(contract));
  check('Major Brief is current for its session clock', contract?.status === 'fresh', `status=${contract?.status ?? 'missing'}`);

  const bodyText = await page.locator('body').innerText();
  const pulseVisible = bodyText.includes('MARKET PULSE · INTRADAY') && bodyText.includes('Market Pulse · evaluasi 15 menit dari candle 5 menit');
  check('Market Pulse is visibly available to visitors', pulseVisible);

  const delayedBrief = /MAJOR BRIEF TERTUNDA|PEMBACAAN SAAT INI DITAHAN|Major Brief tertunda/i.test(bodyText);
  check('Visitor is not shown an overdue Major Brief as current value', !delayedBrief);

  const unavailablePulse = /Market Pulse belum memiliki record fresh|Market Pulse 15 menit tidak terjadwal/i.test(bodyText);
  check('Visitor-facing Market Pulse is not in a missing-scheduler state', !unavailablePulse);
} finally {
  await browser.close();
}

if (failures.length) {
  console.error(`Intelligence freshness gate failed with ${failures.length} issue(s):`);
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('PASS intelligence freshness gate.');
