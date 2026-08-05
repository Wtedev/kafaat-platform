import { chromium } from 'playwright';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const base = process.env.BASE || 'http://127.0.0.1:8010';
const token = process.env.SCREENSHOT_TOKEN;

async function dismissPrefs(page) {
  const decline = page.getByRole('button', { name: /لا شكرا|داخل المنصة فقط/ });
  if (await decline.count()) {
    await decline.first().click({ timeout: 5000 }).catch(() => {});
    await page.waitForTimeout(800);
  }
  // Also try text click
  const alt = page.locator('button, a').filter({ hasText: 'داخل المنصة فقط' });
  if (await alt.count()) {
    await alt.first().click({ timeout: 3000 }).catch(() => {});
    await page.waitForTimeout(500);
  }
}

const browser = await chromium.launch({ channel: 'chrome', headless: true });

{
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'ar-SA' });
  const page = await context.newPage();
  await page.goto(`${base}/__local/screenshot-login/admin?token=${encodeURIComponent(token)}`);
  await page.waitForURL(/support-inbox/, { timeout: 20000 });
  await page.waitForTimeout(1500);
  await dismissPrefs(page);
  await page.waitForTimeout(1000);
  await page.screenshot({ path: path.join(__dirname, 'admin-inbox-list-desktop.png'), fullPage: false });
  console.log('list desktop');

  // Click first ticket row
  const row = page.locator('.support-inbox__ticket, .support-inbox__row, [wire\\:click*="selectTicket"]').first();
  if (await row.count()) {
    await row.click();
  } else {
    // fallback: any list button containing ST-
    const byText = page.locator('button, a, div').filter({ hasText: 'ST-' }).first();
    if (await byText.count()) await byText.click();
  }
  await page.waitForTimeout(1500);
  await page.screenshot({ path: path.join(__dirname, 'admin-inbox-conversation-desktop.png'), fullPage: false });
  console.log('conversation desktop');
  await context.close();
}

{
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    deviceScaleFactor: 2,
    isMobile: true,
    hasTouch: true,
    locale: 'ar-SA',
  });
  const page = await context.newPage();
  await page.goto(`${base}/__local/screenshot-login/admin?token=${encodeURIComponent(token)}`);
  await page.waitForURL(/support-inbox/, { timeout: 20000 });
  await page.waitForTimeout(1500);
  await dismissPrefs(page);
  await page.waitForTimeout(800);
  await page.screenshot({ path: path.join(__dirname, 'admin-inbox-list-mobile.png'), fullPage: false });
  console.log('list mobile');
  const row = page.locator('.support-inbox__ticket, .support-inbox__row, [wire\\:click*="selectTicket"]').first();
  if (await row.count()) {
    await row.click();
    await page.waitForTimeout(1200);
    await page.screenshot({ path: path.join(__dirname, 'admin-inbox-conversation-mobile.png'), fullPage: false });
    console.log('conversation mobile');
  }
  await context.close();
}

// Closed FAB widget (create mode after closed ticket user)
{
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'ar-SA' });
  const page = await context.newPage();
  await page.goto(`${base}/__local/screenshot-login/beneficiary-closed?token=${encodeURIComponent(token)}`);
  await page.waitForLoadState('networkidle');
  await page.locator('[data-support-open]').click();
  await page.waitForSelector('[data-support-panel]:not([hidden])');
  await page.waitForTimeout(1200);
  // If create form, also open closed ticket via show to capture closed composer in FAB:
  // Navigate to closed ticket then open FAB — state is create; instead inject closed view by visiting show then open FAB after selecting history.
  // Better: fetch closed ticket in widget by opening show page closed CTA area already captured.
  // Capture FAB create-after-closed:
  await page.screenshot({ path: path.join(__dirname, 'widget-closed-fab-create-desktop.png'), fullPage: false });
  console.log('closed fab create');
  await context.close();
}

await browser.close();
console.log('done');
