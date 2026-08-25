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

This library supports you in creating, reading, and validating [llms.txt](https://llmstxt.org/) Markdown files via PHP.
It targets [v2](https://llmstxt.org/changes.html) of the specification. A good example `llms.txt` file is the [one](https://docs.astral.sh/uv/llms.txt) from the [uv](https://docs.astral.sh/uv/) project.

## What's llms.txt?

Think of it like `robots.txt` for LLMs. The evolving spec is available over [here](https://llmstxt.org/), its
second version is summarised [here](https://llmstxt.org/changes.html). For the structure of a `llms.txt` file you can also have a look at this
repository's [llms.txt](llms.txt) file.

## Installation and usage

```bash
composer require stolt/llms-txt-php
```

### Creating a llms.txt file

```php
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;

$section1 = (new Section())->name('Section name')
    ->addLink((new Link())->urlTitle('Link title')
        ->url('https://link_url')->urlDetails('Optional link details')
    );
$section2 = (new Section())->name('Optional')
    ->link((new Link())->urlTitle('Link title')
        ->url('https://link_url')
    );

$llmsTxt = (new LlmsTxt())->title('Test title')
  ->description('Test description')
  ->details('Test details')
  ->addSection($section1) // OR ->addSections([$section1, $section2])
  ->section($section2) // alias method
  ->toString(); // OR ->toFile('/path/to/llmsTxtToBe.md');
```

### Setting and reading the Optional section

Since [v2](https://llmstxt.org/changes.html) of the specification the `Optional` section no longer carries mechanical
semantics, it stays a convention for secondary links. The `optional` and `getOptional` methods give direct
access to it, without having to know the section name.

```php
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;

$llmsTxt = (new LlmsTxt())->title('Test title')
    ->description('Test description')
    ->details('Test details')
    ->optional((new Section())->addLink(
        (new Link())->urlTitle('Secondary link')->url('https://link_url')
    ));

$optionalSection = $llmsTxt->getOptional(); // null when there is none
```

The section is named `Optional` automatically, and an already present `Optional` section is replaced. The accessor
also works on a parsed `llms.txt` file.

### Making a llms.txt file discoverable

Version two of the specification introduced link relations so agents don't have to guess where things are.
`rel="alternate" type="text/markdown"` points at the Markdown version of a page, `rel="describedby"` at the
`llms.txt` file covering it. Both can be served as HTML `<link>` elements or as an HTTP `Link` header.

```php
use Stolt\LlmsTxt\Discovery;

$discovery = new Discovery();

$discovery->asLinkElements('/docs/llms.txt', '/docs/page.html.md');
$discovery->asLinkHeader('/docs/llms.txt', '/docs/page.html.md');
```

Value of the rendered link elements, and of the header value, which excludes the `Link:` field name so it can be
handed straight to `header()`:

```html
<link rel="alternate" type="text/markdown" href="/docs/page.html.md">
<link rel="describedby" href="/docs/llms.txt">
```

```
</docs/page.html.md>; rel="alternate"; type="text/markdown", </docs/llms.txt>; rel="describedby"
```

The same relations can be read back out of HTML content or a `Link` header value.

```php
$discovery->describedByUrls($html);              // ['/docs/llms.txt']
$discovery->markdownAlternates($html);           // ['/docs/page.html.md']

$discovery->describedByUrlsFromHeader($header);
$discovery->markdownAlternatesFromHeader($header);
```

Two URL rules of the specification are available as helpers. A page may offer its Markdown version either by
appending `.md` or by swapping its extension for `.md`, and a `llms.txt` file covers the pages under its path, where
the most specific file applies.

```php
$discovery->markdownUrls('/docs/page.html');  // ['/docs/page.html.md', '/docs/page.md']
$discovery->markdownUrls('/docs/');           // ['/docs/index.html.md', '/docs/index.md']
$discovery->markdownUrls('/docs/page.md');    // ['/docs/page.md'], already a Markdown URL

$discovery->coveringUrls('/docs/a/b.html');   // ['/docs/a/llms.txt', '/docs/llms.txt', '/llms.txt']
$discovery->coveringUrl('/docs/a/b.html', ['/llms.txt', '/docs/llms.txt']); // '/docs/llms.txt'
```

### Combining Discovery and LlmsTxt

`Discovery` locates files, `LlmsTxt` reads them, which makes the two complement each other. An agent that has the
HTML of a page, or its `Link` header, can follow the `describedby` relation and parse what it finds.

```php
use Stolt\LlmsTxt\Discovery;
use Stolt\LlmsTxt\LlmsTxt;

$discovery = new Discovery();

foreach ($discovery->describedByUrls($html) as $llmsTxtUrl) { // OR describedByUrlsFromHeader($header)
    $llmsTxt = (new LlmsTxt())->parse($llmsTxtUrl);

    if ($llmsTxt->validate()) {
        $sections = $llmsTxt->getSections();
        // ...
    }
}
```

The other direction, when serving a page, is to announce the `llms.txt` file covering it next to its Markdown
version. The keys of the file list are the URLs the `llms.txt` files are served under, and the most specific one
covering the requested page wins.

```php
$llmsTxts = [
    '/llms.txt' => (new LlmsTxt())->parse('/path/to/llms.txt'),
    '/docs/api/llms.txt' => (new LlmsTxt())->parse('/path/to/docs/api/llms.txt'),
];

$page = '/docs/api/v2/endpoints.html';

\header('Link: ' . $discovery->asLinkHeader(
    $discovery->coveringUrl($page, \array_keys($llmsTxts)) ?? '',
    $discovery->markdownUrls($page)[0]
));
```

Value of the sent header:

```
Link: </docs/api/v2/endpoints.html.md>; rel="alternate"; type="text/markdown", </docs/api/llms.txt>; rel="describedby"
```

Since the `markdownUrls` method returns the URL forms a Markdown version may be served under, it also composes with
the `$fetcher` of the context expansion below. The following fetcher resolves every linked page through its Markdown 
version and falls back to the page itself when there is none, which keeps HTML out of the context file.

```php
use Stolt\LlmsTxt\Discovery;
use Stolt\LlmsTxt\LlmContext;

$discovery = new Discovery();
$llmContext = new LlmContext();

$fetcher = static function (string $url) use ($discovery, $llmContext): string {
    foreach ($discovery->markdownUrls($url) as $markdownUrl) {
        try {
            return $llmContext->fetch($markdownUrl);
        } catch (\RuntimeException) {
            continue;
        }
    }

    return $llmContext->fetch($url);
};

$context = (new LlmsTxt())->parse('/path/to/llmsTxt.md')->toLlmContext(false, $fetcher);
```

All of the above is covered by [DiscoveryIntegrationTest.php](tests/DiscoveryIntegrationTest.php).

### Expanding a llms.txt file into a LLM context file

Linked documents can be expanded into an XML context file. Version two of the specification dropped its
`llms_txt2ctx` context expansion tooling, and with it the mechanical meaning of the `Optional` section, so all
sections are expanded by default. Pass `$skipOptional` to leave the secondary links out and get a shorter context.

```php
use Stolt\LlmsTxt\LlmsTxt;

$llmsTxt = (new LlmsTxt())->parse('/path/to/llmsTxt.md'); // OR parse('markdown-string')

$context = $llmsTxt->toLlmContext(); // OR ->toLlmContext(true) to skip the Optional section
$llmsTxt->toLlmContextFile('/path/to/llm-ctx.xml');
```

> [!TIP]
> Pass a `$fetcher` callable `(string $url): string` to resolve URLs without hitting the network (for tests or a custom
> HTTP client). The default fetcher reads local files and otherwise uses PHP streams.

The context file is a well-formed XML, the details of the `llms.txt` file and the fetched documents are escaped as
character data. Quotes are kept as they are, since they carry no meaning outside an attribute value.

### Validating and reading a llms.txt file and its parts

The title is the only element the specification requires, so a missing one is the only validation error. The
description, details, and file lists are recommended, missing ones are reported as validation warnings.

```php
use Stolt\LlmsTxt\LlmsTxt;

$llmsText = (new LlmsTxt())->parse('/path/to/llmsTxt.md'); // OR parse('markdown-string')

if ($llmsText->validate()) {
    $title = $llmsText->getTitle();
    $description = $llmsText->getDescription();
    $details = $llmsText->getDetails();
    $sections = $llmsText->getSections();
}
```

In case you want to get the exact validation errors, you need to call `validate` with the `detailed` flag sat to
`true` and then use the `errors()` method like shown below. The recommended, but not required, elements a file is
missing are available via the `warnings()` method.

```php
use Stolt\LlmsTxt\LlmsTxt;

$llmsText = (new LlmsTxt())->parse('/path/to/llmsTxt.md'); // OR parse('markdown-string')

$validationResult = $llmsTxt->validate(true);
if ($validationResult->isValid()) {
    $title = $llmsText->getTitle();
    $description = $llmsText->getDescription();
    $details = $llmsText->getDetails();
    $sections = $llmsText->getSections();
} else {
    $validationErrors = $validationResult->errors();
    // ...
}

if ($validationResult->hasWarnings()) {
    $validationWarnings = $validationResult->warnings();
    // ...
}
```

> [!TIP]
> To interact with `llms.txt` files from the console, the complement package [llms-txt-php-cli](https://github.com/raphaelstolt/llms-txt-php-cli) might come in handy.
> The complementary package also includes four AI skills that can be used to interact with `llms.txt` files.

### Inline LLM instructions in HTML

[Vercel](https://vercel.com/) [proposed](https://vercel.com/blog/a-proposal-for-inline-llm-instructions-in-html) a non-formal 
standard for inlining LLM instructions in HTML, based on the `llms.txt` standard.

```php
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;

$section1 = (new Section())->name('Section name')
    ->addLink((new Link())->urlTitle('Link title')
        ->url('https://link_url')->urlDetails('Optional link details')
    );
$section2 = (new Section())->name('Optional')
    ->link((new Link())->urlTitle('Link title')
        ->url('https://link_url')
    );

$llmsTxtContent = (new LlmsTxt())->title('Test title')
  ->description('Test description')
  ->details('Test details')
  ->sections([$section1, $section2])
  ->asScriptTag(); // OR ->toEmbeddedInScriptTag()
```

Value of `$llmsTxtContent`:

```html
<script type="text/llms.txt">
<!-- programmatically assembled llms.txt content -->
</script>
```

For more usage examples, have a look at the tests i.e. [LlmsTxtTest.php](tests/LlmsTxtTest.php).

### Extract LLM instructions from HTML

```php
use Stolt\LlmsTxt\Extractor;

$html = <<<HTML
<html>
  <body>
    <script type="text/llms.txt"># first llms.txt content</script>
    Some other content.
    <p>And some more content.</p>
    <br />
    <script type="text/llms.txt"># second llms.txt content</script>
  </body>
</html>
HTML;

$llmsTxts = (new Extractor())->extractFromHtml($html); // OR ->extractFromFile('/path/to/file.html')
```

Value of `$llmsTxts`:

```php
array(2) {
  [0]=>
  string(11) "# first llms.txt content"
  [1]=>
  string(12) "# second llms.txt content"
}
```

To retrieve already parsed `llms.txt` object instances, pass the `parse` flag to the available extraction methods.

Value of `$llmsTxts` when parsed:

```php
array(2) {
  [0]=>
  object(Stolt\LlmsTxt\LlmsTxt)#11 (5) {
    ["hasBeenParsed":"Stolt\LlmsTxt\LlmsTxt":private]=>
    bool(true)
    ["title":"Stolt\LlmsTxt\LlmsTxt":private]=>
    string(22) "first llms.txt content"
    ["description":"Stolt\LlmsTxt\LlmsTxt":private]=>
    string(0) ""
    ["details":"Stolt\LlmsTxt\LlmsTxt":private]=>
    string(0) ""
    ["sections":"Stolt\LlmsTxt\LlmsTxt":private]=>
    array(0) {
    }
  }
  [1]=> // ... ommitted for brevity
}
```



### Running tests

``` bash
composer test
```

### License

This library is licensed under the MIT license. Please see [LICENSE.md](LICENSE.md) for more details.

### Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more details.

### Contributing

Please see [CONTRIBUTING.md](.github/CONTRIBUTING.md) for more details.
