[![Build Status](https://travis-ci.org/dshafik/php7-mysql-shim.svg?branch=master)](https://travis-ci.org/dshafik/php7-mysql-shim)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/dshafik/php7-mysql-shim/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/dshafik/php7-mysql-shim/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/dshafik/php7-mysql-shim/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/dshafik/php7-mysql-shim/?branch=master)
# PHP 7 Shim for ext/mysql

This library attempts to create a drop-in replacement for ext/mysql on PHP 7 using mysqli.

For the most part, it should _just work_, although you either need to prefix all calls with a `\` (only internal functions will fallback to the global scope)
or import the functions into every file (e.g. `use function \mysql_connect`).

## Installation

To install, either add `dshafik/php7-mysql-shim` to your `composer.json`:

```sh
$ composer require dshafik/php7-mysql-shim
```

or, clone/download this repo, and include `mysql.php` in your project.

## Usage

Once the file is included, it will create `mysql_*` function if they don't already exist.

## Caveats

- Calls to `is_resource()` and `get_resource_type()` on MySQL connections and results will fail as these are now their `mysqli` equivalents.
- Some errors are now from `ext/mysqli`, and others are `E_USER_WARNING` instead of `E_WARNING`.
- You must use the `mysqli.*` INI entries instead of `mysql.*` (e.g. `mysqli.default_user` instead of `mysql.default_usert`)
- Column lengths reported by `mysql_field_len()` assume `latin1`
