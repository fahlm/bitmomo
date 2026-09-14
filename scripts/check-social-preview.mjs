import { chromium } from 'playwright';

const baseUrl = (process.env.BITMOMO_UI_BASE_URL || '').replace(/\/$/, '');
if (!/^https?:\/\//.test(baseUrl)) {
  console.error('BITMOMO_UI_BASE_URL must be an absolute http(s) URL.');
  process.exit(2);
}

const auditHost = new URL(baseUrl).hostname;
const productionAudit = /(^|\.)bitmomo\.id$/i.test(auditHost);
const forbiddenHostPattern = /(?:seagreen|staging\.bitmomo|\.hostingersite\.|\.hostingerapp\.)/i;
const browser = await chromium.launch({ headless: true });
const failures = [];

function fail(label, message) {
  failures.push(`${label}: ${message}`);
  console.error(`::error title=Social preview contract::${label}: ${message}`);
}

function absoluteHttp(value) {
  try {
    const url = new URL(value);
    return ['http:', 'https:'].includes(url.protocol);
  } catch {
    return false;
  }
}

async function readMeta(page) {
  return page.evaluate(() => {
    const content = (selector) => document.querySelector(selector)?.getAttribute('content')?.trim() || '';
    const href = (selector) => document.querySelector(selector)?.getAttribute('href')?.trim() || '';
    return {
      title: document.title.trim(),
      canonical: href('link[rel="canonical"]'),
      ogTitle: content('meta[property="og:title"]'),
      ogDescription: content('meta[property="og:description"]'),
      ogUrl: content('meta[property="og:url"]'),
      ogImage: content('meta[property="og:image"]'),
      twitterCard: content('meta[name="twitter:card"]'),
      twitterTitle: content('meta[name="twitter:title"]'),
      twitterDescription: content('meta[name="twitter:description"]'),
      twitterImage: content('meta[name="twitter:image"]'),
    };
  });
}

async function audit(label, url) {
  const context = await browser.newContext();
  const page = await context.newPage();
  try {
    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    if (!response || response.status() !== 200) {
      fail(label, `HTTP ${response ? response.status() : 0} for ${url}`);
      return;
    }

    const meta = await readMeta(page);
    const requiredText = [
      ['document title', meta.title],
      ['canonical', meta.canonical],
      ['og:title', meta.ogTitle],
      ['og:description', meta.ogDescription],
      ['og:url', meta.ogUrl],
      ['og:image', meta.ogImage],
      ['twitter:card', meta.twitterCard],
      ['twitter:title', meta.twitterTitle],
      ['twitter:description', meta.twitterDescription],
      ['twitter:image', meta.twitterImage],
    ];
    for (const [name, value] of requiredText) {
      if (!value) fail(label, `missing ${name}`);
    }

    for (const [name, value] of [
      ['canonical', meta.canonical],
      ['og:url', meta.ogUrl],
      ['og:image', meta.ogImage],
      ['twitter:image', meta.twitterImage],
    ]) {
      if (value && !absoluteHttp(value)) fail(label, `${name} is not an absolute http(s) URL: ${value}`);
      if (productionAudit && value && forbiddenHostPattern.test(value)) {
        fail(label, `${name} leaks staging/preview identity on production: ${value}`);
      }
    }

    if (meta.twitterCard && meta.twitterCard !== 'summary_large_image') {
      fail(label, `twitter:card must be summary_large_image; got ${meta.twitterCard}`);
    }
    if (meta.canonical && meta.ogUrl && meta.canonical.replace(/\/$/, '') !== meta.ogUrl.replace(/\/$/, '')) {
      fail(label, `canonical and og:url disagree: ${meta.canonical} vs ${meta.ogUrl}`);
    }
    if (meta.ogTitle && meta.twitterTitle && meta.ogTitle !== meta.twitterTitle) {
      fail(label, 'og:title and twitter:title disagree');
    }
    if (meta.ogDescription && meta.twitterDescription && meta.ogDescription !== meta.twitterDescription) {
      fail(label, 'og:description and twitter:description disagree');
    }

    console.log(`PASS ${label} social preview metadata`);
  } finally {
    await context.close();
  }
}

let articleUrl = '';
{
  const context = await browser.newContext();
  const page = await context.newPage();
  try {
    await page.goto(`${baseUrl}/category/riset/`, { waitUntil: 'domcontentloaded', timeout: 45000 });
    articleUrl = await page.evaluate(() => {
      const selectors = [
        '.bm-research-lead__paper a[href]',
        '.bm-research-library a[href]',
        '.bm-home-research__item a[href]',
      ];
      const currentOrigin = window.location.origin;
      for (const selector of selectors) {
        for (const link of document.querySelectorAll(selector)) {
          try {
            const url = new URL(link.href, window.location.href);
            if (url.origin !== currentOrigin) continue;
            if (/^\/(?:category|tag|pro|btc-intelligence)(?:\/|$)/.test(url.pathname)) continue;
            if (url.pathname === '/' || url.pathname === '/category/riset/') continue;
            return url.href;
          } catch {}
        }
      }
      return '';
    });
  } finally {
    await context.close();
  }
}

await audit('homepage', `${baseUrl}/`);
await audit('pro', `${baseUrl}/pro/`);
await audit('research', `${baseUrl}/category/riset/`);
if (!articleUrl) fail('research-article', 'could not discover a representative qualified article from Research Hub');
else await audit('research-article', articleUrl);

await browser.close();

if (failures.length) {
  console.error(`Social preview contract failed with ${failures.length} issue(s).`);
  process.exit(1);
}

console.log('PASS Open Graph/X preview contract for homepage, Pro, Research Hub, and representative research article.');
