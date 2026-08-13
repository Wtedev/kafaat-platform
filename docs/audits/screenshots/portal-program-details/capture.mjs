import { chromium } from '../prep-attendance-portal-tabs/node_modules/playwright/index.mjs';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const base = process.env.BASE || 'http://127.0.0.1:8766';

const shots = [
  ['inperson-running', '01-inperson-running'],
  ['remote-running', '02-remote-running'],
  ['hybrid-running', '03-hybrid-running'],
  ['not-started', '04-not-started'],
  ['completed', '05-completed'],
];

const browser = await chromium.launch();

async function capture(name, prefix, viewport, suffix) {
  const page = await browser.newPage({ viewport });
  await page.goto(`${base}/${name}.html`, { waitUntil: 'networkidle' });
  await page.waitForSelector('h1');
  await page.screenshot({ path: path.join(__dirname, `${prefix}-${suffix}.png`), fullPage: true });
  await page.close();
}

for (const [name, prefix] of shots) {
  await capture(name, prefix, { width: 1280, height: 900 }, 'desktop');
  await capture(name, prefix, { width: 390, height: 844 }, 'mobile');
}

fs.writeFileSync(path.join(__dirname, 'overflow-check.json'), JSON.stringify({
  generated_at: new Date().toISOString(),
  files: shots.flatMap(([, prefix]) => [`${prefix}-desktop.png`, `${prefix}-mobile.png`]),
}, null, 2) + '\n');

await browser.close();
console.log('captured 10 screenshots');
