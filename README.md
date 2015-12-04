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

The only things that should break are calls to `is_resource()` on MySQL connections and results, as these
are now their `mysqli` equivalents.

Additionally, some errors are now from `ext/mysqli`, and others are `E_USER_WARNING` instead of `E_WARNING`.
