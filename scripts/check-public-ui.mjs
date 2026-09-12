import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';

const baseUrl = (process.env.BITMOMO_UI_BASE_URL || 'https://bitmomo.id').replace(/\/$/, '');
const outputDir = process.env.BITMOMO_UI_OUTPUT_DIR || 'ui-artifacts';
fs.mkdirSync(outputDir, { recursive: true });

const surfaces = [
  { name: 'home', path: '/', marker: 'BTC Intelligence' },
  { name: 'btc-intelligence', path: '/btc-intelligence/', marker: 'Pahami BTC dalam konteks.', active: 'BTC Intelligence' },
  { name: 'pro', path: '/pro/', marker: 'FOUNDING MEMBERSHIP', active: 'BITMOMO PRO' },
  { name: 'help', path: '/help/', marker: 'Help Center' },
  { name: 'research', path: '/category/riset/', marker: 'Riset untuk memahami pasar', active: 'Research' },
  { name: 'about', path: '/tentang-kami/', marker: 'Tentang Kami', active: 'Tentang' },
  { name: 'privacy', path: '/kebijakan-privasi/', marker: 'Kebijakan Privasi' },
  { name: 'disclaimer', path: '/disclaimer/', marker: 'Disclaimer' },
  { name: 'search', path: '/?s=bitcoin', marker: 'Hasil untuk “bitcoin”' },
  { name: 'not-found', path: '/__bitmomo-ui-contract-not-found__/', marker: 'Halaman tidak ditemukan.', expectedStatus: 404 },
];
const viewports = [
  { width: 360, height: 800 },
  { width: 390, height: 844 },
  { width: 768, height: 1024 },
  { width: 1024, height: 900 },
  { width: 1440, height: 1000 },
];
const expectedNavLabels = ['BTC Intelligence', 'Research', 'Tentang', 'Masuk', 'BITMOMO PRO'];
const expectedSocial = {
  telegram: 'https://t.me/bitmomodaily',
  youtube: 'https://www.youtube.com/@bitmomoid',
  x: 'https://x.com/bitmomoid',
};
const requiredSurfaceSelectors = {
  home: ['.bm-direction-card', '.bm-authority__pillars', '.bm-research-list', '.bm-ai-lab-themes'],
  research: ['.bm-research-hub__hero', '.bm-research-disciplines', '.bm-research-principles__grid', '.bm-research-streams', '.bm-research-archive'],
  article: ['.bm-article-head', '.bm-article-body', '.bm-article-standard'],
  about: ['.bm-about-authority'],
  search: ['.bm-archive-v2'],
  'not-found': ['.bm-public-page--error'],
};

const failures = [];
const report = [];
const browser = await chromium.launch({ headless: true });
function addFailure(surface, viewport, message) {
  const label = `${surface.name}@${viewport.width}x${viewport.height}`;
  failures.push(`${label}: ${message}`);
  console.error(`::error title=UI browser contract::${label}: ${message}`);
}

try {
  /* Discover a real published research article instead of hard-coding a slug.
     This keeps the browser contract aligned with WordPress as source of truth. */
  const discoveryContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const discoveryPage = await discoveryContext.newPage();
  try {
    const discoveryResponse = await discoveryPage.goto(`${baseUrl}/category/riset/`, { waitUntil: 'domcontentloaded', timeout: 45000 });
    if (!discoveryResponse || discoveryResponse.status() !== 200) {
      failures.push(`article-discovery: Research Hub HTTP ${discoveryResponse ? discoveryResponse.status() : 0}`);
    } else {
      const articleLink = discoveryPage.locator('.bm-research-featured__body h3 a, .bm-research-card h3 a').first();
      if (await articleLink.count()) {
        const href = await articleLink.getAttribute('href');
        const title = (await articleLink.innerText()).trim();
        if (href && title) {
          const resolved = new URL(href, baseUrl);
          if (resolved.origin !== new URL(baseUrl).origin) {
            failures.push(`article-discovery: representative research link leaves audited origin (${resolved.origin})`);
          } else {
            surfaces.push({ name: 'article', url: resolved.href, marker: title, active: 'Research' });
          }
        } else {
          failures.push('article-discovery: representative research article has no usable href/title');
        }
      } else {
        failures.push('article-discovery: Research Hub exposes no published article to audit');
      }
    }
  } catch (error) {
    failures.push(`article-discovery: ${error.message}`);
  } finally {
    await discoveryContext.close();
  }

  for (const surface of surfaces) {
    for (const viewport of viewports) {
      const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
      const page = await context.newPage();
      const consoleErrors = [];
      const pageErrors = [];
      page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
      page.on('pageerror', (error) => pageErrors.push(error.message));

      const url = surface.url || `${baseUrl}${surface.path}`;
      let response;
      try {
        response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
        await page.waitForTimeout(500);
      } catch (error) {
        addFailure(surface, viewport, `navigation failed: ${error.message}`);
        await context.close();
        continue;
      }

      const status = response ? response.status() : 0;
      const expectedStatus = surface.expectedStatus || 200;
      if (status !== expectedStatus) addFailure(surface, viewport, `HTTP ${status}; expected ${expectedStatus}`);
      const bodyText = await page.locator('body').innerText().catch(() => '');
      if (!bodyText.includes(surface.marker)) addFailure(surface, viewport, `missing content marker: ${surface.marker}`);

      for (const selector of requiredSurfaceSelectors[surface.name] || []) {
        if ((await page.locator(selector).count()) !== 1) addFailure(surface, viewport, `expected one canonical ${selector}`);
      }

      const metrics = await page.evaluate(() => {
        const visible = (element) => {
          if (!element) return false;
          const style = getComputedStyle(element);
          const rect = element.getBoundingClientRect();
          return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
        };
        const h1s = [...document.querySelectorAll('h1')].filter(visible);
        const header = document.querySelector('.bm-header');
        const headerRect = header && visible(header) ? header.getBoundingClientRect() : null;
        const firstH1Rect = h1s[0] ? h1s[0].getBoundingClientRect() : null;
        const navLinks = [...document.querySelectorAll('#bm-nav a')].map((link) => ({
          text: (link.textContent || '').trim(), href: link.href, current: link.getAttribute('aria-current') || '',
        }));
        const proNavCount = navLinks.filter((link) => {
          try { return new URL(link.href).pathname.replace(/\/+$/, '') === '/pro'; } catch { return false; }
        }).length;
        const footerSocial = [...document.querySelectorAll('.bm-footer-social a')].map((link) => ({ key: link.getAttribute('data-social') || '', href: link.href }));
        return {
          clientWidth: document.documentElement.clientWidth,
          scrollWidth: document.documentElement.scrollWidth,
          bodyScrollWidth: document.body ? document.body.scrollWidth : 0,
          h1Count: h1s.length,
          headerBottom: headerRect ? headerRect.bottom : null,
          firstH1Top: firstH1Rect ? firstH1Rect.top : null,
          title: document.title,
          navLinks,
          proNavCount,
          legacySubscribeModalCount: document.querySelectorAll('#bm-subscribe-modal').length,
          footerNewsletterCount: document.querySelectorAll('#newsletter .bm-footer-newsletter').length,
          footerSocial,
        };
      });

      const overflow = Math.max(metrics.scrollWidth, metrics.bodyScrollWidth) - metrics.clientWidth;
      if (overflow > 2) addFailure(surface, viewport, `horizontal overflow ${overflow}px (scrollWidth=${metrics.scrollWidth}, clientWidth=${metrics.clientWidth})`);
      if (metrics.h1Count !== 1) addFailure(surface, viewport, `expected exactly one visible H1, found ${metrics.h1Count}`);
      if (metrics.headerBottom !== null && metrics.firstH1Top !== null && metrics.firstH1Top >= 0 && metrics.firstH1Top < metrics.headerBottom - 1) {
        addFailure(surface, viewport, `first H1 begins under sticky header (${metrics.firstH1Top}px < ${metrics.headerBottom}px)`);
      }

      const navLabels = metrics.navLinks.map((link) => link.text);
      if (expectedNavLabels.some((label) => !navLabels.includes(label))) addFailure(surface, viewport, `primary navigation labels drifted: ${navLabels.join(' | ')}`);
      if (metrics.proNavCount !== 1) addFailure(surface, viewport, `expected exactly one /pro/ header destination, found ${metrics.proNavCount}`);
      if (metrics.legacySubscribeModalCount !== 0) addFailure(surface, viewport, `legacy newsletter modal returned (${metrics.legacySubscribeModalCount})`);
      if (metrics.footerNewsletterCount !== 1) addFailure(surface, viewport, `expected one compact footer newsletter surface, found ${metrics.footerNewsletterCount}`);

      if (surface.active) {
        const activeLabels = metrics.navLinks.filter((link) => link.current === 'page').map((link) => link.text);
        if (!activeLabels.includes(surface.active)) addFailure(surface, viewport, `missing aria-current for ${surface.active}; active=${activeLabels.join(', ') || 'none'}`);
      }
      if (surface.name === 'home') {
        const socialMap = Object.fromEntries(metrics.footerSocial.map((item) => [item.key, item.href.replace(/\/$/, '')]));
        for (const [key, expected] of Object.entries(expectedSocial)) {
          if ((socialMap[key] || '') !== expected.replace(/\/$/, '')) addFailure(surface, viewport, `${key} footer destination mismatch: ${socialMap[key] || 'missing'}`);
        }
      }

      if (viewport.width === 390) {
        const hamburger = page.locator('#bm-hamburger');
        if (await hamburger.count()) {
          await hamburger.click();
          const opened = await page.evaluate(() => {
            const nav = document.getElementById('bm-nav');
            const button = document.getElementById('bm-hamburger');
            return { expanded: button?.getAttribute('aria-expanded'), hidden: nav?.getAttribute('aria-hidden'), inert: nav?.hasAttribute('inert'), openClass: nav?.classList.contains('open') };
          });
          if (opened.expanded !== 'true' || opened.hidden === 'true' || opened.inert || !opened.openClass) addFailure(surface, viewport, `mobile menu did not become accessible/open: ${JSON.stringify(opened)}`);
          await page.keyboard.press('Escape');
          const closed = await page.evaluate(() => {
            const nav = document.getElementById('bm-nav');
            const button = document.getElementById('bm-hamburger');
            return { expanded: button?.getAttribute('aria-expanded'), hidden: nav?.getAttribute('aria-hidden'), inert: nav?.hasAttribute('inert'), openClass: nav?.classList.contains('open') };
          });
          if (closed.expanded !== 'false' || closed.hidden !== 'true' || !closed.inert || closed.openClass) addFailure(surface, viewport, `mobile menu did not close accessibly on Escape: ${JSON.stringify(closed)}`);
        } else addFailure(surface, viewport, 'mobile hamburger control missing');
      }

      for (const error of pageErrors) addFailure(surface, viewport, `uncaught page error: ${error}`);
      const materialConsoleErrors = consoleErrors.filter((error) => !/favicon\.ico/i.test(error));
      for (const error of materialConsoleErrors) addFailure(surface, viewport, `console error: ${error}`);

      const screenshotPath = path.join(outputDir, `${surface.name}-${viewport.width}.png`);
      await page.screenshot({ path: screenshotPath, fullPage: true });

      let axeViolations = [];
      if (viewport.width === 390 || viewport.width === 1440) {
        try {
          const axe = await new AxeBuilder({ page }).analyze();
          axeViolations = axe.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact));
          for (const violation of axeViolations) {
            const targets = violation.nodes.slice(0, 8).map((node) => (node.target || []).join(' ')).filter(Boolean).join(', ');
            addFailure(surface, viewport, `a11y ${violation.impact}: ${violation.id} — ${violation.help}` + (targets ? `; targets: ${targets}` : ''));
          }
        } catch (error) { addFailure(surface, viewport, `axe scan failed: ${error.message}`); }
      }

      report.push({ surface: surface.name, url, viewport, status, expectedStatus, metrics, consoleErrors, pageErrors, axeViolations: axeViolations.map((violation) => ({ id: violation.id, impact: violation.impact, help: violation.help, helpUrl: violation.helpUrl, nodeCount: violation.nodes.length })) });
      console.log(`PASS ${surface.name} ${viewport.width}x${viewport.height} HTTP ${status} overflow=${Math.max(0, overflow)}px H1=${metrics.h1Count}`);
      await context.close();
    }
  }
} finally { await browser.close(); }

fs.writeFileSync(path.join(outputDir, 'report.json'), JSON.stringify({ baseUrl, report, failures }, null, 2));
if (failures.length) {
  console.error(`UI browser contract failed with ${failures.length} issue(s).`);
  process.exit(1);
}
console.log(`PASS UI browser contract across ${surfaces.length} surfaces and ${viewports.length} viewports.`);
