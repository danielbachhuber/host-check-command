Feature: Check whether a WordPress install is still hosted here

  @broken
  Scenario: 'hosted-valid-login' status when WordPress is functional
    Given a WP install
    And I run `wp option update home http://localhost:8181`
    And I run `wp option update siteurl http://localhost:8181`
    And I launch in the background `wp server --host=localhost --port=8181`

    When I run `wp host-check --path=./`
    Then STDOUT should contain:
      """
      Yes: WordPress install is hosted here (HTTP code 200)
      """
    And STDOUT should contain:
      """
      Yes: wp-login loads as expected (HTTP code 200)
      """
    And STDOUT should contain:
      """
      Summary: ./, hosted-valid-login
      """

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

  @broken
  Scenario: 'hosted-broken-login' status when WordPress has a broken login
    Given a WP install
    And I run `wp option update home http://localhost:8181`
    And I run `wp option update siteurl http://localhost:8181`
    And I launch in the background `wp server --host=localhost --port=8181`
    And I run `echo "<?php" > wp-login.php`

    When I run `wp host-check --path=./`
    Then STDOUT should contain:
      """
      Yes: WordPress install is hosted here (HTTP code 200)
      """
    And STDOUT should contain:
      """
      No: wp-login is missing name="log" (HTTP code 200)
      """
    And STDOUT should contain:
      """
      Summary: ./, hosted-broken-login
      """

  @broken
  Scenario: 'hosted-maintenance' status when WordPress is in maintenance mode
    Given a WP install
    And I run `wp option update home http://localhost:8181`
    And I run `wp option update siteurl http://localhost:8181`
    And I launch in the background `wp server --host=localhost --port=8181`
    And a .maintenance file:
      """
      <?php
      $upgrading = time();
      """

    When I run `wp host-check --path=./`
    Then STDOUT should contain:
      """
      Yes: WordPress install is hosted here (HTTP code 200)
      """
    And STDOUT should contain:
      """
      No: WordPress is in maintenance mode (HTTP code 503)
      """
    And STDOUT should contain:
      """
      Summary: ./, hosted-maintenance
      """

  @broken
  Scenario: 'hosted-php-fatal' status when WordPress has a fatal error
    Given a WP install
    And I run `wp option update home http://localhost:8181`
    And I run `wp option update siteurl http://localhost:8181`
    And "define( 'DB_NAME', 'wp_cli_test' );" replaced with "define( 'DB_NAME', 'wp_cli_test' );define('WP_DEBUG', true);" in the wp-config.php file
    And I launch in the background `wp server --host=localhost --port=8181`
    And a wp-content/mu-plugins/local.php file:
      """
      <?php
      foobarmissingfunc();
      """

    When I run `wp host-check --path=./`
    Then STDOUT should contain:
      """
      Yes: WordPress install is hosted here (HTTP code 200)
      """
    And STDOUT should contain:
      """
      No: WordPress has a PHP fatal error (HTTP code 200)
      """
    And STDOUT should contain:
      """
      Summary: ./, hosted-php-fatal
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
