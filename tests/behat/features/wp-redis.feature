Feature: WP Redis

  Scenario: Redis service should be enabled on Pantheon
    Given I log in as an admin
    And I should not see "The Pantheon Redis service needs to be enabled"

  Scenario: Redis debug should include 'Redis Calls'

    When I am on the homepage
    Then I should not see "Redis Calls:"
    And I should not see "Cache Hits:"
    And I should not see "Cache Misses:"

    When I am on "/?redis_debug"
    Then I should see "Redis Calls:"
    And I should see "Cache Hits:"
    And I should see "Cache Misses:"
    And I should see "- get:"
