danielbachhuber/host-check-command
==================================

Checks hosting status for WordPress installation.

[![Build Status](https://travis-ci.org/danielbachhuber/host-check-command.svg?branch=master)](https://travis-ci.org/danielbachhuber/host-check-command)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

~~~
wp host-check --path=<path>
~~~

Loads the WordPress installation to verify that it's still hosted on this server.

First, it verifies the WordPress installation loads. Next, it makes a HTTP
request to determine: 1) whether the installation is still on the server,
and 2) whether the installation loads as expected.

Potential statuses include:

* no-wp-exists - WordPress doesn't exist at the path.
* no-wp-config - No wp-config.php file was found for the installation.
* error-db-connect - Couldn't connect to the database using defined credentials.
* error-db-select - Connected to the database but couldn't select specific database.
* missing-<http-code> - WordPress installation isn't on the server.
* hosted-maintenance - WordPress installation is hosted but renders maintenance page.
* hosted-php-fatal - WordPress installation is hosted but has a PHP fatal.
* hosted-broken-wp-login - WordPress installation is hosted but the login page is broken.
* hosted-valid-login - WordPress installation is hosted on server and login page loads.

Disables WP cron to prevent 'wp_version_check' from being run.

**OPTIONS**

	--path=<path>
		Path to the WordPress installation.

**EXAMPLES**

    # Site loads successfully and is hosted on the server.
    $ wp host-check --path=wordpress
    [2018-08-16 13:41:48] Loading: wordpress
    [2018-08-16 13:41:48] WordPress version: 4.9.8
    [2018-08-16 13:41:48] Next scheduled wp_version_check: 2018-08-13 23:31:31
    [2018-08-16 13:41:48] Yes: WordPress install is hosted here (HTTP code 200)
    [2018-08-16 13:41:49] Yes: wp-login loads as expected (HTTP code 200)
    [2018-08-16 13:41:49] Summary: wordpress, hosted-valid-login, 4.9.8
    [2018-08-16 13:41:49] Details: {"wp_version_check":"2018-08-13 23:31:31","active_plugins":["debug-bar\/debug-bar.php"],"active_theme":"wordpress-theme","user_count":3,"post_count":89,"last_post_date":"2018-08-06 13:22:39"}

    # Error connecting to the database when loading site.
    $ wp host-check --path=wordpress
    [2018-08-16 13:40:03] Loading: wordpress
    [2018-08-16 13:40:03] WordPress version: 4.7.6
    [2018-08-16 13:40:03] Summary: wordpress, error-db-connect, 4.7.6
    [2018-08-16 13:40:03] Details: {"wp_version_check":null,"active_plugins":null,"active_theme":null,"user_count":null,"post_count":null,"last_post_date":null

## Installing

Installing this package requires WP-CLI v2 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with:

    wp package install git@github.com:danielbachhuber/host-check-command.git

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/danielbachhuber/host-check-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/danielbachhuber/host-check-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/danielbachhuber/host-check-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

## Support

Github issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
