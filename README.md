# llms.txt PHP

![Test Status](https://github.com/raphaelstolt/llms-txt-php/workflows/test/badge.svg)
[![Version](http://img.shields.io/packagist/v/stolt/llms-txt-php.svg?style=flat)](https://packagist.org/packages/stolt/llms-txt-php)
![Downloads](https://img.shields.io/packagist/dt/stolt/llms-txt-php)
![PHP Version](https://img.shields.io/badge/php-8.1+-ff69b4.svg)
[![PDS Skeleton](https://img.shields.io/badge/pds-skeleton-blue.svg?style=flat)](https://github.com/php-pds/skeleton)
![llms.txt](https://img.shields.io/badge/llms.txt-available-blue.svg?style=flat)
[![Lean dist package](https://img.shields.io/badge/lean-dist%20package-00ffb6.svg?style=flat)](https://github.com/raphaelstolt/lean-package-validator)

<p align="center">
    <img src="llms-txt-logo.png" 
         alt="Llms txt logo">
</p>

A PHP library for creating, parsing, validating, discovering, and expanding [`llms.txt`](https://llmstxt.org/) files.

Build and consume machine-readable Markdown context for LLMs and AI agents with a focused PHP API and no Composer runtime dependencies.

## Why llms-txt-php?

`llms.txt` is an emerging Markdown-based convention for providing LLMs and AI agents with a concise, structured overview of a website, project, or documentation set.

`llms-txt-php` gives PHP applications one API for working with that format:

- Create and serialize `llms.txt` documents.
- Parse local files, URLs, or Markdown strings.
- Validate required and recommended document elements.
- Inspect validation errors and warnings.
- Preserve meaningful Markdown details and whitespace while parsing.
- Discover `llms.txt` and Markdown resources using the specification's link relations and URL conventions.
- Expand linked documents into LLM context or `llms-full.txt`.
- Embed `llms.txt` content in HTML.
- Extract embedded `text/llms.txt` blocks from HTML.
- Work with the conventional `Optional` section without treating it as mechanically special.
- Provide custom fetchers for tests and applications that already have an HTTP client.

For the current specification, see [llmstxt.org](https://llmstxt.org/) and the [AnswerDotAI/llms-txt repository](https://github.com/AnswerDotAI/llms-txt).

## Installation

Install the package with Composer:

```bash
composer require stolt/llms-txt-php
```

## Quick start

### Create a `llms.txt`

```php
<?php

use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;

$llmsTxt = (new LlmsTxt())
    ->title('My project')
    ->description('A short description of my project.')
    ->details('Additional information about the project.')
    ->addSection(
        (new Section())
            ->name('Documentation')
            ->addLink(
                (new Link())
                    ->urlTitle('Getting started')
                    ->url('https://example.com/docs')
                    ->urlDetails('Getting started guide')
            )
    );

echo $llmsTxt->toString();
```

The generated document follows the familiar `llms.txt` structure:

```markdown
# My project

> A short description of my project.

Additional information about the project.

## Documentation

- [Getting started](https://example.com/docs): Getting started guide
```

Write the document directly to a file with `toFile()`:

```php
$llmsTxt->toFile('/path/to/llms.txt');
```

## Parse and read

Parse a file or Markdown string with `LlmsTxt`:

```php
use Stolt\LlmsTxt\LlmsTxt;

$llmsTxt = (new LlmsTxt())->parse('/path/to/llms.txt');

$title = $llmsTxt->getTitle();
$description = $llmsTxt->getDescription();
$details = $llmsTxt->getDetails();
$sections = $llmsTxt->getSections();
```

The parser can also be used directly when you want a focused parsing API:

```php
use Stolt\LlmsTxt\Parser\LlmsTxtParser;

$parser = new LlmsTxtParser();

$llmsTxt = $parser->parse($markdown);
$llmsTxt = $parser->parseFile('/path/to/llms.txt');
```

Parsing preserves the Markdown of the `details` content, including its meaningful line and paragraph structure. Transport-level differences such as a UTF-8 BOM and `CRLF`/`CR` line endings are normalized.

## Validate

The specification requires a title. The description, details, and file lists are recommended.

For a simple validity check:

```php
if ($llmsTxt->validate()) {
    // The document is valid.
}
```

For detailed errors and warnings:

```php
$validationResult = $llmsTxt->validate(true);

if (!$validationResult->isValid()) {
    foreach ($validationResult->errors() as $error) {
        // Handle validation error.
    }
}

if ($validationResult->hasWarnings()) {
    foreach ($validationResult->warnings() as $warning) {
        // Handle validation warning.
    }
}
```

Use `errors()` for validation errors and `warnings()` for recommended elements that are missing.

## The `Optional` section

The second version of the specification no longer gives the `Optional` section mechanical semantics. It remains a convention for secondary links.

`optional()` and `getOptional()` provide direct access without requiring callers to know the section name:

```php
$llmsTxt->optional(
    (new Section())->addLink(
        (new Link())
            ->urlTitle('Secondary link')
            ->url('https://example.com/secondary')
    )
);

$optionalSection = $llmsTxt->getOptional(); // null when absent
```

The section is named `Optional` automatically. Setting it replaces an existing `Optional` section.

The accessor also works with parsed documents.

## Discovery

Version two of the specification introduced link relations that allow agents to discover Markdown representations and the `llms.txt` file covering a page.

`Discovery` supports:

- `rel="alternate" type="text/markdown"`
- `rel="describedby"`
- HTML `<link>` elements
- HTTP `Link` headers
- Markdown URL conventions
- Finding the most specific `llms.txt` covering a page

### Generate discovery links

```php
use Stolt\LlmsTxt\Discovery;

$discovery = new Discovery();

$elements = $discovery->asLinkElements(
    '/docs/llms.txt',
    '/docs/page.html.md'
);

$header = $discovery->asLinkHeader(
    '/docs/llms.txt',
    '/docs/page.html.md'
);
```

The generated HTML is:

```html
<link rel="alternate" type="text/markdown" href="/docs/page.html.md">
<link rel="describedby" href="/docs/llms.txt">
```

The generated header value can be passed directly to PHP's `header()` function:

```php
header('Link: ' . $header);
```

### Read discovery links

```php
$discovery->describedByUrls($html);
// ['/docs/llms.txt']

$discovery->markdownAlternates($html);
// ['/docs/page.html.md']

$discovery->describedByUrlsFromHeader($header);
$discovery->markdownAlternatesFromHeader($header);
```

### Resolve Markdown and covering URLs

A page can expose its Markdown representation by appending `.md` or replacing its extension with `.md`:

```php
$discovery->markdownUrls('/docs/page.html');
// ['/docs/page.html.md', '/docs/page.md']

$discovery->markdownUrls('/docs/');
// ['/docs/index.html.md', '/docs/index.md']
```

A `llms.txt` file covers pages below its path. The most specific covering file wins:

```php
$discovery->coveringUrls('/docs/a/b.html');
// ['/docs/a/llms.txt', '/docs/llms.txt', '/llms.txt']

$discovery->coveringUrl(
    '/docs/a/b.html',
    ['/llms.txt', '/docs/llms.txt']
);
// '/docs/llms.txt'
```

## Combine discovery and parsing

`Discovery` locates resources while `LlmsTxt` parses them.

For example, an application can follow a `describedby` relation and parse the discovered document:

```php
use Stolt\LlmsTxt\Discovery;
use Stolt\LlmsTxt\LlmsTxt;

$discovery = new Discovery();

foreach ($discovery->describedByUrls($html) as $llmsTxtUrl) {
    $llmsTxt = (new LlmsTxt())->parse($llmsTxtUrl);

    if ($llmsTxt->validate()) {
        $sections = $llmsTxt->getSections();

        // Use the discovered context.
    }
}
```

When serving pages, the same API can be used to announce the covering `llms.txt` together with the page's Markdown representation:

```php
$llmsTxts = [
    '/llms.txt' => (new LlmsTxt())->parse('/path/to/llms.txt'),
    '/docs/api/llms.txt' => (new LlmsTxt())->parse('/path/to/docs/api/llms.txt'),
];

$page = '/docs/api/v2/endpoints.html';

$coveringUrl = $discovery->coveringUrl(
    $page,
    array_keys($llmsTxts)
);

header('Link: ' . $discovery->asLinkHeader(
    $coveringUrl ?? '',
    $discovery->markdownUrls($page)[0]
));
```

## Expand into LLM context

Linked documents can be expanded into an XML LLM context file.

```php
use Stolt\LlmsTxt\LlmsTxt;

$llmsTxt = (new LlmsTxt())->parse('/path/to/llms.txt');

$context = $llmsTxt->toLlmContext();

$llmsTxt->toLlmContextFile('/path/to/llm-ctx.xml');
```

By default, all sections are expanded. Pass `true` to skip the conventional `Optional` section:

```php
$context = $llmsTxt->toLlmContext(true);
```

### Custom fetchers

The default fetcher reads local files and otherwise uses PHP streams.

Pass a callable when you want to use a custom HTTP client, provide fixtures in tests, or control how linked documents are resolved:

```php
$fetcher = static function (string $url): string {
    // Fetch and return the document contents.
};

$context = $llmsTxt->toLlmContext(false, $fetcher);
```

The same fetcher approach is available when generating `llms-full.txt`.

## Generate `llms-full.txt`

Linked documents can also be expanded into a Markdown `llms-full.txt` document.

```php
$full = $llmsTxt->toFull();

$llmsTxt->toFullFile('/path/to/llms-full.txt');
```

The generated document keeps the `llms.txt` header and expands each link into a `###` block containing its details, source URL, and fetched document:

```markdown
# My project

> A short description of my project.

Additional information about the project.

## Documentation

### Getting started

Getting started guide

Source: https://example.com/docs

<!-- fetched document contents -->
```

Skip the conventional `Optional` section with:

```php
$full = $llmsTxt->toFull(true);
```

A custom fetcher can be supplied as the second argument, just like with `toLlmContext()`.

## Inline `llms.txt` in HTML

The library can embed `llms.txt` content in HTML using a `text/llms.txt` script element.

```php
$embedded = $llmsTxt->asScriptTag();
```

The result has the following form:

```html
<script type="text/llms.txt">
# My project

> A short description of my project.

...
</script>
```

`toEmbeddedInScriptTag()` is available as an alias.

## Extract `llms.txt` from HTML

Use `Extractor` to retrieve `text/llms.txt` blocks from HTML:

```php
use Stolt\LlmsTxt\Extractor;

$html = <<<HTML
<html>
    <body>
        <script type="text/llms.txt"># First document</script>
        <p>Regular page content.</p>
        <script type="text/llms.txt"># Second document</script>
    </body>
</html>
HTML;

$llmsTxts = (new Extractor())->extractFromHtml($html);
```

The result is an array of extracted Markdown strings:

```php
[
    '# First document',
    '# Second document',
]
```

You can also extract from a file:

```php
$llmsTxts = (new Extractor())->extractFromFile('/path/to/page.html');
```

Pass the parse flag when you want `LlmsTxt` objects instead of strings:

```php
$llmsTxts = (new Extractor())->extractFromHtml($html, true);

foreach ($llmsTxts as $llmsTxt) {
    echo $llmsTxt->getTitle();
}
```

## API overview

### `LlmsTxt`

The main document API:

- `title()`
- `description()`
- `details()`
- `addSection()`
- `addSections()`
- `section()`
- `sections()`
- `optional()`
- `getOptional()`
- `parse()`
- `toString()`
- `toFile()`
- `validate()`
- `getTitle()`
- `getDescription()`
- `getDetails()`
- `getSections()`
- `toLlmContext()`
- `toLlmContextFile()`
- `toFull()`
- `toFullFile()`
- `asScriptTag()`
- `toEmbeddedInScriptTag()`

### `Section`

Use `Section` to group links:

```php
$section = (new Section())
    ->name('Documentation')
    ->addLink($link);
```

### `Link`

Use `Link` to represent a Markdown file-list entry:

```php
$link = (new Link())
    ->urlTitle('Getting started')
    ->url('https://example.com/docs')
    ->urlDetails('Getting started guide');
```

### `Discovery`

Use `Discovery` for `llms.txt` discovery and Markdown URL resolution:

- `asLinkElements()`
- `asLinkHeader()`
- `describedByUrls()`
- `describedByUrlsFromHeader()`
- `markdownAlternates()`
- `markdownAlternatesFromHeader()`
- `markdownUrls()`
- `coveringUrls()`
- `coveringUrl()`

### `Extractor`

Use `Extractor` to extract embedded `text/llms.txt` blocks:

- `extractFromHtml()`
- `extractFromFile()`

### `LlmsTxtParser`

Use `LlmsTxtParser` when you need the parser directly:

- `parse()`
- `parseFile()`

## CLI

For command-line workflows, use the complementary [`stolt/llms-txt-php-cli`](https://github.com/raphaelstolt/llms-txt-php-cli) package.

Install it as a development dependency:

```bash
composer require --dev stolt/llms-txt-php-cli
```

The CLI currently provides commands for:

- validating `llms.txt` files
- checking links
- inspecting metadata
- creating an initial `llms.txt`
- rendering `llms.txt`
- listing included AI skills

See the [CLI repository](https://github.com/raphaelstolt/llms-txt-php-cli) for installation and usage details.

## Examples and tests

The test suite contains additional usage examples and documents edge cases that are important when parsing and serializing `llms.txt`.

For implementation-level examples, see the [`tests`](https://github.com/raphaelstolt/llms-txt-php/tree/main/tests) directory.

## Alternative libraries

If you are working in a Laravel application, you may also want to look at [`laravel-llms-txt`](https://github.com/laravel-llms-txt/laravel-llms-txt).

## Contributing

If you're considering contributing to this library, have a look at this repository's [CONTRIBUTING.md](.github/CONTRIBUTING.md)
for more advice.

## License

This library is licensed under the MIT license. See [`LICENSE.md`](LICENSE.md) for details.

## Changelog

See [`CHANGELOG.md`](CHANGELOG.md) for the release history.
