import { chromium } from '../prep-attendance-portal-tabs/node_modules/playwright/index.mjs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const base = process.env.BASE || 'http://127.0.0.1:8767';
const shots = [
  '01-before-open',
  '02-countdown',
  '03-after-checkin',
  '04-after-end',
];

const browser = await chromium.launch();
for (const name of shots) {
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.goto(`${base}/${name}.html`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#gate-live-session');
  await page.locator('#gate-live-session').scrollIntoViewIfNeeded();
  await page.screenshot({
    path: path.join(__dirname, `${name}-desktop.png`),
    fullPage: false,
  });
  await page.close();

  const mobile = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await mobile.goto(`${base}/${name}.html`, { waitUntil: 'networkidle' });
  await mobile.waitForSelector('#gate-live-session');
  await mobile.locator('#gate-live-session').scrollIntoViewIfNeeded();
  await mobile.screenshot({
    path: path.join(__dirname, `${name}-mobile.png`),
    fullPage: false,
  });
  await mobile.close();
}
await browser.close();
console.log('captured 8 screenshots');
