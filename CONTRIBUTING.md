## Contributing ##

The best way to contribute to the development of this plugin is by participating on the GitHub project:

https://github.com/pantheon-systems/wp-redis

Pull requests and issues are welcome!

## Workflow

The `develop` branch is the development branch which means it contains the next version to be released. `master` contains the corresponding stable development version. Always work on the `develop` branch and open up PRs against `develop`.

## Testing

You may notice there are two sets of tests running, on two different services:

* The [PHPUnit](https://phpunit.de/) test suite.
* The [Behat](http://behat.org/) test suite runs against a Pantheon site, to ensure the plugin's compatibility with the Pantheon platform.

Both of these test suites can be run locally, with a varying amount of setup.

PHPUnit requires the [WordPress PHPUnit test suite](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/), and access to a database with name `wordpress_test`. If you haven't already configured the test suite locally, you can run `bash bin/install-wp-tests.sh wordpress_test root '' localhost`. You'll also need to enable Redis and the PHPRedis extension in order to run the test suite against Redis.

Behat requires a Pantheon site with Redis enabled. Once you've created the site, you'll need [install Terminus](https://github.com/pantheon-systems/terminus#installation), and set the `TERMINUS_TOKEN`, `TERMINUS_SITE`, and `TERMINUS_ENV` environment variables. Then, you can run `./bin/behat-prepare.sh` to prepare the site for the test suite.

## Release Process

1. Starting from `develop`, cut a release branch named `release_X.Y.Z` containing your changes.
1. Update plugin version in `package.json`, `README.md`, `readme.txt`, and `wp-redis.php`.
1. Update the Changelog with the latest changes.
1. Create a PR against the `master` branch.
1. After all tests pass and you have received approval from a CODEOWNER (including resolving any merge conflicts), merge the PR into `master`.
1. Pull `master` locally, create a new tag, and push up.
1. Confirm that the necessary assets are present in the newly created tag, and test on a WP install if desired.
1. Create a [new release](https://github.com/pantheon-systems/wp-redis/releases/new) using the tag created in the previous steps, naming the release with the new version number, and targeting the tag created in the previous step. Paste the release changelog from the `Changelog` section of the `README` into the body of the release and include a link to the closed issues if applicable.
1. Wait for the [_Release wp-redis plugin to wp.org_ action](https://github.com/pantheon-systems/wp-redis/actions/workflows/wordpress-plugin-deploy.yml) to finish deploying to the WordPress.org repository. If all goes well, users with SVN commit access for that plugin will receive an emailed diff of changes.
1. Check WordPress.org: Ensure that the changes are live on https://wordpress.org/plugins/wp-redis/. This may take a few minutes.