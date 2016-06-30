Feature: WP Redis

  Scenario: Redis service should be enabled on Pantheon
    Given I am on "wp-login.php"
    Then print current URL

    When I fill in "log" with "pantheon"
    And I fill in "pwd" with "pantheon"
    And I press "wp-submit"
    Then print current URL
    And I should be on "/wp-admin/"
    And I should not see "The Pantheon Redis service needs to be enabled"

  Scenario: Redis debug should include 'Redis calls'

    When I am on the homepage
    Then I should not see "Redis Calls:"
    And I should not see "Cache Hits:"
    And I should not see "Cache Misses:"

    When I am on "/?redis_debug"
    Then I should see "Redis Calls:"
    And I should see "Cache Hits:"
    And I should see "Cache Misses:"
