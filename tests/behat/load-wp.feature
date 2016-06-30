Feature: Load WordPress

  Scenario: Verify that WordPress loads with the plugin active
    Given I am on the homepage
    Then I should see "Hello World"

  Scenario: Verify that a user can publish a blog post
    Given I am on "wp-login.php"
    Then print current URL

    When I fill in "log" with "pantheon"
    And I fill in "pwd" with "pantheon"
    And I press "wp-submit"
    Then print current URL
    And I should be on "/wp-admin/"

    When I go to "/wp-admin/post-new.php"
    And I fill in "post_title" with "Awesome Post"
    And I press "publish"
    Then I should see "Post published."
