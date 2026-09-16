import { createBdd } from 'playwright-bdd';
import { test as cmsBddTest, expect } from 'cms-bdd';
const { Given, When, Then } = createBdd(cmsBddTest);

// The admin session comes from storageState, set up once by the setup project.
// This step documents the precondition in Gherkin; it has nothing to do.
Given('I log in as an admin', async () => {});

Given('I am on the homepage', async ({ page }) => {
  await page.goto('/', { waitUntil: 'load' });
});

When('I go to the homepage', async ({ page }) => {
  await page.goto('/', { waitUntil: 'load' });
});

Given('I am on {string}', async ({ page }, url: string) => {
  await page.goto(url, { waitUntil: 'load' });
});

Given('I go to {string}', async ({ page }, url: string) => {
  await page.goto(url, { waitUntil: 'load' });
});

When('I fill in {string} with {string}', async ({ page }, fieldName: string, value: string) => {
  await page.locator(`[name="${fieldName}"]`).fill(value);
});

When('I submit the {string} form', async ({ page }, selector: string) => {
  await page.locator(selector).evaluate((form: HTMLFormElement) => form.submit());
  await page.waitForLoadState('load');
});

When('I wait {string} second(s)', async ({ page }, seconds: string) => {
  await page.waitForTimeout(parseInt(seconds, 10) * 1000);
});

Then('I should see {string}', async ({ page }, text: string) => {
  await expect(page.locator('body')).toContainText(text);
});

Then('I should not see {string}', async ({ page }, text: string) => {
  await expect(page.locator('body')).not.toContainText(text);
});

// Behat's Mink step of the same name checks the element's HTML, not its
// rendered text (its own docs example asserts on a style="..." attribute).
Then('the {string} element should contain {string}', async ({ page }, selector: string, text: string) => {
  const html = await page.locator(selector).innerHTML();
  expect(html).toContain(text);
});

Then('the {string} element should not contain {string}', async ({ page }, selector: string, text: string) => {
  const html = await page.locator(selector).innerHTML();
  expect(html).not.toContain(text);
});
