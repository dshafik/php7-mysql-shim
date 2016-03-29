# Contributing

## Forking

You should be working in a fork, see the [following documentation](https://help.github.com/articles/fork-a-repo/)

## Before Making Any Changes

### Fetch The Latest Changes from upstream

> On the `master` branch

```sh
$ git fetch --all
$ git rebase upstream/master
```

### Create a New Branch

```sh
$ git checkout -b reason-for-changes
```

### Refresh Dependencies

```sh
$ composer install
```

## Testing Your Changes

In the root directory, you can run the test suite by running:

```sh
$ vendor/bin/phpunit 
```

## After Making Your Changes

### Commit Your Changes

```sh
$ git add [files...]
$ git commit -m "DESCRIPTION OF CHANGES"
$ git push origin master
```

## Pushing Changes Back Upstream

To contribute your changes back, simply perform a [Pull Request](https://help.github.com/articles/using-pull-requests/) against the master branch.
