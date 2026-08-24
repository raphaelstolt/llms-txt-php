# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`stolt/llms-txt-php` is a dependency-free PHP library (only `ext-dom`/`ext-libxml`) for writing, reading, and
validating [llms.txt](https://llmstxt.org/) Markdown files. It targets [v2](https://llmstxt.org/changes.html) of the
specification. The public API surface is documented in detail in the README — keep both in sync when changing it.

A complementary console package lives in a separate repository: [llms-txt-php-cli](https://github.com/raphaelstolt/llms-txt-php-cli).

## Commands

```bash
composer test                                    # PHPUnit (stops on first failure, see phpunit.xml.dist)
vendor/bin/phpunit --filter itParsesLlmsTxtFile  # single test method
vendor/bin/phpunit tests/DiscoveryTest.php       # single test file
composer test-with-coverage                      # HTML coverage into coverage-reports/ (sets XDEBUG_MODE)

composer cs-lint                                 # php-cs-fixer dry run, fails on violation
composer cs-fix                                  # php-cs-fixer fix in place
composer static-analyse                          # PHPStan level 5 over src/ and tests/
composer spell-check                             # peck (needs aspell + aspell-en)
composer validate-gitattributes                  # lean-package-validator
composer dependency-analyse                      # composer-dependency-analyser

composer pre-commit-check                        # test + cs-lint + static-analyse + spell-check + gitattributes
```

Run `composer pre-commit-check` before committing or opening a pull request. CI runs the test suite on PHP 8.3, 8.4,
and 8.5 (`composer.json` still allows `>=8.1`), and lints on 8.4.

## Architecture

Six classes under `Stolt\LlmsTxt` (`src/`, PSR-4), each covering one concern of the specification:

- `LlmsTxt` — the aggregate root. Holds title, description, details, and an ordered list of `Section`s; renders
  (`toString`, `toFile`, `asScriptTag`), parses (`parse`), and validates (`validate`).
- `Section` / `Section\Link` — the `## Heading` blocks and their `- [title](url): details` lines. Both render
  themselves via `toString`, which is how `LlmsTxt::toString` composes output.
- `Discovery` — v2 link relations (`rel="describedby"`, `rel="alternate" type="text/markdown"`). Renders and parses
  them as HTML `<link>` elements *and* as HTTP `Link` header values, plus the two URL rules of the spec
  (`markdownUrls`, `coveringUrls`/`coveringUrl`). Pure string/DOM work, no network.
- `LlmContext` — expands the linked documents of a parsed file into an XML context file (`<project>` / section tag /
  `<doc>`). Reached through `LlmsTxt::toLlmContext()` / `toLlmContextFile()`.
- `Extractor` — pulls `<script type="text/llms.txt">` blocks out of HTML, optionally parsing each into an `LlmsTxt`.
- `Validation\ValidationResult` / `ValidationError` — errors vs. warnings container returned by `validate(true)`.

`Discovery` and `LlmsTxt` are designed to compose: `Discovery` locates files and Markdown page versions, `LlmsTxt`
reads them. `tests/DiscoveryIntegrationTest.php` is the reference for those combinations.

### Behaviours that are easy to get wrong

- **`parse()` returns a new instance**, not `$this` — always use the return value. It is also the only thing that
  sets the private `hasBeenParsed` flag.
- **`validate()` throws** an `Exception` on an instance that wasn't produced by `parse()`.
- **Validation severity follows the spec**: a missing title is the *only* error, because the title is the only
  required element. Missing description, details, sections, and section links are *warnings*. Don't promote a
  warning to an error without a spec change to point at.
- **`parse()` accepts a path or raw content**, discriminated in `extractContent()`: content must start with `#`, a
  path must end in `txt` or `md`, anything else throws.
- **v2 dropped the mechanical meaning of the `Optional` section.** It is now just a convention for secondary links,
  so `toLlmContext()` expands *all* sections by default; `$skipOptional` is opt-in. `LlmsTxt::optional()` /
  `getOptional()` name and find that section via `LlmsTxt::OPTIONAL_SECTION_NAME`.
- **`addSection()` appends and de-duplicates by name; `addSections()`/`sections()` replaces the whole list.**
- **Network access is injectable**: pass a `callable(string $url): string` `$fetcher` to `toLlmContext()` /
  `toLlmContextFile()` / `LlmContext::expand()`. Tests must use this instead of hitting the network; the default
  `LlmContext::fetch()` reads local files, then falls back to PHP streams.

## Conventions

- Fluent setters are bare nouns (`title()`, `description()`, `name()`, `url()`), getters are prefixed (`getTitle()`,
  `getLinks()`), and several `addX()` methods have a singular alias (`section()`/`addSection()`,
  `link()`/`addLink()`). Follow that pairing when adding to the API.
- All classes are `final` and `declare(strict_types=1)`. php-cs-fixer enforces `final_internal_class` — the one
  exception, `tests/TestCase.php`, opts out with a `@not-fix` docblock annotation.
- `native_function_invocation` is enforced for internal functions, hence the `\`-prefixed calls (`\count`,
  `\file_get_contents`, …) throughout. New code must match.
- Tests use PHPUnit attributes (`#[Test]`) with camelCase `itDoesSomething` method names, extend
  `Stolt\LlmsTxt\Tests\TestCase`, and read fixtures from `tests/fixtures/`. `TestCase` provides
  `setUpTemporaryDirectory()` with automatic teardown for tests that write files.
- Commits follow [Conventional Commits](https://www.conventionalcommits.org/); contributions go on feature branches.
- New domain vocabulary that `peck` flags belongs in the `ignore.words` list of `peck.json`.
- `.gitattributes` is an export-ignore-everything allowlist (lean dist package) — a new directory that must ship in
  the Composer dist needs a matching `-export-ignore` entry, which `composer validate-gitattributes` checks.
- Keep `CHANGELOG.md` and the repository's own `llms.txt` current alongside user-facing changes.
