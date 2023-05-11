Feature: WP Redis CLI Commands

  Background:
    Given a WP install

  Scenario: Activate a plugin that's already installed
    When I run `wp plugin activate wp-redis`
    Then STDOUT should be:
      """
      Plugin 'wp-redis' activated.
      Success: Activated 1 of 1 plugins.
      """
    And the return code should be 0