# Orchestra

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/pointybeard/orchestra/badges/quality-score.png?b=master)][ext-scrutinizer]
[![Code Coverage](https://scrutinizer-ci.com/g/pointybeard/orchestra/badges/coverage.png?b=master)][ext-scrutinizer]
[![Build Status](https://scrutinizer-ci.com/g/pointybeard/orchestra/badges/build.png?b=master)][ext-scrutinizer]

[Orchestra][ext-Orchestra] is a meta package for defining, scaffolding, rapidly deploying, and re-deploying [Symphony CMS][ext-Symphony CMS] builds.

-   [Installation](#installation)
-   [Basic Usage](#basic-usage)
-   [About](#about)
    -   [Requirements](#dependencies)
    -   [Dependencies](#dependencies)
-   [Documentation](#documentation)
-   [Support](#support)
-   [Contributing](#contributing)
-   [License](#license)

## Installation

### Pre-compiled

```bash
$ curl -sSL https://github.com/pointybeard/orchestra/releases/download/1.0.0/orchestra.phar > orchestra.phar
$ sudo mv orchestra.phar /usr/local/sbin/orchestra
$ sudo chmod 0755 /usr/local/sbin/orchestra
```

This will download the pre-compiled Orchestra binary and move it to `/usr/local/sbin`.

### From Source

```bash
$ git clone --depth 1 https://github.com/pointybeard/orchestra.git
$ composer update \
    --no-cache \
    --optimize-autoloader \
    --no-dev \
    --working-dir="./orchestra"
$ make && sudo make install
```

This will install Orchestra to `/usr/local/sbin`. Use `target=/some/path` to specify a different destination for the Orchestra binary, e.g. `make install target=~/bin/orchestra`

## Basic Usage

1. Initialise a new Orchestra project by running

```bash
$ orchestra init myproject
```
The name of your project (in this example, "myproject") is optional. If ommitted, the name of the parent folder will be used instead.

2. Update `.orchestra/build.json` with your project specific details (see, [Customising your Build](.docs/01_basics.md#customising-your-build))
3. Run the following command to build and deploy your project

```bash
$ orchestra build
```

**Tip: Use `--help` for details on options and flags.**

4. Create virtual hosts in Apache that point to `www/admin` (see, [Navigating to the Admin](.docs/01_basics.md#navigating-to-the-admin)) and `www/www` (see, [Viewing the Frontend](.docs/01_basics.md#viewing-the-frontend) and [Creating Sub-projects](.docs/01_basics.md#creating-sub-projects)).

## About

### Requirements

-   Orchestra works with PHP 7.3 or above.
-   [Composer], which is used to install dependencies

### Dependencies

Orchestra depends on the following Composer dev libraries:

- [squizlabs/php_codesniffer][dep-php_codesniffer]
- [friendsofphp/php-cs-fixer][dep-friendsofphp/php-cs-fixer]
- [damianopetrungaro/php-commitizen][dep-php-commitizen]
- [php-parallel-lint/php-parallel-lint][dep-php-parallel-lint]

## Documentation

**Documentation is coming soon!**

Read the [full documentation here][ext-docs].

## Support

If you believe you have found a bug, please report it using the [GitHub issue tracker][ext-issues],
or better yet, fork the library and submit a pull request.

## Contributing

We encourage you to contribute to this project. Please check out the [Contributing to this project][doc-CONTRIBUTING] documentation for guidelines about how to get involved.

## Author
-   Alannah Kearney - hi@alannahkearney.com - http://twitter.com/pointybeard
-   See also the list of [contributors][ext-contributor] who participated in this project

## License
"Orchestra" is released under the MIT License. See [LICENCE][doc-LICENCE] for details.

[doc-CONTRIBUTING]: https://github.com/pointybeard/orchestra/blob/master/CONTRIBUTING.md
[doc-LICENCE]: http://www.opensource.org/licenses/MIT
[ext-issues]: https://github.com/pointybeard/orchestra/issues
[ext-Symphony CMS]: http://getsymphony.com
[ext-Composer]: http://getcomposer.com
[ext-Orchestra]: https://github.com/pointybeard/orchestra
[ext-contributor]: https://github.com/pointybeard/orchestra/contributors
[ext-docs]: https://github.com/pointybeard/orchestra/blob/master/.docs/toc.md
[ext-scrutinizer]: https://scrutinizer-ci.com/g/pointybeard/orchestra/?branch=master
[dep-php_codesniffer]: https://github.com/squizlabs/php_codesniffer
[dep-friendsofphp/php-cs-fixer]: https://github.com/friendsofphp/php-cs-fixer
[dep-php-commitizen]: https://github.com/damianopetrungaro/php-commitizen
[dep-php-parallel-lint]: https://github.com/php-parallel-lint/php-parallel-lint
