import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const baseUrl = (process.env.BITMOMO_UI_BASE_URL || 'https://seagreen-snail-158456.hostingersite.com').replace(/\/$/, '');
const outputDir = process.env.BITMOMO_UI_OUTPUT_DIR || 'ui-artifacts';
fs.mkdirSync(outputDir, { recursive: true });

const viewports = [
  { width: 360, height: 800 },
  { width: 390, height: 568 },
  { width: 390, height: 844 },
  { width: 768, height: 1024 },
  { width: 1024, height: 900 },
  { width: 1440, height: 1000 },
];

const failures = [];
const report = [];
const browser = await chromium.launch({ headless: true });

function fail(viewport, message) {
  const label = `home-footer@${viewport.width}x${viewport.height}`;
  failures.push(`${label}: ${message}`);
  console.error(`::error title=Footer/home geometry::${label}: ${message}`);
}

function approx(actual, expected, tolerance = 2) {
  return Number.isFinite(actual) && Number.isFinite(expected) && Math.abs(actual - expected) <= tolerance;
}

function maxDelta(values) {
  if (!values.length) return 0;
  return Math.max(...values) - Math.min(...values);
}

try {
  for (const viewport of viewports) {
    const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
    const page = await context.newPage();
    const consoleErrors = [];
    const pageErrors = [];

    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
    page.on('pageerror', (error) => pageErrors.push(error.message));

    let response;
    try {
      response = await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      await page.waitForTimeout(500);
    } catch (error) {
      fail(viewport, `navigation failed: ${error.message}`);
      await context.close();
      continue;
    }

    if (!response || response.status() !== 200) {
      fail(viewport, `homepage HTTP ${response ? response.status() : 0}; expected 200`);
    }

    const metrics = await page.evaluate(() => {
      const rect = (selector) => {
        const element = document.querySelector(selector);
        if (!element) return null;
        const box = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return {
          left: box.left,
          right: box.right,
          top: box.top,
          bottom: box.bottom,
          width: box.width,
          height: box.height,
          textAlign: style.textAlign,
          paddingLeft: parseFloat(style.paddingLeft) || 0,
          paddingRight: parseFloat(style.paddingRight) || 0,
          paddingTop: parseFloat(style.paddingTop) || 0,
          paddingBottom: parseFloat(style.paddingBottom) || 0,
          display: style.display,
          gridTemplateColumns: style.gridTemplateColumns,
          justifyContent: style.justifyContent,
          alignItems: style.alignItems,
        };
      };

      const rects = (selector) => [...document.querySelectorAll(selector)].map((element) => {
        const box = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return {
          left: box.left,
          right: box.right,
          top: box.top,
          bottom: box.bottom,
          width: box.width,
          height: box.height,
          textAlign: style.textAlign,
        };
      });

      const footer = rect('.bm-footer');
      const footerContainer = rect('.bm-footer > .bm-container');
      const principles = rect('.bm-footer-principles');
      const grid = rect('.bm-footer-grid');
      const newsletter = rect('.bm-footer-newsletter');
      const newsletterAnchor = rect('.bm-footer-newsletter-anchor');
      const footerBottom = rect('.bm-footer-bottom');
      const footerIdentity = rect('.bm-footer-bottom__identity');
      const footerLegal = rect('.bm-footer-legal');
      const principleItems = rects('.bm-footer-principle');
      const footerGroups = rects('.bm-footer-group');
      const footerBrand = rect('.bm-footer-brand');

      const foundingContainer = rect('#founding-whitelist .bm-container');
      const foundingUnified = rect('#founding-whitelist .bm-wl-unified');
      const foundingIntro = rect('#founding-whitelist .bm-wl-unified__intro');
      const foundingForm = rect('#founding-whitelist .bm-wl-unified__form');
      const foundingFacts = rect('#founding-whitelist .bm-wl-unified__facts');

      const visibleSubmit = [...document.querySelectorAll('#founding-whitelist .bm-wl__submit, #founding-whitelist .bm-wl-teaser-cta')]
        .find((element) => {
          const box = element.getBoundingClientRect();
          const style = getComputedStyle(element);
          return box.width > 0 && box.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
        });
      const submit = visibleSubmit ? (() => {
        const box = visibleSubmit.getBoundingClientRect();
        const style = getComputedStyle(visibleSubmit);
        return {
          left: box.left,
          right: box.right,
          width: box.width,
          height: box.height,
          textAlign: style.textAlign,
          justifyContent: style.justifyContent,
        };
      })() : null;

      return {
        viewportWidth: document.documentElement.clientWidth,
        documentScrollWidth: Math.max(document.documentElement.scrollWidth, document.body?.scrollWidth || 0),
        footer,
        footerContainer,
        principles,
        grid,
        newsletter,
        newsletterAnchor,
        footerBottom,
        footerIdentity,
        footerLegal,
        principleItems,
        footerGroups,
        footerBrand,
        foundingContainer,
        foundingUnified,
        foundingIntro,
        foundingForm,
        foundingFacts,
        submit,
      };
    });

    const required = [
      ['footer', metrics.footer],
      ['footer container', metrics.footerContainer],
      ['footer principles', metrics.principles],
      ['footer grid', metrics.grid],
      ['footer newsletter', metrics.newsletter],
      ['footer bottom', metrics.footerBottom],
      ['Founding container', metrics.foundingContainer],
      ['Founding unified surface', metrics.foundingUnified],
      ['Founding intro', metrics.foundingIntro],
      ['Founding form', metrics.foundingForm],
    ];
    for (const [name, value] of required) {
      if (!value) fail(viewport, `${name} is missing from the rendered homepage`);
    }

    if (required.some(([, value]) => !value)) {
      await page.screenshot({ path: path.join(outputDir, `footer-home-geometry-${viewport.width}x${viewport.height}.png`), fullPage: true });
      await context.close();
      continue;
    }

    const overflow = metrics.documentScrollWidth - metrics.viewportWidth;
    if (overflow > 2) fail(viewport, `page-level horizontal overflow ${overflow}px`);

    const footerCenter = (metrics.footerContainer.left + metrics.footerContainer.right) / 2;
    if (!approx(footerCenter, metrics.viewportWidth / 2, 1.5)) {
      fail(viewport, `footer shell is not centered: center=${footerCenter.toFixed(2)} viewportCenter=${(metrics.viewportWidth / 2).toFixed(2)}`);
    }

    if (!approx(metrics.footer.left, 0, 1) || !approx(metrics.footer.right, metrics.viewportWidth, 1)) {
      fail(viewport, `footer background does not span the viewport exactly: left=${metrics.footer.left.toFixed(2)} right=${metrics.footer.right.toFixed(2)}`);
    }

    if (metrics.footer.textAlign !== 'left' || metrics.footerContainer.textAlign !== 'left') {
      fail(viewport, `legacy centered footer alignment leaked back in: footer=${metrics.footer.textAlign}, container=${metrics.footerContainer.textAlign}`);
    }
    if (metrics.footer.paddingTop > 0.5 || metrics.footer.paddingBottom > 0.5) {
      fail(viewport, `legacy footer outer padding leaked back in: top=${metrics.footer.paddingTop}px bottom=${metrics.footer.paddingBottom}px`);
    }

    const contentLeft = metrics.footerContainer.left + metrics.footerContainer.paddingLeft;
    const contentRight = metrics.footerContainer.right - metrics.footerContainer.paddingRight;
    for (const [name, section] of [
      ['principles', metrics.principles],
      ['grid', metrics.grid],
      ['newsletter anchor', metrics.newsletterAnchor],
      ['footer bottom', metrics.footerBottom],
    ]) {
      if (!section) continue;
      if (!approx(section.left, contentLeft, 1.5) || !approx(section.right, contentRight, 1.5)) {
        fail(viewport, `${name} does not align to the canonical footer content rail: ${section.left.toFixed(2)}..${section.right.toFixed(2)} expected ${contentLeft.toFixed(2)}..${contentRight.toFixed(2)}`);
      }
    }

    if (metrics.newsletter.left < contentLeft - 2 || metrics.newsletter.right > contentRight + 2) {
      fail(viewport, 'newsletter surface escapes the canonical footer content rail');
    }

    if (metrics.principles.bottom > metrics.grid.top + 1) fail(viewport, 'footer principles overlap the IA grid');
    if (metrics.grid.bottom > metrics.newsletter.top + 1) fail(viewport, 'footer IA grid overlaps the newsletter surface');
    if (metrics.newsletter.bottom > metrics.footerBottom.top + 24) fail(viewport, 'footer newsletter overlaps or crowds the legal rail');

    if (viewport.width > 900) {
      if (metrics.principleItems.length !== 3) fail(viewport, `expected 3 principle columns, found ${metrics.principleItems.length}`);
      if (metrics.principleItems.length === 3 && maxDelta(metrics.principleItems.map((item) => item.width)) > 2) {
        fail(viewport, `principle columns are not equal width: ${metrics.principleItems.map((item) => item.width.toFixed(2)).join(', ')}`);
      }
      if (metrics.footerGroups.length !== 3) fail(viewport, `expected 3 footer IA groups, found ${metrics.footerGroups.length}`);
      if (metrics.footerGroups.length === 3 && maxDelta(metrics.footerGroups.map((item) => item.top)) > 2) {
        fail(viewport, `desktop footer IA groups do not share one top baseline: ${metrics.footerGroups.map((item) => item.top.toFixed(2)).join(', ')}`);
      }
      if (metrics.footerIdentity && metrics.footerLegal) {
        const identityCenter = (metrics.footerIdentity.top + metrics.footerIdentity.bottom) / 2;
        const legalCenter = (metrics.footerLegal.top + metrics.footerLegal.bottom) / 2;
        if (!approx(identityCenter, legalCenter, 4)) {
          fail(viewport, `footer identity/legal rail is vertically misaligned: ${identityCenter.toFixed(2)} vs ${legalCenter.toFixed(2)}`);
        }
      }
    }

    if (viewport.width <= 640 && metrics.principleItems.length === 3) {
      const lefts = metrics.principleItems.map((item) => item.left);
      if (maxDelta(lefts) > 2) fail(viewport, `mobile principles are not on one aligned column: ${lefts.map((value) => value.toFixed(2)).join(', ')}`);
    }

    if (viewport.width <= 430 && metrics.footerGroups.length === 3) {
      const lefts = metrics.footerGroups.map((item) => item.left);
      if (maxDelta(lefts) > 2) fail(viewport, `narrow-mobile footer IA is not one aligned column: ${lefts.map((value) => value.toFixed(2)).join(', ')}`);
    }

    const foundingCenter = (metrics.foundingContainer.left + metrics.foundingContainer.right) / 2;
    if (!approx(foundingCenter, metrics.viewportWidth / 2, 1.5)) {
      fail(viewport, `Founding/home-bottom shell is not centered: center=${foundingCenter.toFixed(2)}`);
    }
    const foundingContentLeft = metrics.foundingContainer.left + metrics.foundingContainer.paddingLeft;
    const foundingContentRight = metrics.foundingContainer.right - metrics.foundingContainer.paddingRight;
    if (!approx(metrics.foundingUnified.left, foundingContentLeft, 1.5) || !approx(metrics.foundingUnified.right, foundingContentRight, 1.5)) {
      fail(viewport, `Founding conversion surface does not align to homepage content rail: ${metrics.foundingUnified.left.toFixed(2)}..${metrics.foundingUnified.right.toFixed(2)} expected ${foundingContentLeft.toFixed(2)}..${foundingContentRight.toFixed(2)}`);
    }
    if (metrics.foundingIntro.textAlign !== 'left' || metrics.foundingForm.textAlign !== 'left') {
      fail(viewport, `Founding conversion copy has inconsistent alignment: intro=${metrics.foundingIntro.textAlign}, form=${metrics.foundingForm.textAlign}`);
    }
    if (metrics.foundingFacts && (metrics.foundingFacts.left < metrics.foundingUnified.left - 2 || metrics.foundingFacts.right > metrics.foundingUnified.right + 2)) {
      fail(viewport, 'Founding facts escape the conversion surface');
    }

    if (viewport.width > 900) {
      if (!approx(metrics.foundingIntro.top, metrics.foundingForm.top, 2) || !approx(metrics.foundingIntro.bottom, metrics.foundingForm.bottom, 2)) {
        fail(viewport, `desktop Founding columns do not share the same vertical rail: intro=${metrics.foundingIntro.top.toFixed(2)}..${metrics.foundingIntro.bottom.toFixed(2)} form=${metrics.foundingForm.top.toFixed(2)}..${metrics.foundingForm.bottom.toFixed(2)}`);
      }
      if (metrics.foundingIntro.right > metrics.foundingForm.left + 2) fail(viewport, 'desktop Founding columns overlap');
    } else if (metrics.foundingIntro.bottom > metrics.foundingForm.top + 2) {
      fail(viewport, 'tablet/mobile Founding blocks overlap instead of stacking');
    }

    if (metrics.submit && metrics.submit.width < 44) fail(viewport, `Founding CTA width is below usable geometry: ${metrics.submit.width}px`);
    if (metrics.submit && metrics.submit.height < 44) fail(viewport, `Founding CTA height is below 44px: ${metrics.submit.height}px`);

    for (const error of consoleErrors) fail(viewport, `console error: ${error}`);
    for (const error of pageErrors) fail(viewport, `uncaught page error: ${error}`);

    await page.screenshot({ path: path.join(outputDir, `footer-home-geometry-${viewport.width}x${viewport.height}.png`), fullPage: true });
    report.push({ viewport, metrics, consoleErrors, pageErrors });
    console.log(`PASS footer/home geometry ${viewport.width}x${viewport.height}`);
    await context.close();
  }
} finally {
  await browser.close();
}

fs.writeFileSync(path.join(outputDir, 'footer-home-geometry-report.json'), `${JSON.stringify(report, null, 2)}\n`);

if (failures.length) {
  console.error(`Footer/home geometry contract failed with ${failures.length} issue(s).`);
  process.exit(1);
}

console.log('PASS footer/home geometry browser contract.');
