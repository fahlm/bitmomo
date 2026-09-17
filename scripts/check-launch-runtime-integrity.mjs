const baseUrl = (process.env.BITMOMO_UI_BASE_URL || 'https://bitmomo.id').replace(/\/$/, '');
const staging = /hostingersite\.com$/i.test(new URL(baseUrl).hostname);
const failures = [];

function fail(message) {
  failures.push(message);
  console.error(`::error title=Launch runtime integrity::${message}`);
}

async function request(path, init = {}) {
  return fetch(`${baseUrl}${path}`, { redirect: 'manual', ...init });
}

async function inspectWhitelistCache(path) {
  const first = await request(path);
  const second = await request(path);
  if (first.status !== 200 || second.status !== 200) {
    fail(`${path} did not return 200 twice (${first.status}/${second.status}).`);
    return;
  }

  const firstHtml = await first.text();
  const secondHtml = await second.text();
  if (!firstHtml.includes('id="bm-wl-form"') || !secondHtml.includes('id="bm-wl-form"')) {
    fail(`${path} does not expose the canonical whitelist form.`);
  }

  const values = [first, second].map((response) => ({
    cache: String(response.headers.get('x-litespeed-cache') || '').toLowerCase(),
    cacheControl: String(response.headers.get('cache-control') || '').toLowerCase(),
    lsControl: String(response.headers.get('x-litespeed-cache-control') || '').toLowerCase(),
  }));
  values.forEach((headers, index) => {
    if (headers.cache === 'hit') fail(`${path} request ${index + 1} was served from LiteSpeed page cache.`);
    const explicitlyNoCache = /no-cache|no-store|private/.test(headers.cacheControl) || /no-cache|no-store/.test(headers.lsControl);
    if (!explicitlyNoCache && headers.cache && headers.cache !== 'miss') {
      fail(`${path} request ${index + 1} has no explicit no-cache signal: ${JSON.stringify(headers)}`);
    }
  });
}

async function inspectAnonymousUsers() {
  const response = await request('/wp-json/wp/v2/users?per_page=1');
  if (response.status === 200) {
    const body = await response.text();
    fail(`Anonymous WordPress user enumeration is still public (HTTP 200): ${body.slice(0, 180)}`);
  }
  if (![401, 403, 404].includes(response.status)) {
    fail(`Anonymous user REST boundary returned unexpected HTTP ${response.status}.`);
  }
}

async function inspectBrandTitle() {
  for (const path of ['/help/', '/kebijakan-privasi/', '/disclaimer/']) {
    const response = await request(path);
    if (response.status !== 200) {
      fail(`${path} returned HTTP ${response.status} while checking site identity.`);
      continue;
    }
    const html = await response.text();
    const title = html.match(/<title[^>]*>([\s\S]*?)<\/title>/i)?.[1]?.replace(/\s+/g, ' ').trim() || '';
    if (!title) fail(`${path} has no document title.`);
    if (/hostingersite\.com|seagreen-snail/i.test(title)) fail(`${path} leaks staging hostname in document title: ${title}`);
  }
}

async function inspectStagingIsolation() {
  if (!staging) return;

  const response = await request('/');
  const xRobots = String(response.headers.get('x-robots-tag') || '').toLowerCase();
  if (!xRobots.includes('noindex')) fail(`Staging homepage is missing X-Robots-Tag noindex (${xRobots || 'missing'}).`);

  const robots = await request('/robots.txt');
  const body = await robots.text();
  if (!/user-agent:\s*\*/i.test(body) || !/disallow:\s*\//i.test(body)) {
    fail(`Staging robots.txt is not a deterministic deny-all policy: ${body.slice(0, 240)}`);
  }

  for (const path of ['/category/riset/', '/pro/', '/btc-intelligence/']) {
    const page = await request(path);
    const header = String(page.headers.get('x-robots-tag') || '').toLowerCase();
    if (!header.includes('noindex')) fail(`Staging ${path} is missing X-Robots-Tag noindex.`);
  }
}

await inspectWhitelistCache('/');
await inspectWhitelistCache('/pro/');
await inspectAnonymousUsers();
await inspectBrandTitle();
await inspectStagingIsolation();

if (failures.length) {
  console.error(`Launch runtime integrity failed with ${failures.length} issue(s).`);
  process.exit(1);
}

console.log(`PASS launch runtime integrity (${staging ? 'staging' : 'production/runtime'} profile).`);
