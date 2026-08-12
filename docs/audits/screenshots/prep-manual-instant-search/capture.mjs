import { chromium } from '../prep-attendance-portal-tabs/node_modules/playwright/index.mjs';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const base = process.env.BASE || 'http://127.0.0.1:8765';

async function measure(page) {
  return page.evaluate(() => {
    const root = document.documentElement;
    const container = document.querySelector('main > div.mx-auto');
    const table = document.querySelector('#manual-list');
    const nav = document.querySelector('nav[aria-label="التنقل بين الصفحات"]');
    const cs = container ? getComputedStyle(container) : null;
    return {
      viewportWidth: window.innerWidth,
      hasHorizontalOverflow: root.scrollWidth > root.clientWidth + 1,
      hasMain: !!document.querySelector('main'),
      containerWidth: cs?.width ?? null,
      tableClientWidth: table?.clientWidth ?? null,
      paginationText: nav?.innerText?.replace(/\s+/g, ' ').trim() ?? null,
      hasQrTab: Array.from(document.querySelectorAll('a')).some((a) => /مسح QR|تحضير QR/.test(a.textContent || '')),
      hasStatusColumn: Array.from(document.querySelectorAll('th')).some((th) => th.textContent?.trim() === 'الحالة'),
      hasAbsentButton: Array.from(document.querySelectorAll('button')).some((b) => b.textContent?.trim() === 'لم يحضر'),
      hasSearchSubmit: Array.from(document.querySelectorAll('button')).some((b) => b.textContent?.trim() === 'بحث'),
      prepButtons: Array.from(document.querySelectorAll('.prep-mark')).map((b) => b.textContent.trim()),
      showingText: /Showing\s+\d+\s+to/i.test(document.body.innerText),
      rowCount: document.querySelectorAll('#manual-list tbody tr').length,
    };
  });
}

const browser = await chromium.launch();
const results = {};

async function shot(label, viewport, urlPath, files) {
  const page = await browser.newPage({ viewport });
  await page.goto(`${base}${urlPath}`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#manual-list');
  results[label] = await measure(page);
  await page.screenshot({ path: path.join(__dirname, files.top), fullPage: false });
  if (files.bottom) {
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.screenshot({ path: path.join(__dirname, files.bottom), fullPage: false });
  }
  await page.close();
}

await shot('desktop', { width: 1280, height: 900 }, '/__prep-portal-shot.html', {
  top: '01-manual-desktop.png',
  bottom: '02-manual-desktop-pagination.png',
});
await shot('mobile', { width: 390, height: 844 }, '/__prep-portal-shot.html', {
  top: '03-manual-mobile.png',
  bottom: '04-manual-mobile-pagination.png',
});
await shot('desktop-search', { width: 1280, height: 900 }, '/__prep-portal-search.html', {
  top: '05-manual-desktop-search.png',
});
await shot('mobile-search', { width: 390, height: 844 }, '/__prep-portal-search.html', {
  top: '06-manual-mobile-search.png',
});

fs.writeFileSync(path.join(__dirname, 'overflow-check.json'), JSON.stringify(results, null, 2) + '\n');
console.log(JSON.stringify(results, null, 2));

const failed = Object.entries(results).filter(([, m]) => (
  !m.hasMain || m.hasHorizontalOverflow || m.showingText || m.hasQrTab || m.hasStatusColumn || m.hasAbsentButton || m.hasSearchSubmit
));
if (failed.length) {
  console.error('UI checks failed:', failed.map(([k]) => k).join(', '));
  process.exitCode = 1;
}

await browser.close();
