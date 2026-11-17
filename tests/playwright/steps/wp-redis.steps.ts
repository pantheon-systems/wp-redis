import { createBdd } from 'playwright-bdd';
import { test } from 'cms-bdd';

const { Given } = createBdd(test);

// The admin session comes from storageState, set up once by the setup project.
// This step documents the precondition in Gherkin; it has nothing to do.
Given('I log in as an admin', async () => {});
