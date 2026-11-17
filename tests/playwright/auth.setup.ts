import { test as setup, expect } from '@playwright/test';
import { WPLoginPage } from 'cms-bdd';
import { AUTH_FILE } from './constants';

setup('authenticate as admin', async ({ page }) => {
  const loginPage = new WPLoginPage(page);
  await loginPage.login();
  expect(await loginPage.isLoggedIn(), 'admin login failed').toBe(true);
  await page.context().storageState({ path: AUTH_FILE });
});
