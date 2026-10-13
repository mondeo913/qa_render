import { createRequire } from 'node:module';
import { readFileSync, mkdirSync } from 'node:fs';
import assert from 'node:assert/strict';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const base = process.env.SIGET_UI_URL || 'http://127.0.0.1:8000';
const password = process.env.SIGET_QA_PASSWORD || 'SigetQA_2026_Cambiar!';
const users = JSON.parse(readFileSync('storage/framework/testing/ui-users.json', 'utf8'));
const screenshots = 'storage/framework/testing/ui-screenshots';
mkdirSync(screenshots, { recursive: true });
const browser = await chromium.launch({ headless: true, ...(process.env.SIGET_CHROMIUM_PATH ? { executablePath: process.env.SIGET_CHROMIUM_PATH } : {}) });
let pages = 0;
async function checkPage(page, name) {
  await page.waitForFunction(() => Boolean(window.SigetAppearance));
  await page.waitForTimeout(150);
  const geometry = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth }));
  assert(geometry.scroll <= geometry.width + 2, `${name}: horizontal overflow ${geometry.scroll}/${geometry.width}`);
  assert.equal(await page.locator('script[src*="siget-zenith-runtime.js"]').count(), 1, `${name}: duplicate runtime`);
  assert.equal(await page.locator('link[href*="siget-zenith-runtime.css"]').count(), 0, `${name}: duplicate visual CSS`);
  assert.equal(await page.locator('.siget-main').count(), 1);
  pages++;
}
try {
  for (const user of users) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: 'light' });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => { if (response.status() >= 400 && !response.url().endsWith('favicon.ico')) errors.push(`HTTP ${response.status()}: ${response.url()}`); });
    await page.goto(base + '/iniciar-sesion');
    await page.locator('[name="email"]').fill(user.email);
    await page.locator('[name="password"]').fill(password);
    await Promise.all([page.waitForURL('**/dashboard'), page.locator('button[type="submit"], button.btn-primary').last().click()]);
    await checkPage(page, user.email);
    await page.getByRole('button', { name: 'Elegir tema', exact: true }).click();
    assert(await page.locator('.siget-top-actions .dropdown-menu.show').count() > 0, 'Bootstrap dropdown failed');
    await page.locator('.siget-top-actions [data-theme-value="dark"]').click();
    assert.equal(await page.locator('html').getAttribute('data-bs-theme'), 'dark');
    await page.getByRole('button', { name: 'Personalizar apariencia', exact: true }).click();
    assert.equal(await page.locator('#siget-customizer').getAttribute('aria-hidden'), 'false');
    for (const [kind, value] of [['preset', 'ocean'], ['density', 'comfortable'], ['container', 'boxed'], ['radius', 'soft'], ['layout', 'topnav']]) {
      await page.locator(`#siget-customizer [data-zenith-${kind}="${value}"]`).click();
      assert.equal(await page.locator('html').getAttribute(`data-zenith-${kind}`), value);
    }
    assert.equal(await page.locator('.siget-main').evaluate(el => getComputedStyle(el).marginLeft), '0px');
    await page.locator('#siget-customizer [data-zenith-layout="sidebar"]').click();
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#siget-customizer').getAttribute('aria-hidden'), 'true');
    await page.reload();
    assert.equal(await page.locator('html').getAttribute('data-bs-theme'), 'dark', 'Theme did not persist');
    assert.equal(await page.locator('html').getAttribute('data-zenith-preset'), 'ocean', 'Color did not persist');
    await page.getByRole('button', { name: 'Alternar menú de navegación' }).click();
    assert.equal(await page.locator('.siget-sidebar').evaluate(el => el.getBoundingClientRect().width), 72, 'Sidebar collapse failed');
    await page.getByRole('button', { name: 'Alternar menú de navegación' }).click();
    await page.screenshot({ path: `${screenshots}/${user.id}-dashboard-dark.png`, fullPage: true });
    // Every menu entry is a GET page already authorized by this user's role.
    const links = await page.locator('.siget-nav a').evaluateAll(elements => elements.map(el => el.href));
    for (const url of links) {
      const response = await page.goto(url);
      assert.equal(response.status(), 200, `${user.email}: menu route ${url}`);
      await checkPage(page, `${user.email}: ${url}`);
      // Real Bootstrap collapse controls, where present, must open and close.
      const collapse = page.locator('[data-bs-toggle="collapse"]').first();
      if (await collapse.count() && await collapse.isVisible()) {
        const selector = await collapse.getAttribute('data-bs-target') || await collapse.getAttribute('href');
        if (selector?.startsWith('#')) {
          const before = await page.locator(selector).evaluate(el => el.classList.contains('show'));
          await collapse.click();
          await page.waitForTimeout(500);
          assert.notEqual(await page.locator(selector).evaluate(el => el.classList.contains('show')), before, 'Bootstrap collapse failed');
        }
      }
    }
    await page.goto(base + '/repositorio');
    await page.locator('[data-file-view="list"]').click();
    assert(await page.locator('[data-file-manager]').evaluate(el => el.classList.contains('siget-file-list-view')), 'Repository list failed');
    await page.locator('[data-file-view="grid"]').click();
    await page.screenshot({ path: `${screenshots}/${user.id}-repository-dark.png`, fullPage: true });
    await page.goto(base + '/tablero-cargas');
    await page.screenshot({ path: `${screenshots}/${user.id}-board-dark.png`, fullPage: true });
    for (const width of [768, 390]) {
      await page.setViewportSize({ width, height: 844 });
      for (const route of ['/dashboard', '/repositorio', '/tablero-cargas']) {
        await page.goto(base + route);
        await checkPage(page, `${user.email}: ${width} ${route}`);
        await page.getByRole('button', { name: 'Alternar menú de navegación' }).click();
        assert.equal(await page.locator('.siget-sidebar').evaluate(el => Math.round(el.getBoundingClientRect().left)), 0, 'Mobile navigation failed');
        await page.keyboard.press('Escape');
        assert(await page.locator('.siget-sidebar').evaluate(el => el.getBoundingClientRect().right <= 1), 'Mobile navigation did not close');
      }
    }
    await page.screenshot({ path: `${screenshots}/${user.id}-mobile.png`, fullPage: true });
    assert.deepEqual(errors, [], `${user.email}: browser errors`);
    console.log(`UI PASS ${user.role} ${user.email}`);
    await context.close();
  }
  console.log(`UI ACCEPTANCE: ${users.length} accounts, ${pages} pages, desktop/tablet/mobile, no browser errors.`);
} finally { await browser.close(); }
