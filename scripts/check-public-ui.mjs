import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';

const baseUrl = (process.env.BITMOMO_UI_BASE_URL || 'https://bitmomo.id').replace(/\/$/, '');
const outputDir = process.env.BITMOMO_UI_OUTPUT_DIR || 'ui-artifacts';
fs.mkdirSync(outputDir, { recursive: true });

const surfaces = [
  { name: 'home', path: '/', marker: 'BTC Intelligence' },
  { name: 'btc-intelligence', path: '/btc-intelligence/', marker: 'Pahami BTC dalam konteks.' },
  { name: 'pro', path: '/pro/', marker: 'FOUNDING MEMBERSHIP' },
  { name: 'help', path: '/help/', marker: 'Help Center' },
  { name: 'research', path: '/category/riset/', marker: 'Riset' },
  { name: 'about', path: '/tentang-kami/', marker: 'Tentang Kami' },
  { name: 'privacy', path: '/kebijakan-privasi/', marker: 'Kebijakan Privasi' },
  { name: 'disclaimer', path: '/disclaimer/', marker: 'Disclaimer' },
];

const viewports = [
  { width: 360, height: 800 },
  { width: 390, height: 844 },
  { width: 768, height: 1024 },
  { width: 1024, height: 900 },
  { width: 1440, height: 1000 },
];

const failures = [];
const report = [];
const browser = await chromium.launch({ headless: true });

function addFailure(surface, viewport, message) {
  const label = `${surface.name}@${viewport.width}x${viewport.height}`;
  failures.push(`${label}: ${message}`);
  console.error(`::error title=UI browser contract::${label}: ${message}`);
}

try {
  for (const surface of surfaces) {
    for (const viewport of viewports) {
      const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
      const page = await context.newPage();
      const consoleErrors = [];
      const pageErrors = [];

      page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
      });
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
      if (status !== 200) addFailure(surface, viewport, `HTTP ${status}`);

      const bodyText = await page.locator('body').innerText().catch(() => '');
      if (!bodyText.includes(surface.marker)) {
        addFailure(surface, viewport, `missing content marker: ${surface.marker}`);
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

        return {
          clientWidth: document.documentElement.clientWidth,
          scrollWidth: document.documentElement.scrollWidth,
          bodyScrollWidth: document.body ? document.body.scrollWidth : 0,
          h1Count: h1s.length,
          headerBottom: headerRect ? headerRect.bottom : null,
          firstH1Top: firstH1Rect ? firstH1Rect.top : null,
          title: document.title,
        };
      });

      const overflow = Math.max(metrics.scrollWidth, metrics.bodyScrollWidth) - metrics.clientWidth;
      if (overflow > 2) {
        addFailure(surface, viewport, `horizontal overflow ${overflow}px (scrollWidth=${metrics.scrollWidth}, clientWidth=${metrics.clientWidth})`);
      }

      if (metrics.h1Count !== 1) {
        addFailure(surface, viewport, `expected exactly one visible H1, found ${metrics.h1Count}`);
      }

      if (
        metrics.headerBottom !== null &&
        metrics.firstH1Top !== null &&
        metrics.firstH1Top >= 0 &&
        metrics.firstH1Top < metrics.headerBottom - 1
      ) {
        addFailure(surface, viewport, `first H1 begins under sticky header (${metrics.firstH1Top}px < ${metrics.headerBottom}px)`);
      }

      for (const error of pageErrors) {
        addFailure(surface, viewport, `uncaught page error: ${error}`);
      }

      const screenshotPath = path.join(outputDir, `${surface.name}-${viewport.width}.png`);
      await page.screenshot({ path: screenshotPath, fullPage: true });

      let axeViolations = [];
      if (viewport.width === 390 || viewport.width === 1440) {
        try {
          const axe = await new AxeBuilder({ page }).analyze();
          axeViolations = axe.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact));
          for (const violation of axeViolations) {
            const targets = violation.nodes
              .slice(0, 8)
              .map((node) => (node.target || []).join(' '))
              .filter(Boolean)
              .join(', ');
            addFailure(
              surface,
              viewport,
              `a11y ${violation.impact}: ${violation.id} — ${violation.help}` + (targets ? `; targets: ${targets}` : '')
            );
          }
        } catch (error) {
          addFailure(surface, viewport, `axe scan failed: ${error.message}`);
        }
      }

      report.push({
        surface: surface.name,
        url,
        viewport,
        status,
        metrics,
        consoleErrors,
        pageErrors,
        axeViolations: axeViolations.map((violation) => ({
          id: violation.id,
          impact: violation.impact,
          help: violation.help,
          helpUrl: violation.helpUrl,
          nodeCount: violation.nodes.length,
          nodes: violation.nodes.map((node) => ({
            target: node.target,
            html: node.html,
            failureSummary: node.failureSummary,
            impact: node.impact,
            any: node.any,
            all: node.all,
            none: node.none,
          })),
        })),
      });

      console.log(`PASS ${surface.name} ${viewport.width}x${viewport.height} HTTP ${status} overflow=${Math.max(0, overflow)}px H1=${metrics.h1Count}`);
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

console.log(`PASS UI browser contract across ${surfaces.length} surfaces and ${viewports.length} viewports.`);
