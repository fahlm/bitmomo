import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';

const baseUrl = (process.env.BITMOMO_UI_BASE_URL || 'https://bitmomo.id').replace(/\/$/, '');
const outputDir = process.env.BITMOMO_UI_OUTPUT_DIR || 'ui-artifacts';
fs.mkdirSync(outputDir, { recursive: true });

const surfaces = [
  { name: 'home', path: '/', marker: 'BTC Intelligence', expectedStatus: 200 },
  { name: 'btc-intelligence', path: '/btc-intelligence/', marker: 'Pahami kondisi BTC sekarang', active: 'BTC Intelligence', expectedStatus: 200 },
  { name: 'pro', path: '/pro/', marker: 'FOUNDING MEMBERSHIP', active: 'BITMOMO PRO', expectedStatus: 200 },
  { name: 'help', path: '/help/', marker: 'Help Center', expectedStatus: 200 },
  { name: 'research', path: '/category/riset/', marker: 'BITMOMO RESEARCH', active: 'Riset', expectedStatus: 200 },
  { name: 'about', path: '/tentang-kami/', marker: 'Tentang Kami', active: 'Tentang', expectedStatus: 200 },
  { name: 'privacy', path: '/kebijakan-privasi/', marker: 'Kebijakan Privasi', expectedStatus: 200 },
  { name: 'disclaimer', path: '/disclaimer/', marker: 'Disclaimer', expectedStatus: 200 },
  { name: 'search', path: '/?s=bitcoin', marker: 'Hasil untuk', expectedStatus: 200 },
  { name: 'account', path: '/pro/account/', marker: 'Akun Bitmomo Pro', expectedStatus: 200 },
  { name: 'not-found', path: '/__bitmomo-ui-audit-not-found__/', marker: 'Halaman tidak ditemukan.', expectedStatus: 404 },
];

const viewports = [
  { width: 360, height: 800 },
  { width: 390, height: 568 },
  { width: 390, height: 844 },
  { width: 768, height: 1024 },
  { width: 1024, height: 900 },
  { width: 1440, height: 1000 },
];

const expectedNavLabels = ['BTC Intelligence', 'Riset', 'Tentang', 'Masuk', 'BITMOMO PRO'];
const expectedSocial = (() => {
  const raw = String(process.env.BITMOMO_EXPECTED_SOCIAL_JSON || '').trim();
  if (!raw) return {};
  try {
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch (error) {
    console.error(`::error title=UI browser contract::Invalid BITMOMO_EXPECTED_SOCIAL_JSON: ${error.message}`);
    process.exit(2);
  }
})();

const failures = [];
const report = [];
const browser = await chromium.launch({ headless: true });

function addFailure(surface, viewport, message) {
  const label = `${surface.name}@${viewport.width}x${viewport.height}`;
  failures.push(`${label}: ${message}`);
  console.error(`::error title=UI browser contract::${label}: ${message}`);
}

async function collectMetrics(page) {
  return page.evaluate(() => {
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
    const firstH1Style = h1s[0] ? getComputedStyle(h1s[0]) : null;
    const navLinks = [...document.querySelectorAll('#bm-nav a')].map((link) => ({
      text: (link.textContent || '').trim(), href: link.href, current: link.getAttribute('aria-current') || '',
    }));
    const proNavCount = navLinks.filter((link) => {
      try { return new URL(link.href).pathname.replace(/\/+$/, '') === '/pro'; }
      catch { return false; }
    }).length;
    const footerSocial = [...document.querySelectorAll('.bm-footer-social a')].map((link) => ({
      key: link.getAttribute('data-social') || '', href: link.href,
    }));
    const missingFragmentTargets = [...document.querySelectorAll('a[href^="#"]')]
      .map((link) => link.getAttribute('href') || '')
      .filter((href) => href.length > 1 && !document.getElementById(decodeURIComponent(href.slice(1))));
    const emptyLinks = [...document.querySelectorAll('a')]
      .filter((link) => !String(link.getAttribute('href') || '').trim())
      .map((link) => (link.textContent || '').trim() || '(unlabelled)');

    const articleBody = document.querySelector('.bm-article-body');
    const articleBodyRect = articleBody && visible(articleBody) ? articleBody.getBoundingClientRect() : null;
    const articleBodyStyle = articleBodyRect ? getComputedStyle(articleBody) : null;
    const articleMeta = document.querySelector('.bm-article-meta');
    const articleEyebrow = document.querySelector('.bm-article .bm-public-eyebrow');

    return {
      clientWidth: document.documentElement.clientWidth,
      scrollWidth: document.documentElement.scrollWidth,
      bodyScrollWidth: document.body ? document.body.scrollWidth : 0,
      h1Count: h1s.length,
      headerBottom: headerRect ? headerRect.bottom : null,
      firstH1Top: firstH1Rect ? firstH1Rect.top : null,
      firstH1FontSize: firstH1Style ? parseFloat(firstH1Style.fontSize) : null,
      title: document.title,
      navLinks,
      proNavCount,
      legacySubscribeModalCount: document.querySelectorAll('#bm-subscribe-modal').length,
      footerNewsletterCount: document.querySelectorAll('#newsletter .bm-footer-newsletter').length,
      footerSocial,
      missingFragmentTargets,
      emptyLinks,
      homeResearchLabels: [...document.querySelectorAll('.bm-research .bm-research-category')].map((el) => (el.textContent || '').trim()),
      homeResearchItems: document.querySelectorAll('.bm-research .bm-research-item').length,
      article: {
        present: !!articleBodyRect,
        width: articleBodyRect ? articleBodyRect.width : null,
        fontSize: articleBodyStyle ? parseFloat(articleBodyStyle.fontSize) : null,
        lineHeight: articleBodyStyle ? parseFloat(articleBodyStyle.lineHeight) : null,
        meta: articleMeta ? (articleMeta.textContent || '').replace(/\s+/g, ' ').trim() : '',
        eyebrow: articleEyebrow ? (articleEyebrow.textContent || '').trim() : '',
        breadcrumbCount: document.querySelectorAll('.bm-article-breadcrumb').length,
        researchStandardCount: document.querySelectorAll('.bm-article-standard').length,
        genericPostNavCount: document.querySelectorAll('.bm-post-nav').length,
        relatedEditorialCount: document.querySelectorAll('.bm-related--editorial').length,
      },
    };
  });
}

async function auditAxe(page, surface, viewport) {
  if (viewport.width !== 390 && viewport.width !== 1440) return [];
  try {
    const axe = await new AxeBuilder({ page }).analyze();
    const serious = axe.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact));
    for (const violation of serious) {
      const targets = violation.nodes.slice(0, 8).map((node) => (node.target || []).join(' ')).filter(Boolean).join(', ');
      addFailure(surface, viewport, `a11y ${violation.impact}: ${violation.id} — ${violation.help}` + (targets ? `; targets: ${targets}` : ''));
    }
    return serious;
  } catch (error) {
    addFailure(surface, viewport, `axe scan failed: ${error.message}`);
    return [];
  }
}

async function auditClosedMobileNavFocus(page, surface, viewport, phase) {
  const closed = await page.evaluate(() => {
    const nav = document.getElementById('bm-nav');
    const button = document.getElementById('bm-hamburger');
    return {
      expanded: button?.getAttribute('aria-expanded'),
      hidden: nav?.getAttribute('aria-hidden'),
      inert: nav?.hasAttribute('inert'),
      openClass: nav?.classList.contains('open'),
    };
  });
  if (closed.expanded !== 'false' || closed.hidden !== 'true' || !closed.inert || closed.openClass) {
    addFailure(surface, viewport, `${phase}: mobile menu is not accessibly closed: ${JSON.stringify(closed)}`);
    return;
  }

  const hamburger = page.locator('#bm-hamburger');
  await hamburger.focus();
  for (let index = 0; index < 12; index += 1) {
    await page.keyboard.press('Tab');
    const focus = await page.evaluate(() => {
      const nav = document.getElementById('bm-nav');
      const active = document.activeElement;
      return {
        inNav: Boolean(nav && active && nav.contains(active)),
        activeTag: active?.tagName || '',
        activeText: (active?.textContent || '').trim().slice(0, 80),
      };
    });
    if (focus.inNav) {
      addFailure(surface, viewport, `${phase}: closed mobile nav received keyboard focus on ${focus.activeTag} ${focus.activeText}`);
      break;
    }
  }
}

try {
  for (const surface of surfaces) {
    for (const viewport of viewports) {
      const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
      const page = await context.newPage();
      const consoleErrors = [];
      const pageErrors = [];

      page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
      page.on('pageerror', (error) => pageErrors.push(error.message));

      const url = `${baseUrl}${surface.path}`;
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
      if (status !== surface.expectedStatus) addFailure(surface, viewport, `HTTP ${status}; expected ${surface.expectedStatus}`);

      const bodyText = await page.locator('body').innerText().catch(() => '');
      if (!bodyText.includes(surface.marker)) addFailure(surface, viewport, `missing content marker: ${surface.marker}`);

      const metrics = await collectMetrics(page);
      const overflow = Math.max(metrics.scrollWidth, metrics.bodyScrollWidth) - metrics.clientWidth;
      if (overflow > 2) addFailure(surface, viewport, `horizontal overflow ${overflow}px`);
      if (metrics.h1Count !== 1) addFailure(surface, viewport, `expected exactly one visible H1, found ${metrics.h1Count}`);
      if (metrics.headerBottom !== null && metrics.firstH1Top !== null && metrics.firstH1Top >= 0 && metrics.firstH1Top < metrics.headerBottom - 1) {
        addFailure(surface, viewport, `first H1 begins under sticky header (${metrics.firstH1Top}px < ${metrics.headerBottom}px)`);
      }
      if (metrics.missingFragmentTargets.length) addFailure(surface, viewport, `links target missing same-page anchors: ${metrics.missingFragmentTargets.join(', ')}`);
      if (metrics.emptyLinks.length) addFailure(surface, viewport, `empty href links: ${metrics.emptyLinks.join(', ')}`);

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
        const socialKeys = new Set();
        const socialMap = {};
        for (const item of metrics.footerSocial) {
          if (!item.key) addFailure(surface, viewport, `rendered social link has no data-social key: ${item.href}`);
          if (socialKeys.has(item.key)) addFailure(surface, viewport, `duplicate rendered social channel: ${item.key}`);
          socialKeys.add(item.key);
          try {
            const parsed = new URL(item.href);
            if (parsed.protocol !== 'https:') addFailure(surface, viewport, `${item.key || 'social'} must use HTTPS: ${item.href}`);
          } catch {
            addFailure(surface, viewport, `${item.key || 'social'} has invalid destination: ${item.href}`);
          }
          if (item.key) socialMap[item.key] = item.href.replace(/\/$/, '');
        }

        // Social is deliberately fail-closed. Missing unconfigured channels are
        // valid. CI enforces exact destinations only when the environment
        // explicitly supplies BITMOMO_EXPECTED_SOCIAL_JSON.
        for (const [key, expected] of Object.entries(expectedSocial)) {
          const observed = socialMap[key] || '';
          const normalizedExpected = String(expected || '').replace(/\/$/, '');
          if (observed !== normalizedExpected) addFailure(surface, viewport, `${key} footer destination mismatch: ${observed || 'missing'} expected=${normalizedExpected}`);
        }

        if (metrics.homeResearchItems > 3) addFailure(surface, viewport, `homepage research renders ${metrics.homeResearchItems} items; max is 3`);
        if (metrics.homeResearchLabels.some((label) => label !== 'Market Research')) addFailure(surface, viewport, `homepage research contains non-market label(s): ${metrics.homeResearchLabels.join(', ')}`);
      }

      if (viewport.width === 390) {
        const hamburger = page.locator('#bm-hamburger');
        if (await hamburger.count()) {
          await auditClosedMobileNavFocus(page, surface, viewport, 'initial state');
          await hamburger.click();
          const opened = await page.evaluate(() => {
            const nav = document.getElementById('bm-nav');
            const button = document.getElementById('bm-hamburger');
            const rect = nav?.getBoundingClientRect();
            return {
              expanded: button?.getAttribute('aria-expanded'),
              hidden: nav?.getAttribute('aria-hidden'),
              inert: nav?.hasAttribute('inert'),
              openClass: nav?.classList.contains('open'),
              navTop: rect?.top ?? null,
              navBottom: rect?.bottom ?? null,
              navClientHeight: nav?.clientHeight ?? null,
              navScrollHeight: nav?.scrollHeight ?? null,
              viewportHeight: window.innerHeight,
            };
          });
          if (opened.expanded !== 'true' || opened.hidden === 'true' || opened.inert || !opened.openClass) {
            addFailure(surface, viewport, `mobile menu did not become accessible/open: ${JSON.stringify(opened)}`);
          }
          if (opened.navBottom !== null && opened.navBottom > opened.viewportHeight + 1) {
            addFailure(surface, viewport, `mobile nav extends below viewport: bottom=${opened.navBottom}px viewport=${opened.viewportHeight}px`);
          }
          if (opened.navScrollHeight !== null && opened.navClientHeight !== null && opened.navScrollHeight > opened.navClientHeight + 1) {
            const overflowY = await page.locator('#bm-nav').evaluate((nav) => getComputedStyle(nav).overflowY);
            if (!['auto', 'scroll'].includes(overflowY)) addFailure(surface, viewport, `short-height mobile nav cannot scroll; overflow-y=${overflowY}`);
          }

          await page.keyboard.press('Escape');
          const focusReturned = await page.evaluate(() => document.activeElement?.id === 'bm-hamburger');
          if (!focusReturned) addFailure(surface, viewport, 'Escape did not return focus to the hamburger control');
          await auditClosedMobileNavFocus(page, surface, viewport, 'after Escape');
        } else addFailure(surface, viewport, 'mobile hamburger control missing');
      }

      for (const error of consoleErrors) addFailure(surface, viewport, `console error: ${error}`);
      for (const error of pageErrors) addFailure(surface, viewport, `uncaught page error: ${error}`);

      const screenshotPath = path.join(outputDir, `${surface.name}-${viewport.width}x${viewport.height}.png`);
      await page.screenshot({ path: screenshotPath, fullPage: true });
      const axeViolations = await auditAxe(page, surface, viewport);

      report.push({ surface: surface.name, url, viewport, status, metrics, consoleErrors, pageErrors, axeViolations: axeViolations.map((v) => ({ id: v.id, impact: v.impact, help: v.help, nodeCount: v.nodes.length })) });
      console.log(`PASS ${surface.name} ${viewport.width}x${viewport.height} HTTP ${status} overflow=${Math.max(0, overflow)}px H1=${metrics.h1Count}`);
      await context.close();
    }
  }

  // Audit one real qualified Research article. Failure to discover one is itself
  // a staging failure because the Research -> article journey remains unproven.
  const discovery = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await discovery.goto(`${baseUrl}/category/riset/`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  const articleHref = await discovery.locator('.bm-research-lead__title a, .bm-research-library__copy h3 a').first().getAttribute('href').catch(() => null);
  await discovery.close();

  if (!articleHref) {
    addFailure(
      { name: 'qualified-article-discovery' },
      { width: 1440, height: 1000 },
      'no qualified Research article discoverable from current Research Hub selectors',
    );
  } else {
    const articleUrl = new URL(articleHref, baseUrl).toString();
    for (const viewport of [{ width: 390, height: 844 }, { width: 1440, height: 1000 }]) {
      const surface = { name: 'qualified-article', path: articleUrl, marker: '', expectedStatus: 200 };
      const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
      const page = await context.newPage();
      const consoleErrors = [];
      const pageErrors = [];
      page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
      page.on('pageerror', (error) => pageErrors.push(error.message));
      const response = await page.goto(articleUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      await page.waitForTimeout(350);
      const status = response ? response.status() : 0;
      if (status !== 200) addFailure(surface, viewport, `HTTP ${status}`);
      const metrics = await collectMetrics(page);
      const overflow = Math.max(metrics.scrollWidth, metrics.bodyScrollWidth) - metrics.clientWidth;
      if (overflow > 2) addFailure(surface, viewport, `horizontal overflow ${overflow}px`);
      if (metrics.h1Count !== 1) addFailure(surface, viewport, `expected exactly one visible H1, found ${metrics.h1Count}`);

      if (!metrics.article.present) addFailure(surface, viewport, 'canonical .bm-article-body reading surface missing');
      if (metrics.article.width !== null && metrics.article.width > 740) addFailure(surface, viewport, `reading column too wide: ${metrics.article.width.toFixed(1)}px`);
      if (metrics.article.fontSize !== null && metrics.article.fontSize < 17) addFailure(surface, viewport, `article body font too small: ${metrics.article.fontSize}px`);
      if (metrics.article.fontSize && metrics.article.lineHeight && metrics.article.lineHeight / metrics.article.fontSize < 1.65) {
        addFailure(surface, viewport, `article line-height too tight: ${(metrics.article.lineHeight / metrics.article.fontSize).toFixed(2)}`);
      }
      if (metrics.firstH1FontSize !== null && metrics.firstH1FontSize > 54) addFailure(surface, viewport, `article H1 too large for reading surface: ${metrics.firstH1FontSize}px`);
      if (!/Bitmomo Research/.test(metrics.article.meta)) addFailure(surface, viewport, `research publisher context missing: ${metrics.article.meta || 'empty'}`);
      if (!/Dipublikasikan/.test(metrics.article.meta)) addFailure(surface, viewport, `publication date context missing: ${metrics.article.meta || 'empty'}`);
      if (!/menit baca/.test(metrics.article.meta)) addFailure(surface, viewport, `reading-time context missing: ${metrics.article.meta || 'empty'}`);
      if (!['MARKET RESEARCH', 'INTELLIGENCE SYSTEMS RESEARCH'].includes(metrics.article.eyebrow)) addFailure(surface, viewport, `qualified article has wrong institutional label: ${metrics.article.eyebrow || 'missing'}`);
      if (metrics.article.breadcrumbCount !== 1) addFailure(surface, viewport, `expected one research-context breadcrumb, found ${metrics.article.breadcrumbCount}`);
      if (metrics.article.researchStandardCount !== 1) addFailure(surface, viewport, `expected one research-standard trust block, found ${metrics.article.researchStandardCount}`);
      if (metrics.article.genericPostNavCount !== 0) addFailure(surface, viewport, `generic previous/next post navigation returned (${metrics.article.genericPostNavCount})`);

      for (const error of consoleErrors) addFailure(surface, viewport, `console error: ${error}`);
      for (const error of pageErrors) addFailure(surface, viewport, `uncaught page error: ${error}`);
      const axeViolations = await auditAxe(page, surface, viewport);
      await page.screenshot({ path: path.join(outputDir, `qualified-article-${viewport.width}x${viewport.height}.png`), fullPage: true });
      report.push({ surface: surface.name, url: articleUrl, viewport, status, metrics, consoleErrors, pageErrors, axeViolations: axeViolations.map((v) => ({ id: v.id, impact: v.impact, help: v.help, nodeCount: v.nodes.length })) });
      await context.close();
    }
  }
} finally {
  await browser.close();
}

fs.writeFileSync(path.join(outputDir, 'report.json'), JSON.stringify({ baseUrl, report, failures }, null, 2));
if (failures.length) {
  console.error(`UI browser contract failed with ${failures.length} issue(s).`);
  process.exit(1);
}
console.log(`PASS UI browser contract across ${surfaces.length} core surfaces and all configured viewports.`);
