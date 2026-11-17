Feature: Load WordPress

  Scenario: Verify that WordPress loads with the plugin active
    Given I am on the homepage
    Then I should see "Hello World"

  Scenario: Verify that a user can update the site's title
    Given I am on the homepage
    Then the ".wp-block-site-title" element should not contain "Pantheon WordPress Site"

    Given I log in as an admin
    When I visit "/wp-admin/options-general.php"
    And I fill in "blogname" with "Pantheon WordPress Site"
    And I press "Save Changes"
    Then I should see "Settings saved."

    When I am on the homepage
    Then the ".wp-block-site-title" element should contain "Pantheon WordPress Site"
