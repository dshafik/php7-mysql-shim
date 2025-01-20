[![Build Status](https://github.com/dshafik/php7-mysql-shim/workflows/Unit%20Tests/badge.svg)](https://github.com/dshafik/php7-mysql-shim/actions)
![Code Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/dshafik/ee79527e0098afea147bffc33bf710d3/raw/coverage.json)

<p align="center">
  <img width="500" height="500" src="https://github.com/user-attachments/assets/28526ddb-1748-46ba-a984-81bcc238274d">
</p>

# PHP 7 Shim for ext/mysql

This library attempts to create a drop-in replacement for ext/mysql on PHP 7 using mysqli.

For the most part, it should _just work_.

## Why You Shouldn't Use This Library

This library is meant to be a _stop-gap_. It will be slower than using the native functions directly.

**You should switch to `ext/pdo_mysql` or `ext/mysqli`, and migrate to [prepared queries](http://php.net/manual/en/pdo.prepared-statements.php) to ensure you are securely interacting with your database.**

## Installation

To install, either add `dshafik/php7-mysql-shim` to your `composer.json`:

```sh
$ composer require dshafik/php7-mysql-shim
```

or, clone/download this repo, and include `lib/mysql.php` in your project.

## Usage

When installed with composer, the library is included automatically. 

Once the `lib/mysql.php` file is included, it will create `mysql_*` functions if they don't already exist. _**You may safely include the file in a PHP 5.3.6+ project**_, it will do nothing if the mysql extension is already available.

## Caveats

- Calls to `is_resource()` and `get_resource_type()` on MySQL connections and results will fail as these are now their `mysqli` equivalents.
- Some errors are now from `ext/mysqli`, and others are `E_USER_WARNING` instead of `E_WARNING`.
- You must use the `mysqli.*` INI entries instead of `mysql.*` (e.g. `mysqli.default_user` instead of `mysql.default_user`)
- If no host, username, password parameter is provided when using the `mysql_*` functions, the default values from the corresponding `mysqli.*` settings from `php.ini` file will be used (e.g. `mysqli.default_host`, `mysqli.default_user`, `mysqli.default_pw`)

## Alternatives

Instead of using this drop-in-replacement library you should consider refactoring your code from `mysql` to e.g. `mysqli`. This process can be automated with e.g. https://stackoverflow.com/a/61597957
