Feature: Load WordPress

  Scenario: Verify that WordPress loads with the plugin active
    Given I am on the homepage
    Then I should see "Hello World"
