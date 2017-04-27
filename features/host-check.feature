Feature: Check whether a WordPress install is still hosted here

  Scenario: 'no-wp-exists' status when WordPress doesn't exist
    Given an empty directory

    When I run `wp host-check --path=./`
    Then STDOUT should contain:
      """
      Summary: ./, no-wp-exists
      """

  Scenario: 'no-wp-config' status when WordPress is missing its wp-config.php
    Given a WP install
    And I run `rm wp-config.php`

    When I run `wp host-check --path=./`
    Then STDOUT should contain:
      """
      Summary: ./, no-wp-config
      """

  Scenario: 'error-db-connect' status when invalid database password
    Given a WP install
    And "password1" replaced with "password2" in the wp-config.php file

    When I run `wp host-check --path=./`
    Then STDOUT should contain:
      """
      Summary: ./, error-db-connect
      """

  Scenario: 'error-db-select' status when invalid database name
    Given a WP install
    And "define( 'DB_NAME', 'wp_cli_test' );" replaced with "define( 'DB_NAME', 'wp_cli_test2' );" in the wp-config.php file

    When I run `wp host-check --path=./`
    Then STDOUT should contain:
      """
      Summary: ./, error-db-select
      """

  Scenario: 'missing-200' status when performing the file check
    Given a WP install

    When I run `wp host-check --path=./`
    Then STDOUT should contain:
      """
      Missing: WordPress install isn't hosted here (HTTP code 404)
      """
    And STDOUT should contain:
      """
      Summary: ./, missing-404
      """

  Scenario: host check shouldn't create wp-content/uploads if it doesn't exist
    Given a WP install
    And I run `rm -rf wp-content/uploads`

    When I try `wp host-check --path=./`
    Then STDERR should contain:
      """
      Couldn't write test file to path:
      """
    And the wp-content/uploads directory should not exist
