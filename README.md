[![Build Status](https://travis-ci.org/dshafik/php7-mysql-shim.svg?branch=master)](https://travis-ci.org/dshafik/php7-mysql-shim)
[![Build status](https://ci.appveyor.com/api/projects/status/tvj60v4y3o0mn4wp?svg=true)](https://ci.appveyor.com/project/dshafik/php7-mysql-shim)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/dshafik/php7-mysql-shim/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/dshafik/php7-mysql-shim/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/dshafik/php7-mysql-shim/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/dshafik/php7-mysql-shim/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/43c28e54-9c4a-40b4-9cc9-d6409d781dda/mini.png)](https://insight.sensiolabs.com/projects/43c28e54-9c4a-40b4-9cc9-d6409d781dda)
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