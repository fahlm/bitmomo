import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const baseUrl = (process.env.BITMOMO_UI_BASE_URL || 'https://bitmomo.id').replace(/\/$/, '');
const outputDir = process.env.BITMOMO_UI_OUTPUT_DIR || 'ui-artifacts';
const baseHost = new URL(baseUrl).hostname.toLowerCase();
const expectPublicIndexing = process.env.BITMOMO_EXPECT_PUBLIC_INDEXING
  ? process.env.BITMOMO_EXPECT_PUBLIC_INDEXING === '1'
  : ['bitmomo.id', 'www.bitmomo.id'].includes(baseHost);
fs.mkdirSync(outputDir, { recursive: true });

const failures = [];
const report = {};
const browser = await chromium.launch({ headless: true });

function fail(message) {
  failures.push(message);
  console.error(`::error title=Article cleanliness contract::${message}`);
}

async function inspectQualifiedArticle() {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
  const page = await context.newPage();

  await page.goto(`${baseUrl}/category/riset/`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  const articleHref = await page.locator('.bm-research-lead__title a, .bm-research-library__copy h3 a').first().getAttribute('href').catch(() => null);
  if (!articleHref) {
    fail('No qualified Research article is discoverable from the canonical Research Hub.');
    await context.close();
    return;
  }

  const articleUrl = new URL(articleHref, baseUrl).toString();
  const response = await page.goto(articleUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  if (!response || response.status() !== 200) fail(`Qualified article returned HTTP ${response ? response.status() : 0}: ${articleUrl}`);

  const metrics = await page.evaluate(() => {
    const body = document.querySelector('.bm-article-body');
    const bodyText = (body?.textContent || '').replace(/\s+/g, ' ').trim();
    const inlineTypography = body
      ? [...body.querySelectorAll('[style]')].filter((node) => {
          const style = String(node.getAttribute('style') || '').toLowerCase();
          return /(^|;)\s*(font-family|font-size|line-height)\s*:/.test(style);
        }).map((node) => ({ tag: node.tagName, style: node.getAttribute('style') || '', text: (node.textContent || '').trim().slice(0, 80) }))
      : [];
    const bodyNewsletterNodes = body ? body.querySelectorAll('.mailpoet_form, form[action*="mailpoet"], #newsletter, #subscribe, .bm-footer-newsletter').length : 0;
    const bodyDisclaimers = body ? body.querySelectorAll('.bm-disclaimer').length : 0;
    const legacyMarkers = [
      'Subscribe Newsletter Bitmomo',
      'Ringkasan AI & Crypto langsung ke inbox.',
      'Gabung Newsletter Bitmomo',
      'Daftar untuk menerima konten menarik di email Anda setiap bulan!',
    ].filter((marker) => bodyText.includes(marker));
    const researchResidue = {
      chatgptTaggedLinks: body ? [...body.querySelectorAll('a[href]')].filter((node) => /[?&]utm_source=chatgpt\.com(?:&|$)/i.test(node.getAttribute('href') || '')).map((node) => node.getAttribute('href')) : [],
      literalExcerpt: /(^|\s)Excerpt:\s/i.test(bodyText),
    };
    const robots = [...document.querySelectorAll('meta[name="robots"]')].map((meta) => meta.getAttribute('content') || '').join(',').toLowerCase();
    const description = document.querySelector('meta[name="description"]')?.getAttribute('content')?.trim() || '';
    const canonical = document.querySelector('link[rel="canonical"]')?.href || '';
    const eyebrow = document.querySelector('.bm-article .bm-public-eyebrow')?.textContent?.trim() || '';
    const bodyParagraphSizes = body ? [...body.querySelectorAll('p, li')].map((node) => parseFloat(getComputedStyle(node).fontSize)).filter(Number.isFinite) : [];

    return {
      url: location.href,
      title: document.title,
      description,
      canonical,
      robots,
      eyebrow,
      bodyNewsletterNodes,
      bodyDisclaimers,
      legacyMarkers,
      researchResidue,
      inlineTypography,
      minBodyParagraphSize: bodyParagraphSizes.length ? Math.min(...bodyParagraphSizes) : null,
    };
  });

  report.qualified = metrics;

  if (!metrics.title.includes('Bitmomo Research')) fail(`Qualified article title does not carry the Bitmomo Research search promise: ${metrics.title}`);
  if (!metrics.description || metrics.description.length < 60) fail(`Qualified article meta description is missing/too thin (${metrics.description.length} chars).`);
  if (expectPublicIndexing && /noindex/.test(metrics.robots)) fail(`Production-qualified article is unexpectedly noindex: ${metrics.robots}`);
  if (!expectPublicIndexing && !/noindex/.test(metrics.robots)) fail(`Non-production qualified article is indexable: ${metrics.robots || 'robots meta missing'}`);
  if (!metrics.canonical) fail('Qualified article has no canonical URL.');
  if (!['MARKET RESEARCH', 'AI & INTELLIGENCE SYSTEMS', 'INTELLIGENCE SYSTEMS RESEARCH'].includes(metrics.eyebrow)) fail(`Qualified article has wrong eyebrow: ${metrics.eyebrow || 'missing'}`);
  if (metrics.bodyNewsletterNodes) fail(`Qualified article body contains ${metrics.bodyNewsletterNodes} newsletter/form node(s).`);
  if (metrics.bodyDisclaimers) fail(`Qualified article body contains ${metrics.bodyDisclaimers} legacy template disclaimer(s).`);
  if (metrics.legacyMarkers.length) fail(`Qualified article body contains legacy chrome text: ${metrics.legacyMarkers.join(' | ')}`);
  if (metrics.researchResidue.chatgptTaggedLinks.length) fail(`Qualified article leaks chatgpt.com campaign parameters: ${metrics.researchResidue.chatgptTaggedLinks.slice(0, 4).join(' | ')}`);
  if (metrics.researchResidue.literalExcerpt) fail('Qualified article body contains literal "Excerpt:" import/editor residue.');
  if (metrics.inlineTypography.length) fail(`Qualified article body contains inline typography overrides: ${JSON.stringify(metrics.inlineTypography.slice(0, 6))}`);
  if (metrics.minBodyParagraphSize !== null && metrics.minBodyParagraphSize < 15) fail(`Qualified article has paragraph/list text below 15px: ${metrics.minBodyParagraphSize}px`);

  await page.screenshot({ path: path.join(outputDir, 'qualified-article-cleanliness.png'), fullPage: true });
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
  await inspectQualifiedArticle();
  await inspectLegacyResearchCanary();
} finally {
  await browser.close();
}

fs.writeFileSync(path.join(outputDir, 'article-cleanliness.json'), JSON.stringify({ baseUrl, expectPublicIndexing, report, failures }, null, 2));

if (failures.length) {
  console.error(`Article cleanliness contract failed with ${failures.length} issue(s).`);
  process.exit(1);
}

console.log('PASS article search-promise and cleanliness contract.');
