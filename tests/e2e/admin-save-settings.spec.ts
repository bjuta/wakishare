import { test, expect } from '@playwright/test';

const WP_BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';
const ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.WP_ADMIN_PASS || 'pass';
const PLUGIN_PAGE = process.env.PLUGIN_PAGE || 'waki-share';

test('Save Settings redirects back to plugin page and preserves tab', async ({ page }) => {
  // 1. Login
  await page.goto(`${WP_BASE_URL}/wp-login.php`);
  await page.fill('#user_login', ADMIN_USER);
  await page.fill('#user_pass', ADMIN_PASS);
  await page.click('#wp-submit');
  await page.waitForURL(/\/wp-admin\//);

  // 2. Visit plugin settings page
  await page.goto(`${WP_BASE_URL}/wp-admin/options-general.php?page=${PLUGIN_PAGE}`);

  // 3. Switch to a tab if tabs exist
  let chosenTab: string | null = null;
  const tabLink = page.locator('a[role="tab"], .nav-tab[href*="tab="], a[href*="&tab="]').first();
  if (await tabLink.count()) {
    const href = await tabLink.getAttribute('href');
    if (href) {
      const match = href.match(/[?&]tab=([^&]+)/);
      if (match) chosenTab = match[1];
    }
    await tabLink.click();
    await page.waitForLoadState('networkidle');
  }

  // 4. Click Save
  const saveBtn = page.locator(
    'button[type="submit"], input[type="submit"][value="Save Changes"], .button-primary'
  );
  await saveBtn.first().click({ force: true });

  // 5. Assert URL is correct
  await page.waitForURL(new RegExp(`/wp-admin/options-(general|writing|reading)\\.php\\?[^#]*page=${PLUGIN_PAGE}.*`), { timeout: 15000 });
  const url = page.url();
  expect(url).toContain(`page=${PLUGIN_PAGE}`);
  expect(url).toContain('settings-updated=true');
  if (chosenTab) {
    expect(url).toContain(`tab=${chosenTab}`);
  }

  // 6. Assert WP success notice is visible
  const notice = page.locator('.notice-success, .updated');
  await expect(notice).toBeVisible();
});
