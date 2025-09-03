# How to contribute

Thanks for considering to contribute to `llms-txt-php`.

## Setting up a development environment

To set up a development environment, please follow the next shown instructions.

```bash
git clone git@github.com:raphaelstolt/llms-txt-php.git
composer install
// implement your changes
composer pre-commit-check
```

Please follow these guidelines when sending changes upstream:

- All code __MUST__ follow the PSR-2 coding standard. Please see [PSR-2](http://www.php-fig.org/psr/psr-2/) for more details.

- Coding standard compliance __MUST__ be ensured before committing or opening pull requests by running `composer cs-fix` or `composer cs-lint` in the root directory of this repository.

- Commits __MUST__ follow the [Conventional Commits](https://t.co/RF2z4M3jSo) conventions.

- All upstreamed contributions __MUST__ use [feature / topic branches](https://git-scm.com/book/en/v2/Git-Branching-Branching-Workflows) to ease merging.

- Please run `composer pre-commit-check` before opening a pull request.
