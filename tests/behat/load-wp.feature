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

  Scenario: Verify that a user can update the site's title
    When I go to the homepage
    Then print current URL
    And the "#masthead" element should not contain "Pantheon WordPress Site"

    When I go to "wp-login.php"
    Then print current URL

    When I fill in "log" with "pantheon"
    And I fill in "pwd" with "pantheon"
    And I press "wp-submit"
    Then print current URL
    And I should be on "/wp-admin/"

    When I go to "/wp-admin/options-general.php"
    And I fill in "blogname" with "Pantheon WordPress Site"
    And I press "submit"
    Then print current URL
    And I should see "Settings saved."

    When I go to the homepage
    Then the "#masthead" element should contain "Pantheon WordPress Site"
