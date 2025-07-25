# llms.txt PHP

![Test Status](https://github.com/raphaelstolt/llms-txt-php/workflows/test/badge.svg)
[![Version](http://img.shields.io/packagist/v/stolt/llms-txt-php.svg?style=flat)](https://packagist.org/packages/stolt/llms-txt-php)
![Downloads](https://img.shields.io/packagist/dt/stolt/llms-txt-php)
![PHP Version](https://img.shields.io/badge/php-8.1+-ff69b4.svg)
[![PDS Skeleton](https://img.shields.io/badge/pds-skeleton-blue.svg?style=flat)](https://github.com/php-pds/skeleton)
![llms.txt](https://img.shields.io/badge/llms.txt-available-blue.svg?style=flat)

<p align="center">
    <img src="llms-txt-logo.png" 
         alt="Llms txt logo">
</p>

This library supports you in creating, reading, and validating [llms.txt](https://llmstxt.org/) Markdown files via PHP.
A good example `llms.txt` file is the [one](https://docs.astral.sh/uv/llms.txt) from the [uv](https://docs.astral.sh/uv/) project.

## What's llms.txt?

Think of it like `robots.txt` for LLMs. The evolving spec is available over [here](https://llmstxt.org/). 
For the structure of a `llms.txt` file you can also have a look at this repository's [llms.txt](llms.txt) file.

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
    ->addLink((new Link())->urlTitle('Link title')
        ->url('https://link_url')
    );

$llmsTxt = (new LlmsTxt())->title('Test title')
  ->description('Test description')
  ->details('Test details')
  ->addSection($section1) // OR ->addSections([$section1, $section2])
  ->addSection($section2)
  ->toString(); // OR ->toFile('/path/to/llmsTxtToBe.md');
```

### Validating and reading a llms.txt file and its parts

```php
use Stolt\LlmsTxt\LlmsTxt;

$llmsText = (new LlmsTxt())->parse('/path/to/llmsTxt.md');

if ($llmsText->validate()) {
    $title = $llmsText->getTitle();
    $description = $llmsText->getDescription();
    $details = $llmsText->getDetails();
    $sections = $llmsText->getSections();
}
```

For more usage examples, have a look at the tests i.e. [LlmsTxtTest.php](tests/LlmsTxtTest.php).

### Validating a llms.txt file via the included CLI

```bash
bin/llms-txt validate tests/fixtures/uv.llms.md

The provided llms txt file tests/fixtures/uv.llms.md is valid.
```

or

```bash
bin/llms-txt validate https://docs.astral.sh/uv/

The delivered llms txt file from https://docs.astral.sh/uv/ is valid.
```

A `llms.txt` file is considered valid, when a __title__, __description__, __details__, and at least __one section__
with at least __one link__ are available.

### Initialising a llms.txt file via the included CLI

```bash
bin/llms-txt init [<llms-txt-file>]

Created llms txt file llms.txt.

cat llms.txt

# Init title

> Init description

Init details

## Init section

- [Init URL](http://init.org)
```

If no `llms-txt-file` name is provided, it defaults to `llms.txt`. 

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
