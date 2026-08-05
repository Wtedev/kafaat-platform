/**
 * Real UI screenshots for support inbox UX (local only).
 * Usage: SCREENSHOT_TOKEN=... BASE=http://127.0.0.1:8010 node docs/audits/screenshots/support-inbox-ux/capture.mjs
 */
import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = __dirname;
const base = process.env.BASE || 'http://127.0.0.1:8010';
const token = process.env.SCREENSHOT_TOKEN;
if (!token) {
  console.error('SCREENSHOT_TOKEN required');
  process.exit(1);
}

fs.mkdirSync(outDir, { recursive: true });

async function shot(page, name) {
  const file = path.join(outDir, name);
  await page.screenshot({ path: file, fullPage: false });
  console.log('wrote', file);
}

async function login(page, kind) {
  await page.goto(`${base}/__local/screenshot-login/${kind}?token=${encodeURIComponent(token)}`);
  await page.waitForLoadState('networkidle');
}

async function openFabChat(page) {
  const btn = page.locator('[data-support-open]');
  await btn.waitFor({ state: 'visible', timeout: 15000 });
  await btn.click();
  await page.waitForSelector('[data-support-panel]:not([hidden])', { timeout: 10000 });
  // Wait for widget API / chat or create form
  await page.waitForTimeout(1200);
}

const browser = await chromium.launch({
  channel: 'chrome',
  headless: true,
});

try {
  // --- Active ticket widget (desktop) ---
  {
    const context = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      locale: 'ar-SA',
    });
    const page = await context.newPage();
    await login(page, 'beneficiary');
    await openFabChat(page);
    await page.waitForSelector('[data-support-chat]:not([hidden]), [data-support-messages]', { timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(800);
    await shot(page, 'widget-active-ticket-desktop.png');
    await context.close();
  }

  // --- Active ticket widget (mobile) ---
  {
    const context = await browser.newContext({
      viewport: { width: 390, height: 844 },
      deviceScaleFactor: 2,
      isMobile: true,
      hasTouch: true,
      locale: 'ar-SA',
    });
    const page = await context.newPage();
    await login(page, 'beneficiary');
    await openFabChat(page);
    await page.waitForTimeout(800);
    await shot(page, 'widget-active-ticket-mobile.png');
    await context.close();
  }

  // --- Closed ticket widget (desktop) ---
  {
    const context = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      locale: 'ar-SA',
    });
    const page = await context.newPage();
    await login(page, 'beneficiary-closed');
    // Open closed ticket via history show page then FAB new-ticket closed state,
    // or navigate to show then open FAB which should be create mode with closed hint.
    // Force closed conversation view via portal show for visual of closed composer.
    const closedId = process.env.CLOSED_TICKET_ID;
    if (closedId) {
      await page.goto(`${base}/portal/support/${closedId}`);
      await page.waitForLoadState('networkidle');
      await shot(page, 'widget-closed-ticket-desktop.png');
    } else {
      await openFabChat(page);
      await page.waitForTimeout(800);
      await shot(page, 'widget-closed-ticket-desktop.png');
    }
    await context.close();
  }

  // --- Closed ticket widget (mobile) ---
  {
    const context = await browser.newContext({
      viewport: { width: 390, height: 844 },
      deviceScaleFactor: 2,
      isMobile: true,
      hasTouch: true,
      locale: 'ar-SA',
    });
    const page = await context.newPage();
    await login(page, 'beneficiary-closed');
    const closedId = process.env.CLOSED_TICKET_ID;
    if (closedId) {
      await page.goto(`${base}/portal/support/${closedId}`);
      await page.waitForLoadState('networkidle');
    } else {
      await openFabChat(page);
    }
    await page.waitForTimeout(800);
    await shot(page, 'widget-closed-ticket-mobile.png');
    await context.close();
  }

  // --- Admin inbox list + conversation (desktop) ---
  {
    const context = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      locale: 'ar-SA',
    });
    const page = await context.newPage();
    await login(page, 'admin');
    await page.waitForURL(/support-inbox/, { timeout: 20000 });
    await page.waitForSelector('.support-inbox, [wire\\:id]', { timeout: 20000 });
    await page.waitForTimeout(1500);
    await shot(page, 'admin-inbox-list-desktop.png');

    // Select first ticket if present
    const row = page.locator('.support-inbox__row, [data-ticket-id]').first();
    if (await row.count()) {
      await row.click();
      await page.waitForTimeout(1200);
    }
    await shot(page, 'admin-inbox-conversation-desktop.png');
    await context.close();
  }

  // --- Admin inbox (mobile) ---
  {
    const context = await browser.newContext({
      viewport: { width: 390, height: 844 },
      deviceScaleFactor: 2,
      isMobile: true,
      hasTouch: true,
      locale: 'ar-SA',
    });
    const page = await context.newPage();
    await login(page, 'admin');
    await page.waitForURL(/support-inbox/, { timeout: 20000 });
    await page.waitForTimeout(1500);
    await shot(page, 'admin-inbox-list-mobile.png');
    const row = page.locator('.support-inbox__row, [data-ticket-id]').first();
    if (await row.count()) {
      await row.click();
      await page.waitForTimeout(1200);
      await shot(page, 'admin-inbox-conversation-mobile.png');
    }
    await context.close();
  }

  console.log('done');
} finally {
  await browser.close();
}
