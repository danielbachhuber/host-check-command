Feature: Check whether a WordPress install is still hosted here

  Scenario: host check shouldn't create wp-content/uploads if it doesn't exist
    Given a WP install
    And I run `rm -rf wp-content/uploads`

    When I try `wp host-check --path=./`
    Then STDERR should contain:
      """
      Couldn't write test file to path:
      """
    And the wp-content/uploads directory should not exist
