import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const baseUrl = (process.env.BITMOMO_UI_BASE_URL || 'https://bitmomo.id').replace(/\/$/, '');
const outputDir = process.env.BITMOMO_UI_OUTPUT_DIR || 'ui-artifacts';
fs.mkdirSync(outputDir, { recursive: true });

const failures = [];
const report = { qualified: [] };
const browser = await chromium.launch({ headless: true });

function fail(message) {
  failures.push(message);
  console.error(`::error title=Article cleanliness contract::${message}`);
}

async function discoverQualifiedArticleUrls(page) {
  await page.goto(`${baseUrl}/category/riset/`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  const hrefs = await page.locator('.bm-research-lead__title a, .bm-research-library__copy h3 a').evaluateAll((nodes) =>
    nodes.map((node) => node.getAttribute('href')).filter(Boolean)
  );
  const seen = new Set();
  const urls = [];
  for (const href of hrefs) {
    const url = new URL(href, baseUrl).toString();
    if (seen.has(url)) continue;
    seen.add(url);
    urls.push(url);
    if (urls.length >= 10) break;
  }
  return urls;
}

async function articleMetrics(page) {
  return page.evaluate(() => {
    const body = document.querySelector('.bm-article-body');
    const deck = document.querySelector('.bm-article-deck');
    const bodyText = (body?.textContent || '').replace(/\s+/g, ' ').trim();
    const firstParagraph = (body?.querySelector('p')?.textContent || '').replace(/\s+/g, ' ').trim();
    const deckText = (deck?.textContent || '').replace(/\s+/g, ' ').trim();
    const inlineTypography = body
      ? [...body.querySelectorAll('[style]')].filter((node) => {
          const style = String(node.getAttribute('style') || '').toLowerCase();
          return /(^|;)\s*(font-family|font-size|line-height)\s*:/.test(style);
        }).map((node) => ({ tag: node.tagName, style: node.getAttribute('style') || '', text: (node.textContent || '').trim().slice(0, 80) }))
      : [];
    const bodyNewsletterNodes = body ? body.querySelectorAll('.mailpoet_form, form[action*="mailpoet"], #newsletter, #subscribe, .bm-footer-newsletter').length : 0;
    const bodyDisclaimers = body ? body.querySelectorAll('.bm-disclaimer').length : 0;
    const chatgptAttributionLinks = body
      ? [...body.querySelectorAll('a[href]')].filter((node) => {
          try {
            const url = new URL(node.href, location.href);
            return (url.searchParams.get('utm_source') || '').toLowerCase() === 'chatgpt.com';
          } catch {
            return false;
          }
        }).map((node) => node.href)
      : [];
    const legacyMarkers = [
      'Subscribe Newsletter Bitmomo',
      'Ringkasan AI & Crypto langsung ke inbox.',
      'Gabung Newsletter Bitmomo',
      'Daftar untuk menerima konten menarik di email Anda setiap bulan!',
    ].filter((marker) => bodyText.includes(marker));
    const robots = [...document.querySelectorAll('meta[name="robots"]')].map((meta) => meta.getAttribute('content') || '').join(',').toLowerCase();
    const description = document.querySelector('meta[name="description"]')?.getAttribute('content')?.trim() || '';
    const canonical = document.querySelector('link[rel="canonical"]')?.href || '';
    const eyebrow = document.querySelector('.bm-article .bm-public-eyebrow')?.textContent?.trim() || '';
    const bodyParagraphSizes = body ? [...body.querySelectorAll('p, li')].map((node) => parseFloat(getComputedStyle(node).fontSize)).filter(Number.isFinite) : [];
    const literalExcerptScaffold = /(^|\s)Excerpt:\s*/i.test(bodyText);
    const deckDuplicatesOpening = !!deckText && !!firstParagraph && (
      firstParagraph.startsWith(deckText) || deckText.startsWith(firstParagraph)
    );
    const deckLooksAutoTruncated = /(?:\[…\]|\[\.\.\.\])\s*$/.test(deckText);

    return {
      url: location.href,
      title: document.title,
      description,
      canonical,
      robots,
      eyebrow,
      bodyNewsletterNodes,
      bodyDisclaimers,
      chatgptAttributionLinks,
      literalExcerptScaffold,
      deckText,
      firstParagraph: firstParagraph.slice(0, 220),
      deckDuplicatesOpening,
      deckLooksAutoTruncated,
      legacyMarkers,
      inlineTypography,
      minBodyParagraphSize: bodyParagraphSizes.length ? Math.min(...bodyParagraphSizes) : null,
    };
  });
}

function validateArticle(metrics) {
  if (!metrics.title.includes('Bitmomo Research')) fail(`Qualified article title does not carry the Bitmomo Research search promise: ${metrics.title}`);
  if (!metrics.description || metrics.description.length < 60) fail(`Qualified article meta description is missing/too thin (${metrics.description.length} chars): ${metrics.url}`);
  if (/noindex/.test(metrics.robots)) fail(`Qualified article is unexpectedly noindex: ${metrics.url} — ${metrics.robots}`);
  if (!metrics.canonical) fail(`Qualified article has no canonical URL: ${metrics.url}`);
  if (!['MARKET RESEARCH', 'INTELLIGENCE SYSTEMS RESEARCH', 'AI & INTELLIGENCE SYSTEMS'].includes(metrics.eyebrow)) fail(`Qualified article has wrong eyebrow: ${metrics.eyebrow || 'missing'} — ${metrics.url}`);
  if (metrics.bodyNewsletterNodes) fail(`Qualified article body contains ${metrics.bodyNewsletterNodes} newsletter/form node(s): ${metrics.url}`);
  if (metrics.bodyDisclaimers) fail(`Qualified article body contains ${metrics.bodyDisclaimers} legacy template disclaimer(s): ${metrics.url}`);
  if (metrics.legacyMarkers.length) fail(`Qualified article body contains legacy chrome text: ${metrics.legacyMarkers.join(' | ')} — ${metrics.url}`);
  if (metrics.inlineTypography.length) fail(`Qualified article body contains inline typography overrides: ${JSON.stringify(metrics.inlineTypography.slice(0, 6))} — ${metrics.url}`);
  if (metrics.minBodyParagraphSize !== null && metrics.minBodyParagraphSize < 15) fail(`Qualified article has paragraph/list text below 15px: ${metrics.minBodyParagraphSize}px — ${metrics.url}`);
  if (metrics.chatgptAttributionLinks.length) fail(`Qualified article exposes ChatGPT attribution query strings: ${metrics.chatgptAttributionLinks.join(' | ')}`);
  if (metrics.literalExcerptScaffold) fail(`Qualified article exposes literal "Excerpt:" scaffolding: ${metrics.url}`);
  if (metrics.deckLooksAutoTruncated) fail(`Qualified article deck still exposes auto-truncation marker: ${metrics.url}`);
  if (metrics.deckDuplicatesOpening) fail(`Qualified article deck duplicates the opening paragraph: ${metrics.url}`);
}

async function inspectQualifiedArticles() {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
  const page = await context.newPage();
  const articleUrls = await discoverQualifiedArticleUrls(page);
  if (!articleUrls.length) {
    fail('No qualified Research article is discoverable from the canonical Research Hub.');
    await context.close();
    return;
  }

  for (let index = 0; index < articleUrls.length; index += 1) {
    const articleUrl = articleUrls[index];
    const response = await page.goto(articleUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    if (!response || response.status() !== 200) {
      fail(`Qualified article returned HTTP ${response ? response.status() : 0}: ${articleUrl}`);
      continue;
    }
    const metrics = await articleMetrics(page);
    report.qualified.push(metrics);
    validateArticle(metrics);
    if (index < 4) {
      await page.screenshot({ path: path.join(outputDir, `qualified-article-cleanliness-${index + 1}.png`), fullPage: true });
    }
  }

  await context.close();
}

async function inspectLegacyResearchCanary() {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
  const page = await context.newPage();
  const canaryUrl = `${baseUrl}/agi-superintelligence-dan-blockchain/`;
  const response = await page.goto(canaryUrl, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

  if (!response || response.status() === 404) {
    report.legacyCanary = { url: canaryUrl, status: response ? response.status() : 0, skipped: true };
    console.log('SKIP legacy Riset canary: known legacy URL is not present on this runtime.');
    await context.close();
    return;
  }

  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  const metrics = await page.evaluate(() => {
    const robots = [...document.querySelectorAll('meta[name="robots"]')].map((meta) => meta.getAttribute('content') || '').join(',').toLowerCase();
    return {
      status: 200,
      title: document.title,
      robots,
      eyebrow: document.querySelector('.bm-article .bm-public-eyebrow')?.textContent?.trim() || '',
      researchStandardCount: document.querySelectorAll('.bm-article-standard').length,
      genericPostNavCount: document.querySelectorAll('.bm-post-nav').length,
    };
  });
  report.legacyCanary = { url: canaryUrl, ...metrics };

  if (!/noindex/.test(metrics.robots)) fail(`Legacy generic-Riset canary is still indexable: ${metrics.robots || 'robots meta missing'}`);
  if (['RISET', 'MARKET RESEARCH', 'INTELLIGENCE SYSTEMS RESEARCH', 'AI & INTELLIGENCE SYSTEMS'].includes(metrics.eyebrow)) fail(`Legacy generic-Riset canary is still visually promoted as research: ${metrics.eyebrow}`);
  if (metrics.researchStandardCount !== 0) fail(`Legacy generic-Riset canary incorrectly renders Research Standard (${metrics.researchStandardCount}).`);
  if (metrics.genericPostNavCount !== 0) fail(`Legacy canary still renders generic previous/next navigation (${metrics.genericPostNavCount}).`);

  await page.screenshot({ path: path.join(outputDir, 'legacy-riset-canary.png'), fullPage: true });
  await context.close();
}

try {
  await inspectQualifiedArticles();
  await inspectLegacyResearchCanary();
} finally {
  await browser.close();
}

fs.writeFileSync(path.join(outputDir, 'article-cleanliness.json'), JSON.stringify({ baseUrl, report, failures }, null, 2));

if (failures.length) {
  console.error(`Article cleanliness contract failed with ${failures.length} issue(s).`);
  process.exit(1);
}

console.log(`PASS article search-promise and cleanliness contract across ${report.qualified.length} qualified article(s).`);
