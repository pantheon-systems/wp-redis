## Contributing ##

The best way to contribute to the development of this plugin is by participating on the GitHub project:

https://github.com/pantheon-systems/wp-redis

Pull requests and issues are welcome!

## Workflow

Development and releases are structured around two branches, `main` and `release`. The `main` branch is the default branch for the repository, and is the source and destination for feature branches.

We prefer to squash commits (i.e. avoid merge PRs) from a feature branch into `main` when merging, and to include the PR # in the commit message. PRs to `main` should also include any relevant updates to the changelog in readme.txt. For example, if a feature constitutes a minor or major version bump, that version update should be discussed and made as part of approving and merging the feature into `main`.

`main` should be stable and usable, though possibly a few commits ahead of the public release on wp.org.

The `release` branch matches the latest stable release deployed to [wp.org](https://wordpress.org/).

## Testing

You may notice there are two sets of tests running, on two different services:

* The [PHPUnit](https://phpunit.de/) test suite.
* The [Behat](http://behat.org/) test suite runs against a Pantheon site, to ensure the plugin's compatibility with the Pantheon platform.

Both of these test suites can be run locally, with a varying amount of setup.

PHPUnit requires the [WordPress PHPUnit test suite](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/), and access to a database with name `wordpress_test`. If you haven't already configured the test suite locally, you can run `bash bin/install-wp-tests.sh wordpress_test root '' localhost`. You'll also need to enable Redis and the PHPRedis extension in order to run the test suite against Redis.

The behat tests require a Pantheon site with Redis enabled. Once you've created the site, you'll need [install Terminus](https://github.com/pantheon-systems/terminus#installation), and set the `TERMINUS_TOKEN`, `TERMINUS_SITE`, and `TERMINUS_ENV` environment variables. Then, you can run `./bin/behat-prepare.sh` to prepare the site for the test suite.

## Release Process

1. Merge your feature branch into `main` with a PR. This PR should include any necessary updates to the changelog in readme.txt and README.md. Features should be squash merged. 
1. From main, checkout a new branch `release_X.Y.Z`.
1. Make a release commit: 
    * In `package.json`, `README.md`, `readme.txt`, and `wp-redis.php`, remove the `-dev`  from the version number. 
    * For the README files, the version number must be updated both at the top of the document as well as the changelog. 
    * Add the date to the  `** X.Y.Z **` heading in the changelogs in `README.md`, `readme.txt`, and any other appropriate location. 
    * Commit these changes with the message `Release X.Y.Z`
    * Push the release branch up.
1. Open a Pull Request to merge `release_X.Y.Z` into `release`. Your PR should consist of all commits to `main` since the last release, and one commit to update the version number. The PR name should also be `Release X.Y.Z`.
1. After all tests pass and you have received approval from a CODEOWNER (including resolving any merge conflicts), merge the PR into `release`. Use a "merge" commit, do no not rebase or squash. If the GitHub UI doesn't offer a "Merge commit" option (only showing "Squash and merge" or "Rebase and merge"), merge from the terminal instead:
    `git checkout release`
    `git merge --no-ff release_X.Y.Z`
    `git push origin release`
1. After merging to the `release` branch, a draft Release will be automatically created by the [build-tag-release workflow] (https://github.com/pantheon-systems/wp-redis/actions/workflows/build-tag-release.yml). This draft release will be automatically pre-filled with release notes. 
1. Confirm that the necessary assets are present in the newly created tag, and test on a WP install if desired. 
1. Review the release notes, making any necessary changes, and publish the release. 
1. Wait for the [Release pantheon-hud plugin to wp.org action](https://github.com/pantheon-systems/wp-redis/actions/workflows/wordpress-plugin-deploy.yml) to finish deploying to the WordPress.org plugin repository.
1. If all goes well, users with SVN commit access for that plugin will receive an email with a diff of the changes. 
1. Check WordPress.org: Ensure that the changes are live on the plugin repository. This may take a few minutes.
1. Following the release, prepare the next dev version with the following steps:
    * `git checkout release`
    * `git pull origin release`
    * `git checkout main`
    * `git rebase release`
    * Update the version number in all locations, incrementing the version by one patch version, and add the `-dev` flag (e.g. after releasing `1.2.3`, the new verison will be `1.2.4-dev`)
    * Add a new `** X.Y.Z-dev **` heading to the changelog
    * `git add -A .`
    * `git commit -m "Prepare X.Y.Z-dev"`
    * `git checkout -b release-XYZ-dev`
    * `git push origin release-XYZ-dev`
    * Create a pull request on GitHub UI from `release-XYZ-dev` to `main` to trigger all required status checks
    * _Wait for all required status checks to pass in CI. Once all tests pass, push to main from the terminal:_
    * `git checkout main && git push origin main`
    * _Note: While main is typically protected, having an open PR with passing tests allows direct push to main, which is the preferred method here._
