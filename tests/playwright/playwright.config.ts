import 'dotenv/config';
import { defineConfig, devices } from '@playwright/test';
import { defineBddConfig } from 'playwright-bdd';
import { AUTH_FILE } from './constants';

if (!process.env.WP_URL) {
  throw new Error('WP_URL is not set. In CI bin/e2e-prepare.sh writes it; locally, set it in tests/playwright/.env.');
}

const testDir = defineBddConfig({
  featuresRoot: '.',
  features: 'features/**/*.feature',
  steps: [
    'steps/**/*.ts',
    'node_modules/cms-bdd/dist/fixtures/customFixtures.js',
  ],
});

const headless = process.env.HEADLESS !== 'false';
const slowMo = parseInt(process.env.SLOW_MO || '0', 10);

export default defineConfig({
  testDir,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [
    ['html'],
    ['list'],
  ],
  timeout: 120000,
  use: {
    baseURL: process.env.WP_URL,
    headless,
    launchOptions: {
      slowMo,
    },
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 10000,
    navigationTimeout: 30000,
  },
  projects: [
    {
      name: 'setup',
      testDir: __dirname,
      testMatch: /auth\.setup\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        channel: 'chrome',
        viewport: { width: 1920, height: 1080 },
      },
    },
    {
      name: 'chrome',
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        channel: 'chrome',
        viewport: { width: 1920, height: 1080 },
        storageState: AUTH_FILE,
      },
    },
  ],
});
