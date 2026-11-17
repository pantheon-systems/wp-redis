# End-to-end tests

Playwright BDD tests built on [cms-bdd](https://github.com/pantheon-systems/cms-bdd). They run
against a Pantheon multidev with the plugin and Redis enabled, not a local WordPress.

| Path | What it holds |
|---|---|
| `features/` | Gherkin scenarios |
| `steps/` | WP Redis-specific step definitions. Generic browser steps come from cms-bdd |
| `auth.setup.ts` | Logs in once and saves the session |

cms-bdd's steps are loaded from `node_modules/cms-bdd/dist/steps` by `playwright.config.ts`. Define a
step here only if cms-bdd lacks it, and never with the same text as a shared step: playwright-bdd
fails on duplicates.

## Running it

```bash
cp .env.example .env   # fill in WP_URL, WP_USER, WP_PASSWORD
npm ci
npx playwright install --with-deps chrome
npm test
```

`npx playwright show-report` opens the last run.

In CI, `bin/e2e-prepare.sh` creates the multidev, deploys the plugin and the `redis-debug.php`
mu-plugin, and exports `WP_URL`; `bin/e2e-cleanup.sh` deletes it.

## Updating cms-bdd

`package.json` pins an exact commit and Dependabot does not raise PRs for git dependencies, so
this is manual:

```bash
npm install "cms-bdd@git+https://github.com/pantheon-systems/cms-bdd.git#<sha>"
```

Use that explicit URL, not the `github:owner/repo` shorthand: npm rewrites the shorthand to
`git+ssh://` in the lockfile, which makes every install authenticate over SSH.

Commit `package.json` and `package-lock.json` together. Keep `@playwright/test`, `playwright`
and `playwright-bdd` on their exact pinned versions and move them together; they are peer
dependencies of cms-bdd, so they are declared here.
