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
