/**
 * Capture real Desktop/Mobile screenshots for prep portal manual table.
 *
 * Usage:
 *   php docs/audits/screenshots/prep-attendance-portal-tabs/render-fixture.php
 *   # rewrite CSS to /build/... then:
 *   php -S 127.0.0.1:8765 -t public
 *   # in another shell, copy fixture to public/__prep-portal-shot.html then:
 *   node docs/audits/screenshots/prep-attendance-portal-tabs/capture.mjs
 */
import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const base = process.env.BASE || 'http://127.0.0.1:8765';
const shotPath = process.env.SHOT_PATH || '/__prep-portal-shot.html';

async function measure(page) {
  return page.evaluate(() => {
    const root = document.documentElement;
    const container = document.querySelector('main > div.mx-auto');
    const table = document.querySelector('#manual-list');
    const nav = document.querySelector('nav[aria-label="التنقل بين الصفحات"]');
    const cs = container ? getComputedStyle(container) : null;
    return {
      viewportWidth: window.innerWidth,
      scrollWidth: root.scrollWidth,
      clientWidth: root.clientWidth,
      hasHorizontalOverflow: root.scrollWidth > root.clientWidth + 1,
      hasMain: !!document.querySelector('main'),
      containerMaxWidth: cs?.maxWidth ?? null,
      containerWidth: cs?.width ?? null,
      tableScrollWidth: table?.scrollWidth ?? null,
      tableClientWidth: table?.clientWidth ?? null,
      paginationScrollWidth: nav?.scrollWidth ?? null,
      paginationClientWidth: nav?.clientWidth ?? null,
      paginationText: nav?.innerText?.replace(/\s+/g, ' ').trim() ?? null,
      hasQrTab: Array.from(document.querySelectorAll('a')).some((a) => /مسح QR|تحضير QR/.test(a.textContent || '')),
      showingText: /Showing\s+\d+\s+to/i.test(document.body.innerText),
      rowCount: document.querySelectorAll('#manual-list tbody tr').length,
    };
  });
}

const browser = await chromium.launch();
const results = {};

for (const [label, viewport, file] of [
  ['desktop', { width: 1280, height: 900 }, '01-manual-table-desktop.png'],
  ['mobile', { width: 390, height: 844 }, '03-manual-table-mobile.png'],
]) {
  const page = await browser.newPage({ viewport });
  await page.goto(`${base}${shotPath}`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#manual-list');
  results[label] = await measure(page);
  await page.screenshot({ path: path.join(__dirname, file), fullPage: false });
  console.log('saved', file);

  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  const pageFile = label === 'desktop' ? '04-manual-table-desktop-pagination.png' : '05-manual-table-mobile-pagination.png';
  await page.screenshot({ path: path.join(__dirname, pageFile), fullPage: false });
  console.log('saved', pageFile);
  await page.close();
}

fs.writeFileSync(path.join(__dirname, 'overflow-check.json'), JSON.stringify(results, null, 2) + '\n');
console.log(JSON.stringify(results, null, 2));

const failed = Object.entries(results).filter(([, m]) => (
  !m.hasMain || m.hasHorizontalOverflow || m.showingText || m.hasQrTab
));
if (failed.length) {
  console.error('UI checks failed:', failed.map(([k]) => k).join(', '));
  process.exitCode = 1;
}

await browser.close();
