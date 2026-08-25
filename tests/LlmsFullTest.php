<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;

final class LlmsFullTest extends TestCase
{
    private function stubFetcher(array $bodies): callable
    {
        return function (string $url) use ($bodies): string {
            if (!\array_key_exists($url, $bodies)) {
                throw new RuntimeException("Unable to fetch LLM context from {$url}");
            }

            return $bodies[$url];
        };
    }

    private function parsedExample(): LlmsTxt
    {
        return (new LlmsTxt())->parse(\realpath(__DIR__ . '/fixtures/example.md'));
    }

    #[Test]
    public function itIncludesTheOptionalSectionByDefault(): void
    {
        $full = $this->parsedExample()->toFull(
            false,
            $this->stubFetcher(['https://link_url' => 'Section body'])
        );

        $expected = <<<MARKDOWN
# Title

> Optional description goes here

Optional details go here

## Section name

### Link title

Optional link details

Source: https://link_url

Section body

## Optional

### Link title

Source: https://link_url

Section body

MARKDOWN;

        $this->assertSame($expected, $full);
    }

    #[Test]
    public function itSkipsTheOptionalSectionWhenRequested(): void
    {
        $full = $this->parsedExample()->toFull(
            true,
            $this->stubFetcher(['https://link_url' => 'Fetched body'])
        );

        $this->assertStringNotContainsString('## Optional', $full);
        $this->assertStringContainsString('## Section name', $full);
        $this->assertStringContainsString('Fetched body', $full);
    }

    #[Test]
    public function itExpandsAnOptionalSectionSetViaTheSetter(): void
    {
        $llmsTxt = (new LlmsTxt())->title('Title')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Doc')->url('https://example.test/doc.md')
                )
            )
            ->optional(
                (new Section())->addLink(
                    (new Link())->urlTitle('Secondary doc')->url('https://example.test/secondary.md')
                )
            );
        $fetcher = $this->stubFetcher([
            'https://example.test/doc.md' => 'Doc body',
            'https://example.test/secondary.md' => 'Secondary body',
        ]);

        $this->assertStringContainsString('Secondary body', $llmsTxt->toFull(false, $fetcher));
        $this->assertStringNotContainsString('Secondary body', $llmsTxt->toFull(true, $fetcher));
    }

    #[Test]
    public function itOmitsAnAbsentDescriptionAndDetails(): void
    {
        $llmsTxt = (new LlmsTxt())->title('Title only')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Doc')->url('https://example.test/doc.md')
                )
            );

        $expected = <<<MARKDOWN
# Title only

## Docs

### Doc

Source: https://example.test/doc.md

Doc body

MARKDOWN;

        $this->assertSame(
            $expected,
            $llmsTxt->toFull(false, $this->stubFetcher(['https://example.test/doc.md' => 'Doc body']))
        );
    }

    #[Test]
    public function itKeepsTheHeaderOfALlmsTxtFileWithoutSections(): void
    {
        $llmsTxt = (new LlmsTxt())->title('Header only')
            ->description('A description')
            ->details('Some details');

        $expected = <<<MARKDOWN
# Header only

> A description

Some details

MARKDOWN;

        $this->assertSame($expected, $llmsTxt->toFull());
    }

    #[Test]
    public function itSkipsAnEmptyFetchedBody(): void
    {
        $llmsTxt = (new LlmsTxt())->title('Empty')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Doc')->url('https://example.test/doc.md')
                )
            );

        $expected = <<<MARKDOWN
# Empty

## Docs

### Doc

Source: https://example.test/doc.md

MARKDOWN;

        $this->assertSame(
            $expected,
            $llmsTxt->toFull(false, $this->stubFetcher(['https://example.test/doc.md' => "  \n \n"]))
        );
    }

    #[Test]
    public function itInlinesTheMarkdownOfAFetchedBodyAsItIs(): void
    {
        $body = <<<MARKDOWN
# Doc heading

Some prose with `a < b` guards & R&D notes.

```bash
# a comment in a code block
echo "done"
```
MARKDOWN;

        $llmsTxt = (new LlmsTxt())->title('Verbatim')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Doc')->url('https://example.test/doc.md')
                )
            );

        $full = $llmsTxt->toFull(
            false,
            $this->stubFetcher(['https://example.test/doc.md' => $body])
        );

        $this->assertStringContainsString($body, $full);
    }

    #[Test]
    public function itExpandsAllLinksOfASection(): void
    {
        $llmsTxt = (new LlmsTxt())->title('Docs')
            ->addSection(
                (new Section())->name('Docs')
                    ->addLink((new Link())->urlTitle('First')->url('https://example.test/first.md'))
                    ->addLink(
                        (new Link())->urlTitle('Second')
                            ->url('https://example.test/second.md')
                            ->urlDetails('The second one')
                    )
            );

        $full = $llmsTxt->toFull(false, $this->stubFetcher([
            'https://example.test/first.md' => 'First body',
            'https://example.test/second.md' => 'Second body',
        ]));

        $this->assertStringContainsString('### First' . PHP_EOL . PHP_EOL . 'Source: https://example.test/first.md', $full);
        $this->assertStringContainsString('### Second' . PHP_EOL . PHP_EOL . 'The second one', $full);
        $this->assertStringContainsString('First body', $full);
        $this->assertStringContainsString('Second body', $full);
    }

    #[Test]
    public function itWritesTheFullFileToAFile(): void
    {
        $this->setUpTemporaryDirectory();
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'llms-full.txt';

        $llmsTxt = $this->parsedExample();
        $fetcher = $this->stubFetcher(['https://link_url' => 'Section body']);

        $this->assertTrue($llmsTxt->toFullFile($path, false, $fetcher));
        $this->assertSame($llmsTxt->toFull(false, $fetcher), \file_get_contents($path));
    }

    #[Test]
    public function itReadsALocalFilePathWithoutACustomFetcher(): void
    {
        $this->setUpTemporaryDirectory();
        $bodyPath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'doc.md';
        \file_put_contents($bodyPath, 'Local file body');

        $llmsTxt = (new LlmsTxt())->title('Local')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Local doc')->url($bodyPath)
                )
            );

        $expected = <<<MARKDOWN
# Local

## Docs

### Local doc

Source: {$bodyPath}

Local file body

MARKDOWN;

        $this->assertSame($expected, $llmsTxt->toFull());
    }

    #[Test]
    public function itThrowsWhenTheFetcherFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to fetch LLM context from https://link_url');

        $this->parsedExample()->toFull(false, function (string $url): string {
            throw new RuntimeException("Unable to fetch LLM context from {$url}");
        });
    }

    #[Test]
    public function theDefaultFetcherThrowsForAMissingPath(): void
    {
        $missing = '/this/path/does/not/exist.md';

        $llmsTxt = (new LlmsTxt())->title('Missing')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Gone')->url($missing)
                )
            );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to fetch LLM context from {$missing}");

        $llmsTxt->toFull();
    }

    #[Test]
    public function itParsesBackIntoTheHeaderOfTheExpandedLlmsTxtFile(): void
    {
        $full = $this->parsedExample()->toFull(
            false,
            $this->stubFetcher(['https://link_url' => 'Section body'])
        );

        $parsed = (new LlmsTxt())->parse($full);

        $this->assertSame('Title', $parsed->getTitle());
        $this->assertSame('Optional description goes here', $parsed->getDescription());
    }
}
